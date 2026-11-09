<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\ResourceModel;

use Automotive\Garage\Api\VehicleTypeProviderInterface;
use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\VehicleType;
use Automotive\Garage\Model\VehicleTypeInput;
use Automotive\Garage\Model\Market;
use Automotive\Garage\Model\Specification;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;

class VehicleTypeStorage implements VehicleTypeProviderInterface
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function get(int $id, bool $lock = false): ?VehicleType
    {
        $db = $this->resource->getConnection();
        $select = $db->select()->from($this->table())->where('entity_id = ?', $id);
        if ($lock) {
            $select->forUpdate(true);
        }
        $row = $db->fetchRow($select);
        return $row ? VehicleType::fromRow($row) : null;
    }

    public function getEnabled(int $id): ?VehicleType
    {
        $type = $this->get($id, $this->resource->getConnection()->getTransactionLevel() > 0);
        return $type?->enabled ? $type : null;
    }

    public function getByCode(string $code): ?VehicleType
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table())->where('code = ?', $code));
        return $row ? VehicleType::fromRow($row) : null;
    }

    /** @return VehicleType[] */
    public function getPage(int $page = 1): array
    {
        $db = $this->resource->getConnection();
        $select = $db->select()->from($this->table())->order(['market', 'make', 'model', 'model_year', 'variant'])
            ->limitPage(max(1, $page), 50);
        return array_map(VehicleType::fromRow(...), $db->fetchAll($select));
    }

    public function count(): int
    {
        $db = $this->resource->getConnection();
        return (int) $db->fetchOne($db->select()->from($this->table(), ['COUNT(*)']));
    }

    /** Each option is derived from the same canonical rows used by fitment. */
    public function options(string $level, string $make = '', string $model = '', ?int $year = null, string $market = 'GB'): array
    {
        $db = $this->resource->getConnection();
        $select = $db->select()->from($this->table(), [])->where('is_enabled = ?', 1);
        foreach (Market::filters($market, $level, $make, $model, $year) as $field => $value) {
            $select->where($field . ' = ?', $value);
        }
        if ($level === 'variant') {
            $select->columns('*')->order('variant');
            return array_map(static function (array $row): array {
                $type = VehicleType::fromRow($row);
                return ['value' => (string) $type->id, 'label' => $type->selectionLabel()];
            }, $db->fetchAll($select));
        }
        $column = match ($level) {
            'make' => 'make', 'model' => 'model', 'year' => 'model_year',
            default => throw new GarageException('Unknown vehicle selector level.'),
        };
        $select->columns([$column])->distinct()->order($column . ($level === 'year' ? ' DESC' : ' ASC'));
        return array_map(static fn ($value): array => ['value' => (string) $value, 'label' => (string) $value],
            $db->fetchCol($select));
    }

    public function save(array $input, ?int $id = null): VehicleType
    {
        $data = VehicleTypeInput::fromArray($input);
        $db = $this->resource->getConnection();
        $db->beginTransaction();
        try {
            if ($id !== null) {
                $old = $db->fetchRow($db->select()->from($this->table())->where('entity_id = ?', $id)->forUpdate(true));
                if (!$old) {
                    throw new GarageException('The vehicle definition could not be found.');
                }
                $changed = false;
                foreach (array_merge(['code', 'market', 'make', 'model', 'model_year', 'variant'], Specification::fields()) as $field) {
                    $changed = $changed || (string) $old[$field] !== (string) $data[$field];
                }
                if ($changed && $this->isUsed($id)) {
                    throw new GarageException('This definition is in use. Disable it and add a new definition to change its vehicle details.');
                }
                $db->update($this->table(), $data, ['entity_id = ?' => $id]);
            } else {
                $db->insert($this->table(), $data);
                $id = (int) $db->lastInsertId($this->table());
            }
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            if ($exception instanceof DuplicateException) {
                throw new GarageException('That code or vehicle definition already exists.', 0, $exception);
            }
            throw $exception;
        }
        return $this->get($id) ?? throw new GarageException('The vehicle definition could not be loaded.');
    }

    private function isUsed(int $id): bool
    {
        $db = $this->resource->getConnection();
        foreach (['automotive_garage_vehicle', 'automotive_garage_product_fitment'] as $table) {
            if ($db->fetchOne($db->select()->from($this->resource->getTableName($table), ['vehicle_type_id'])
                ->where('vehicle_type_id = ?', $id)->limit(1))) {
                return true;
            }
        }
        return false;
    }

    private function table(): string
    {
        return $this->resource->getTableName('automotive_garage_vehicle_type');
    }
}
