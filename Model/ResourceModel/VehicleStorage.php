<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\ResourceModel;

use Automotive\Garage\Api\VehicleStorageInterface;
use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Model\VehicleInput;
use Magento\Framework\App\ResourceConnection;

class VehicleStorage implements VehicleStorageInterface
{
    private const TABLE = 'automotive_garage_vehicle';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function getAll(int $customerId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->resource->getTableName(self::TABLE))
            ->where('customer_id = ?', $customerId)->order('entity_id ASC');
        return array_map(Vehicle::fromRow(...), $connection->fetchAll($select));
    }

    public function transaction(int $customerId, callable $operation): mixed
    {
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            // Lock the owner even when its garage is empty, serialising per-customer writes.
            $lock = $connection->select()
                ->from($this->resource->getTableName('customer_entity'), ['entity_id'])
                ->where('entity_id = ?', $customerId)->forUpdate(true);
            if (!$connection->fetchOne($lock)) {
                throw new GarageException('Please sign in to use your garage.');
            }
            $result = $operation();
            $connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function insert(int $customerId, VehicleInput $input): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->insert($table, ['customer_id' => $customerId] + $input->toArray());
        return (int) $connection->lastInsertId($table);
    }

    public function update(int $customerId, int $vehicleId, VehicleInput $input): void
    {
        $this->resource->getConnection()->update($this->resource->getTableName(self::TABLE), $input->toArray(),
            ['customer_id = ?' => $customerId, 'entity_id = ?' => $vehicleId]);
    }

    public function delete(int $customerId, int $vehicleId): void
    {
        $this->resource->getConnection()->delete($this->resource->getTableName(self::TABLE),
            ['customer_id = ?' => $customerId, 'entity_id = ?' => $vehicleId]);
    }

    public function setDefault(int $customerId, int $vehicleId): void
    {
        $this->setFlag($customerId, $vehicleId, 'is_default');
    }

    public function select(int $customerId, int $vehicleId): void
    {
        $this->setFlag($customerId, $vehicleId, 'is_selected');
    }

    private function setFlag(int $customerId, int $vehicleId, string $column): void
    {
        if (!in_array($column, ['is_default', 'is_selected'], true)) {
            throw new \LogicException('Unsupported garage flag.');
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->update($table, [$column => 0], ['customer_id = ?' => $customerId, $column . ' = ?' => 1]);
        $connection->update($table, [$column => 1], ['customer_id = ?' => $customerId, 'entity_id = ?' => $vehicleId]);
    }
}
