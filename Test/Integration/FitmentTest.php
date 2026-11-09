<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Integration;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Csv\Importer;
use Automotive\Garage\Model\Fitment;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class FitmentTest extends TestCase
{
    /** @magentoDbIsolation enabled */
    public function testMatchingAndUniversalProductsAreFilteredBeforePagination(): void
    {
        $objects = Bootstrap::getObjectManager();
        $types = $objects->get(VehicleTypeStorage::class);
        $type = $types->save($this->type('ranger-2020', 2020));
        $other = $types->save($this->type('ranger-2021', 2021));
        $fitment = $objects->get(Fitment::class);
        $matched = $this->product('garage-matched');
        $universal = $this->product('garage-universal');
        $this->product('garage-unknown');
        $this->product('garage-wrong-year');
        $fitment->save('garage-matched', $type->code . ',' . $type->code, false);
        $fitment->save('garage-universal', '', true);
        $fitment->save('garage-wrong-year', $other->code, false);
        $collection = $objects->get(CollectionFactory::class)->create();
        $collection->setFlag('has_stock_status_filter', true);
        $fitment->applyToCollection($collection, $type->id);
        $collection->setOrder('entity_id', 'ASC')->setPageSize(1)->setCurPage(1);
        self::assertSame(2, $collection->getSize());
        self::assertSame([$matched], array_map('intval', $collection->getColumnValues('entity_id')));
        $pageTwo = $objects->get(CollectionFactory::class)->create();
        $pageTwo->setFlag('has_stock_status_filter', true);
        $fitment->applyToCollection($pageTwo, $type->id);
        $pageTwo->setOrder('entity_id', 'ASC')->setPageSize(1)->setCurPage(2);
        self::assertSame([$universal], array_map('intval', $pageTwo->getColumnValues('entity_id')));
    }

    /** @magentoDbIsolation enabled */
    public function testMappedVehicleDefinitionsCannotBeSilentlyRewritten(): void
    {
        $objects = Bootstrap::getObjectManager();
        $types = $objects->get(VehicleTypeStorage::class);
        $type = $types->save($this->type('ranger-2020', 2020));
        $this->product('garage-mapped');
        $objects->get(Fitment::class)->save('garage-mapped', $type->code, false);
        $types->save($this->type('ranger-2020', 2020) + ['is_enabled' => '0'], $type->id);
        self::assertNull($types->getEnabled($type->id));
        $this->expectException(GarageException::class);
        $types->save($this->type('ranger-2020', 2021), $type->id);
    }

    /** @magentoDbIsolation disabled */
    public function testFailedVehicleCsvImportLeavesNoPartialRecords(): void
    {
        $objects = Bootstrap::getObjectManager();
        $types = $objects->get(VehicleTypeStorage::class);
        $stream = fopen('php://memory', 'w+');
        // This checks a real rollback with no enclosing test transaction.
        fwrite($stream, "code,make,model,model_year,variant,is_enabled\ngarage-rollback-first,Garage Import Test,Model,2020,Double Cab,1\ngarage-rollback-second,Garage Import Test,Model,2020,Double Cab,1\n");
        rewind($stream);
        try {
            $objects->get(Importer::class)->importTypes($stream);
            self::fail('Duplicate vehicle definitions must roll back the file.');
        } catch (GarageException) {
            self::assertNull($types->getByCode('garage-rollback-first'));
            self::assertNull($types->getByCode('garage-rollback-second'));
        } finally {
            fclose($stream);
        }
    }

    private function type(string $code, int $year): array
    {
        return ['code' => $code, 'make' => 'Ford', 'model' => 'Ranger', 'model_year' => (string) $year, 'variant' => 'Double Cab'];
    }

    private function product(string $sku): int
    {
        $objects = Bootstrap::getObjectManager();
        $product = $objects->get(ProductFactory::class)->create();
        $website = (int) $objects->get(StoreManagerInterface::class)->getStore()->getWebsiteId();
        $attributeSet = (int) $objects->get(Config::class)->getEntityType('catalog_product')->getDefaultAttributeSetId();
        $product->setSku($sku)->setName($sku)->setTypeId('simple')->setAttributeSetId($attributeSet)
            ->setStatus(1)->setVisibility(4)->setPrice(10)->setWebsiteIds([$website]);
        return (int) $objects->get(ProductRepositoryInterface::class)->save($product)->getId();
    }
}
