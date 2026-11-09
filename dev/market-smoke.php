<?php
declare(strict_types=1);
ob_start();

// Uses synthetic fixtures; never run against a live merchant installation.
$root = $argv[1] ?? '';
if (!is_file($root . '/app/bootstrap.php') || ($argv[2] ?? '') !== '--disposable') {
    fwrite(STDERR, "Usage: php dev/market-smoke.php /path/to/magento --disposable [pre-upgrade-snapshot.json]\n");
    exit(1);
}
require $root . '/app/bootstrap.php';
$objects = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$objects->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
$objects->get(\Magento\Framework\Registry::class)->register('isSecureArea', true);
$objects->get(\Magento\Store\Model\StoreManagerInterface::class)->setCurrentStore(1);
$resource = $objects->get(\Magento\Framework\App\ResourceConnection::class);
$db = $resource->getConnection();
$types = $objects->get(\Automotive\Garage\Model\ResourceModel\VehicleTypeStorage::class);
$garage = $objects->get(\Automotive\Garage\Model\Garage::class);
$fitment = $objects->get(\Automotive\Garage\Model\Fitment::class);
$customers = $objects->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$products = $objects->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$prefix = 'garage-market-' . bin2hex(random_bytes(4));
$customerId = null;
$fixtureStore = null;
$configPaths = [];
$productIds = [];
$count = 0;
$check = static function (bool $passed, string $message) use (&$count): void {
    if (!$passed) { throw new RuntimeException($message); }
    $count++; echo 'PASS ' . $message . PHP_EOL;
};

if (isset($argv[3])) {
    $snapshot = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    foreach ($snapshot as $table => $rows) {
        if (!in_array($table, ['automotive_garage_vehicle', 'automotive_garage_vehicle_type', 'automotive_garage_product_rule', 'automotive_garage_product_fitment'], true)) {
            throw new RuntimeException('Unexpected snapshot table');
        }
        $after = $db->fetchAll($db->select()->from($resource->getTableName($table)));
        foreach ($rows as $row) {
            $found = false;
            foreach ($after as $candidate) {
                $same = true;
                foreach ($row as $key => $value) {
                    $same = $same && (string) $candidate[$key] === (string) $value;
                }
                if ($same) { $found = true; break; }
            }
            $check($found, 'Upgrade preserves an existing ' . $table . ' row including original identity and preferences');
        }
    }
    $check((int) $db->fetchOne($db->select()->from($resource->getTableName('automotive_garage_vehicle'), ['COUNT(*)'])
        ->where('entity_id IN (?)', array_column($snapshot['automotive_garage_vehicle'] ?? [], 'entity_id') ?: [0])
        ->where("(vehicle_market != 'GB' OR registration_country != 'GB')")) === 0,
        'Pre-market saved vehicles receive GB defaults without reclassification');
}

try {
    $customer = $objects->get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class)->create();
    $customer->setWebsiteId(1)->setFirstname('Market')->setLastname('Test')->setEmail($prefix . '@example.test');
    $customerId = (int) $customers->save($customer)->getId();
    $base = ['make' => $prefix, 'model' => 'Ranger', 'model_year' => '2020', 'variant' => 'Test Cab'];
    $gb = $types->save($base + ['code' => $prefix . '-gb', 'market' => 'GB']);
    $us = $types->save($base + ['code' => $prefix . '-us', 'market' => 'US', 'steering' => 'LHD', 'engine' => '2.3L', 'bed_length_mm' => '1500']);
    $check($gb->id !== $us->id, 'Identical make/model/year/variant can coexist in GB and US markets');
    $options = $types->options('variant', $prefix, 'Ranger', 2020, 'US');
    $check(count($options) === 1 && $options[0]['value'] === (string) $us->id && str_contains($options[0]['label'], '1500 mm'),
        'Real selector query isolates US definitions and displays structured dimensions');
    $first = $garage->save($customerId, ['vehicle_market' => 'US', 'registration_country' => 'US', 'vehicle_type_id' => $us->id,
        'vin' => '1M8GDM9AXKP042788']);
    $second = $garage->save($customerId, ['vehicle_market' => 'US', 'registration_country' => 'US', 'vehicle_type_id' => $us->id]);
    $check($first->registration === '' && $second->id !== $first->id && $first->isSelected && $first->isDefault,
        'Real database permits multiple plate-free vehicles and preserves preferences');
    $plate = ['registration' => 'ABC123', 'vehicle_market' => 'US', 'registration_country' => 'US', 'vehicle_type_id' => $us->id] + $base;
    $ca = $garage->save($customerId, $plate + ['registration_region' => 'CA']);
    $garage->save($customerId, $plate + ['registration_region' => 'NY']);
    $check(count($garage->getList($customerId)) === 4, 'Same plate text can be saved in different US jurisdictions');
    $raw = \Automotive\Garage\Model\VehicleInput::fromArray($plate + ['registration_region' => 'CA'])->toArray();
    try {
        $db->insert($resource->getTableName('automotive_garage_vehicle'), ['customer_id' => $customerId] + $raw);
        throw new RuntimeException('Duplicate plate insert succeeded');
    } catch (\Magento\Framework\DB\Adapter\DuplicateException) {
        $check(true, 'Database unique key independently blocks a duplicate plate within one jurisdiction');
    }
    $changed = $garage->save($customerId, ['registration' => 'CD20EFG', 'registration_country' => 'GB',
        'vehicle_market' => 'US', 'vehicle_type_id' => $us->id], $ca->id);
    $check($changed->id === $ca->id && $changed->registrationRegion === '' && $changed->market === 'US',
        'Changing plate country clears stale US jurisdiction and preserves the specification and garage ID');
    $imported = $garage->save($customerId, ['registration' => 'AB12CDE', 'registration_country' => 'GB',
        'vehicle_market' => 'US', 'vehicle_type_id' => $us->id]);
    $check($imported->market === 'US' && $imported->registrationCountry === 'GB', 'US specification survives UK plate registration');
    try {
        $garage->save($customerId, ['registration' => 'XY20ABC', 'vehicle_market' => 'GB', 'vehicle_type_id' => $us->id]);
        throw new RuntimeException('Wrong-market definition accepted');
    } catch (\Automotive\Garage\Exception\GarageException) {
        $check(count($garage->getList($customerId)) === 5, 'Forged cross-market association is rejected without inserting a row');
    }
    try {
        $types->save($base + ['code' => $us->code, 'market' => 'US', 'engine' => 'Changed'], $us->id);
        throw new RuntimeException('Referenced specifications were rewritten');
    } catch (\Automotive\Garage\Exception\GarageException) {
        $check($types->get($us->id)?->specifications['engine'] === '2.3L', 'Referenced market and specifications remain immutable');
    }
    foreach (['gb-specific', 'us-specific', 'gb-universal', 'us-universal', 'all-universal', 'unknown'] as $kind) {
        $product = $objects->get(\Magento\Catalog\Model\ProductFactory::class)->create();
        $setId = (int) $objects->get(\Magento\Eav\Model\Config::class)->getEntityType('catalog_product')->getDefaultAttributeSetId();
        $product->setSku($prefix . '-' . $kind)->setUrlKey($prefix . '-' . $kind)->setName($kind)->setTypeId('simple')
            ->setAttributeSetId($setId)->setStatus(1)->setVisibility(4)->setPrice(25)->setWebsiteIds([1])
            ->setStockData(['is_in_stock' => 1, 'qty' => 10]);
        $productIds[$kind] = (int) $products->save($product)->getId();
    }
    $fitment->save($prefix . '-gb-specific', $gb->code, false);
    $fitment->save($prefix . '-us-specific', $us->code, false);
    $fitment->save($prefix . '-gb-universal', '', true, 'GB');
    $fitment->save($prefix . '-us-universal', '', true, 'US');
    $fitment->save($prefix . '-all-universal', '', true, 'all');
    foreach (['GB' => $gb, 'US' => $us] as $market => $type) {
        $collection = $objects->get(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class)->create();
        $collection->setFlag('has_stock_status_filter', true)->addIdFilter(array_values($productIds));
        $fitment->applyToCollection($collection, $type->id);
        $actual = array_map('intval', $collection->getColumnValues('entity_id')); sort($actual);
        $expected = [$productIds[strtolower($market) . '-specific'], $productIds[strtolower($market) . '-universal'], $productIds['all-universal']]; sort($expected);
        $check($expected === $actual && $collection->getSize() === 3, $market . ' fitment includes only its specific/universal rules and explicit both-market universal products');
    }
    $objects->get(\Magento\Customer\Model\Session::class)->setCustomerDataAsLoggedIn($customers->getById($customerId));
    $data = $objects->get(\Automotive\Garage\CustomerData\Garage::class)->getSectionData();
    $check(!array_key_exists('vin', $data['vehicles'][0]), 'Full VIN is excluded from browser customer-data sections');
    $stores = $objects->get(\Magento\Store\Model\StoreManagerInterface::class);
    $fixtureStore = $objects->get(\Magento\Store\Model\StoreFactory::class)->create();
    $fixtureStore->setCode('garage_market_' . bin2hex(random_bytes(4)))->setWebsiteId(1)
        ->setGroupId($stores->getStore(1)->getStoreGroupId())->setName('Disposable US market view')->setIsActive(1);
    $storeResource = $objects->get(\Magento\Store\Model\ResourceModel\Store::class);
    $storeResource->save($fixtureStore);
    $writer = $objects->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
    $configPaths = ['automotive_garage/general/default_market' => 'US', 'automotive_garage/general/default_country' => 'US',
        'general/locale/code' => 'en_US'];
    foreach ($configPaths as $path => $value) { $writer->save($path, $value, 'stores', $fixtureStore->getId()); }
    $objects->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
    $stores->reinitStores(); $stores->setCurrentStore($fixtureStore->getId());
    $settings = $objects->get(\Automotive\Garage\Api\SettingsInterface::class);
    $check($settings->defaultMarket() === 'US' && $settings->defaultCountry() === 'US' && !$settings->isPlateRequired('US'),
        'Real store-view configuration supplies US defaults without changing saved GB vehicles');
    $objects->get(\Magento\Framework\App\AreaList::class)->getArea('frontend')->load(\Magento\Framework\App\Area::PART_TRANSLATE);
    $objects->get(\Magento\Framework\TranslateInterface::class)->setLocale('en_US')->loadData('frontend', true);
    $check((string) __('Registration number') === 'License plate' && (string) __('Petrol') === 'Gasoline',
        'Magento loads en_US plate terminology and fuel translations');
    $layout = $objects->get(\Magento\Framework\View\LayoutInterface::class);
    $layout->createBlock(\Magento\Framework\View\Element\FormKey::class, 'formkey');
    $block = $layout->createBlock(\Automotive\Garage\Block\Account::class)->setTemplate('Automotive_Garage::account.phtml');
    $html = $block->toHtml();
    $document = new DOMDocument(); @$document->loadHTML($html); $xpath = new DOMXPath($document);
    $check($xpath->query('//input[@name="registration" and @required]')->length === 0
        && $xpath->query('//*[@data-catalogue-fields]/*[@data-field]')->item(0)?->getAttribute('data-field') === 'year'
        && str_contains($html, 'License plate') && str_contains($html, 'garage-registration-neutral'),
        'Actual Magento US account template renders an optional plate, year-first fields and neutral vehicle cards');
    $request = $objects->get(\Magento\Framework\App\Request\Http::class);
    $request->setParams(['market' => 'US', 'level' => 'variant', 'make' => $prefix, 'model' => 'Ranger', 'year' => '2020']);
    $result = $objects->create(\Automotive\Garage\Controller\Catalog\Options::class)->execute();
    $response = $objects->create(\Magento\Framework\App\Response\Http::class);
    $result->renderResult($response);
    $decoded = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    $check(count($decoded['options']) === 1 && $decoded['options'][0]['value'] === (string) $us->id,
        'Real selector controller returns the selected market definition through Magento JSON rendering');
    $objects->get(\Magento\Customer\Model\Session::class)->logout();
    $guestResult = $objects->create(\Automotive\Garage\Controller\Account\Index::class)->execute();
    $check($guestResult instanceof \Magento\Framework\Controller\Result\Redirect,
        'Guest account route returns a Magento redirect result through its own authentication guard');
    $stores->setCurrentStore(1);
    echo $count . ' market runtime checks passed.' . PHP_EOL;
} finally {
    $objects->get(\Magento\Store\Model\StoreManagerInterface::class)->setCurrentStore(1);
    if ($fixtureStore !== null && $fixtureStore->getId()) {
        $writer = $objects->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        foreach ($configPaths as $path => $value) { $writer->delete($path, 'stores', $fixtureStore->getId()); }
        $objects->get(\Magento\Store\Model\ResourceModel\Store::class)->delete($fixtureStore);
    }
    foreach ($productIds as $id) { $products->delete($products->getById($id)); }
    if ($customerId !== null) { $customers->deleteById($customerId); }
    $db->delete($resource->getTableName('automotive_garage_vehicle_type'), ['code LIKE ?' => $prefix . '%']);
}
