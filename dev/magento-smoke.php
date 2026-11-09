<?php
declare(strict_types=1);
ob_start();

// Run only against a disposable installation: this creates and removes test fixtures.
$magentoRoot = $argv[1] ?? '';
if (!is_file($magentoRoot . '/app/bootstrap.php') || ($argv[2] ?? '') !== '--disposable') {
    fwrite(STDERR, 'Usage: php dev/magento-smoke.php /path/to/magento --disposable' . PHP_EOL);
    exit(1);
}
require $magentoRoot . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objects = $bootstrap->getObjectManager();
$objects->get(\Magento\Framework\Registry::class)->register('isSecureArea', true);
$objects->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
$objects->get(\Magento\Store\Model\StoreManagerInterface::class)->setCurrentStore(1);
$resource = $objects->get(\Magento\Framework\App\ResourceConnection::class);
$customerRepo = $objects->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$productRepo = $objects->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$types = $objects->get(\Automotive\Garage\Model\ResourceModel\VehicleTypeStorage::class);
$garage = $objects->get(\Automotive\Garage\Model\Garage::class);
$fitment = $objects->get(\Automotive\Garage\Model\Fitment::class);
$prefix = 'garage-smoke-' . bin2hex(random_bytes(4));
$customerIds = $productIds = $typeIds = [];
$checks = 0;
$check = static function (bool $passed, string $message) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo 'PASS ' . $message . PHP_EOL;
};
try {
    foreach (['owner', 'other'] as $role) {
        $customer = $objects->get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class)->create();
        $customer->setWebsiteId(1)->setFirstname('Garage')->setLastname('Smoke')->setEmail($prefix . '-' . $role . '@example.test');
        $customerIds[] = (int) $customerRepo->save($customer)->getId();
    }
    $type = $types->save(['code' => $prefix, 'make' => 'Garage Smoke', 'model' => 'Ranger',
        'model_year' => '2020', 'variant' => $prefix, 'is_enabled' => '1']);
    $typeIds[] = $type->id;
    $vehicle = $garage->save($customerIds[0], ['registration' => 'AB12CDE', 'vehicle_type_id' => $type->id, 'make' => 'Spoof']);
    $check($vehicle->make === 'Garage Smoke' && $vehicle->vehicleTypeId === $type->id,
        'Magento DI and canonical vehicle resolution persist the expected values');
    $manual = $garage->save($customerIds[0], ['registration' => 'XY20ABC', 'make' => 'Toyota', 'model' => 'Hilux']);
    $storage = $objects->get(\Automotive\Garage\Api\VehicleStorageInterface::class);
    try {
        $storage->transaction($customerIds[0], static function () use ($storage, $customerIds, $manual): void {
            $storage->select($customerIds[0], $manual->id);
            throw new RuntimeException('Deliberate rollback');
        });
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Deliberate rollback') {
            throw $exception;
        }
    }
    $check($garage->getSelected($customerIds[0])?->id === $vehicle->id, 'MariaDB rollback restores the previous selected vehicle');
    try {
        $garage->delete($customerIds[1], $vehicle->id);
        throw new RuntimeException('Cross-customer deletion was allowed');
    } catch (\Automotive\Garage\Exception\GarageException) {
        $check(count($garage->getList($customerIds[0])) === 2, 'A different customer cannot delete the vehicle');
    }
    foreach (['matched', 'universal', 'unknown'] as $kind) {
        $product = $objects->get(\Magento\Catalog\Model\ProductFactory::class)->create();
        $attributeSet = (int) $objects->get(\Magento\Eav\Model\Config::class)->getEntityType('catalog_product')->getDefaultAttributeSetId();
        $product->setSku($prefix . '-' . $kind)->setUrlKey($prefix . '-' . $kind)->setName('Garage ' . $kind)->setTypeId('simple')
            ->setAttributeSetId($attributeSet)->setStatus(1)->setVisibility(4)->setPrice(25)->setWebsiteIds([1])
            ->setStockData(['is_in_stock' => 1, 'qty' => 10]);
        $productIds[] = (int) $productRepo->save($product)->getId();
    }
    $objects->get(\Magento\Framework\Indexer\IndexerRegistry::class)->get('cataloginventory_stock')->reindexList($productIds);
    $objects->get(\Magento\Framework\Indexer\IndexerRegistry::class)->get('catalog_product_price')->reindexList($productIds);
    $fitment->save($prefix . '-matched', $type->code, false);
    $fitment->save($prefix . '-universal', '', true);
    $collection = $objects->get(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class)->create();
    // Isolate mapping/count tests from the stock plugin; the actual block is checked below.
    $collection->setFlag('has_stock_status_filter', true);
    $collection->addIdFilter($productIds);
    $fitment->applyToCollection($collection, $type->id);
    $collection->setOrder('entity_id', 'ASC')->setPageSize(1)->setCurPage(1);
    $check($collection->getSize() === 2, 'Vehicle matching includes specific and universal products, excluding unknown products');
    $pageIds = array_map('intval', $collection->getColumnValues('entity_id'));
    $check($pageIds === [$productIds[0]],
        'The product count and first page are filtered in SQL (expected ' . $productIds[0] . ', got ' . implode(',', $pageIds) . ')');
    $objects->get(\Magento\Customer\Model\Session::class)->setCustomerDataAsLoggedIn($customerRepo->getById($customerIds[0]));
    $list = $objects->create(\Automotive\Garage\Block\Shop\ProductList::class);
    $storefrontProducts = $list->getLoadedProductCollection();
    $storefrontProducts->addIdFilter($productIds);
    $count = $storefrontProducts->getSize();
    $check($count === 2, 'The actual storefront block applies store, status, visibility and stock filters (count ' . $count . ')');
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, "code,make,model,model_year,variant,is_enabled\n" . $prefix . "-first,Garage Import,Ranger,2020," . $prefix . ",1\n" . $prefix . "-second,Garage Import,Ranger,2020," . $prefix . ",1\n");
    rewind($stream);
    try {
        $objects->get(\Automotive\Garage\Model\Csv\Importer::class)->importTypes($stream);
        throw new RuntimeException('Invalid CSV unexpectedly succeeded');
    } catch (\Automotive\Garage\Exception\GarageException) {
        $check($types->getByCode($prefix . '-first') === null, 'A duplicate vehicle definition rolls back the entire CSV import');
    } finally {
        fclose($stream);
    }
    echo $checks . ' Magento runtime checks passed.' . PHP_EOL;
} finally {
    foreach ($productIds as $id) {
        $productRepo->delete($productRepo->getById($id));
    }
    foreach ($customerIds as $id) {
        $customerRepo->deleteById($id);
    }
    $resource->getConnection()->delete($resource->getTableName('automotive_garage_vehicle_type'),
        ['code LIKE ?' => $prefix . '%']);
}
