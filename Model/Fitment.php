<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class Fitment
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductRepositoryInterface $products,
        private readonly VehicleTypeStorage $types
    ) {
    }

    public function describe(string $sku): array
    {
        $productId = $this->productId($sku);
        $db = $this->resource->getConnection();
        $rule = $db->fetchRow($db->select()->from($this->table('product_rule'))->where('product_id = ?', $productId));
        $codes = $db->fetchCol($db->select()->from(['f' => $this->table('product_fitment')], [])
            ->join(['t' => $this->table('vehicle_type')], 't.entity_id = f.vehicle_type_id', ['code'])
            ->where('f.product_id = ?', $productId)->order('t.code'));
        return ['sku' => $sku, 'product_id' => $productId, 'known' => (bool) $rule,
            'is_universal' => (bool) ($rule['is_universal'] ?? false),
            'universal_market' => $rule['universal_market'] ?? 'GB', 'codes' => $codes];
    }

    /** Resolve all input before starting a replace operation. */
    public function prepare(string $sku, mixed $codes, bool $universal, string $universalMarket = 'GB'): array
    {
        if (!in_array($universalMarket, ['GB', 'US', 'all'], true)) {
            throw new GarageException('Choose a valid universal product market.');
        }
        $ids = [];
        foreach (FitmentInput::codes($codes, $universal) as $code) {
            $type = $this->types->getByCode($code);
            if (!$type) {
                throw new GarageException('A vehicle code was not found. Create the vehicle definition first.');
            }
            $ids[] = $type->id;
        }
        return ['product_id' => $this->productId($sku), 'vehicle_type_ids' => $ids, 'is_universal' => $universal,
            'universal_market' => $universalMarket];
    }

    public function save(string $sku, mixed $codes, bool $universal, string $universalMarket = 'GB'): void
    {
        $this->savePrepared($this->prepare($sku, $codes, $universal, $universalMarket));
    }

    public function savePrepared(array $rule): void
    {
        $db = $this->resource->getConnection();
        $db->beginTransaction();
        try {
            $productId = (int) $rule['product_id'];
            $db->fetchOne($db->select()->from($this->resource->getTableName('catalog_product_entity'), ['entity_id'])
                ->where('entity_id = ?', $productId)->forUpdate(true));
            $db->delete($this->table('product_fitment'), ['product_id = ?' => $productId]);
            $db->insertOnDuplicate($this->table('product_rule'),
                ['product_id' => $productId, 'is_universal' => (int) $rule['is_universal'],
                    'universal_market' => $rule['universal_market'] ?? 'GB'], ['is_universal', 'universal_market']);
            $rows = array_map(static fn (int $id): array => ['product_id' => $productId, 'vehicle_type_id' => $id],
                $rule['vehicle_type_ids']);
            if ($rows !== []) {
                $db->insertMultiple($this->table('product_fitment'), $rows);
            }
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function clear(string $sku): void
    {
        $productId = $this->productId($sku);
        $db = $this->resource->getConnection();
        $db->beginTransaction();
        try {
            $db->fetchOne($db->select()->from($this->resource->getTableName('catalog_product_entity'), ['entity_id'])
                ->where('entity_id = ?', $productId)->forUpdate(true));
            $db->delete($this->table('product_fitment'), ['product_id = ?' => $productId]);
            $db->delete($this->table('product_rule'), ['product_id = ?' => $productId]);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    /** SQL filtering happens before Magento counts, sorts or paginates the collection. */
    public function applyToCollection(Collection $collection, int $vehicleTypeId): void
    {
        $type = $this->types->getEnabled($vehicleTypeId);
        if (!$type) {
            throw new GarageException('This vehicle definition is no longer available. Choose another vehicle.');
        }
        $db = $this->resource->getConnection();
        $universal = $db->select()->from(['r' => $this->table('product_rule')], ['product_id'])
            ->where('r.product_id = e.entity_id')->where('r.is_universal = ?', 1)
            ->where('r.universal_market IN (?)', ['all', $type->market]);
        $specific = $db->select()->from(['f' => $this->table('product_fitment')], ['product_id'])
            ->where('f.product_id = e.entity_id')->where('f.vehicle_type_id = ?', $vehicleTypeId);
        $collection->getSelect()->where('EXISTS (' . $universal . ') OR EXISTS (' . $specific . ')');
    }

    private function productId(string $sku): int
    {
        if ($sku === '' || strlen($sku) > 256 || preg_match('/[\x00-\x1F\x7F]/', $sku)) {
            throw new GarageException('Enter an existing product SKU.');
        }
        try {
            return (int) $this->products->get($sku)->getId();
        } catch (NoSuchEntityException $exception) {
            throw new GarageException('The product SKU could not be found.', 0, $exception);
        }
    }

    private function table(string $suffix): string
    {
        return $this->resource->getTableName('automotive_garage_' . $suffix);
    }
}
