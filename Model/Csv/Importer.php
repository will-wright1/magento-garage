<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\Csv;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Fitment;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Automotive\Garage\Model\VehicleTypeInput;
use Automotive\Garage\Model\Specification;
use Magento\Framework\App\ResourceConnection;

class Importer
{
    public function __construct(private readonly Reader $reader, private readonly VehicleTypeStorage $types,
        private readonly Fitment $fitment, private readonly ResourceConnection $resource)
    {
    }

    public function importTypes($stream): int
    {
        $rows = $this->reader->read($stream, ['code', 'make', 'model', 'model_year', 'variant', 'is_enabled'],
            array_merge(['market'], Specification::fields()));
        $validated = array_map(VehicleTypeInput::fromArray(...), $rows);
        $codes = array_column($validated, 'code');
        if (count($codes) !== count(array_unique($codes))) {
            throw new GarageException('A vehicle code appears more than once in the CSV.');
        }
        return $this->atomic(function () use ($validated): int {
            foreach ($validated as $row) {
                $this->types->save($row, $this->types->getByCode($row['code'])?->id);
            }
            return count($validated);
        });
    }

    public function importFitments($stream): int
    {
        $rows = $this->reader->read($stream, ['sku', 'vehicle_code', 'universal'], ['universal_market']);
        $groups = [];
        foreach ($rows as $row) {
            if (!in_array($row['universal'], ['0', '1'], true)) {
                throw new GarageException('The universal CSV column must contain 0 or 1.');
            }
            $sku = trim($row['sku']);
            $market = $row['universal_market'] ?? 'GB';
            if (isset($groups[$sku]) && $groups[$sku]['market'] !== $market) {
                throw new GarageException('A SKU mixes different universal market settings in the CSV.');
            }
            if (isset($groups[$sku]) && $groups[$sku]['universal'] !== ($row['universal'] === '1')) {
                throw new GarageException('A SKU mixes universal and vehicle-specific CSV rows.');
            }
            $groups[$sku]['universal'] = $row['universal'] === '1';
            $groups[$sku]['market'] = $market;
            $groups[$sku]['codes'][] = $row['vehicle_code'];
        }
        $prepared = [];
        foreach ($groups as $sku => $group) {
            $prepared[] = $this->fitment->prepare((string) $sku, implode(',', $group['codes']), $group['universal'], $group['market']);
        }
        $productIds = array_column($prepared, 'product_id');
        if (count($productIds) !== count(array_unique($productIds))) {
            throw new GarageException('The CSV uses multiple spellings of the same SKU. Use a single spelling for each product.');
        }
        usort($prepared, static fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);
        return $this->atomic(function () use ($prepared): int {
            foreach ($prepared as $rule) {
                $this->fitment->savePrepared($rule);
            }
            return count($prepared);
        });
    }

    private function atomic(callable $operation): int
    {
        $db = $this->resource->getConnection();
        $db->beginTransaction();
        try {
            $count = $operation();
            $db->commit();
            return $count;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
