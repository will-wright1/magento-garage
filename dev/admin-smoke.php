<?php
declare(strict_types=1);
ob_start();

// Synthetic fixtures only. Never run this against a merchant database.
$root = $argv[1] ?? '';
if (!is_file($root . '/app/bootstrap.php') || ($argv[2] ?? '') !== '--disposable') {
    fwrite(STDERR, "Usage: php dev/admin-smoke.php /path/to/magento --disposable\n");
    exit(1);
}
require $root . '/app/bootstrap.php';
$objects = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$objects->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
$objects->configure($objects->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class)->load('adminhtml'));
$objects->get(\Magento\Framework\Registry::class)->register('isSecureArea', true);
$stores = $objects->get(\Magento\Store\Model\StoreManagerInterface::class);
$stores->setCurrentStore(0);
$resource = $objects->get(\Magento\Framework\App\ResourceConnection::class);
$db = $resource->getConnection();
$customers = $objects->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$management = $objects->get(\Automotive\Garage\Model\Adminhtml\CustomerGarage::class);
$listing = $objects->get(\Automotive\Garage\Model\ResourceModel\CustomerGarageListing::class);
$types = $objects->get(\Automotive\Garage\Model\ResourceModel\VehicleTypeStorage::class);
$prefix = 'garage-admin-' . bin2hex(random_bytes(4));
$ids = [];
$store = null;
$paths = ['automotive_garage/general/default_market' => 'US', 'automotive_garage/general/default_country' => 'US',
    'automotive_garage/general/plate_requirement' => 'required'];
$count = 0;
$check = static function (bool $passed, string $message) use (&$count): void {
    if (!$passed) { throw new RuntimeException($message); }
    $count++; echo 'PASS ' . $message . PHP_EOL;
};
$reject = static function (callable $operation) use ($check): void {
    try { $operation(); } catch (\Automotive\Garage\Exception\GarageException) { return; }
    throw new RuntimeException('Invalid admin operation was accepted');
};
$render = static function (string $class, string $template, \Magento\Framework\AuthorizationInterface $auth) use ($objects): string {
    $layout = $objects->create(\Magento\Framework\View\LayoutInterface::class);
    $block = $objects->create($class, ['authorization' => $auth]);
    $block->setLayout($layout)->setTemplate($template);
    return $block->toHtml();
};
$auth = new class implements \Magento\Framework\AuthorizationInterface {
    public bool $manage = false;
    public bool $view = true;
    public function isAllowed($resource, $privilege = null) {
        return $resource === 'Automotive_Garage::customer_view' ? $this->view
            : ($resource === 'Automotive_Garage::customer_manage' && $this->manage);
    }
};
try {
    $store = $objects->get(\Magento\Store\Model\StoreFactory::class)->create();
    $store->setCode('garage_admin_' . bin2hex(random_bytes(4)))->setWebsiteId(1)
        ->setGroupId($stores->getStore(1)->getStoreGroupId())->setName('Disposable admin US view')->setIsActive(1);
    $objects->get(\Magento\Store\Model\ResourceModel\Store::class)->save($store);
    $writer = $objects->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
    foreach ($paths as $path => $value) { $writer->save($path, $value, 'stores', $store->getId()); }
    $objects->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
    $stores->reinitStores();
    foreach ([1, (int) $store->getId()] as $index => $storeId) {
        $customer = $objects->get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class)->create();
        $customer->setWebsiteId(1)->setStoreId($storeId)->setFirstname('Admin')->setLastname('Fixture')
            ->setEmail($prefix . '-' . $index . '@example.test');
        $ids[] = (int) $customers->save($customer)->getId();
    }
    [$owner, $other] = $ids;
    $base = ['operation' => 'save', 'registration' => 'AB12CDE', 'make' => $prefix, 'model' => 'Ranger', 'nickname' => '<script>20% discount</script>'];
    $management->execute($owner, $base + ['customer_id' => $other, 'is_default' => 0, 'is_selected' => 0]);
    $garage = $management->garage($management->customer($owner));
    $first = $garage->getList($owner)[0];
    $check($first->isDefault && $first->isSelected && $first->market === 'GB' && $garage->getList($other) === [],
        'Admin add resolves the actual owner, applies UK store defaults and ignores posted ownership/preferences');
    $us = ['operation' => 'save', 'make' => $prefix, 'model' => 'F-150', 'model_year' => '2024'];
    $reject(static fn () => $management->execute($other, $us));
    $check($management->settings($management->customer($other))->defaultMarket() === 'US',
        'Admin uses the customer store view rather than admin/default store configuration');
    $writer->save('automotive_garage/general/plate_requirement', 'optional', 'stores', $store->getId());
    $objects->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
    $management->execute($other, $us + ['vin' => '1M8GDM9AXKP042788']);
    $otherGarage = $management->garage($management->customer($other));
    $second = $otherGarage->getList($other)[0];
    $check($second->market === 'US' && $second->registration === '', 'US admin entry permits a plate-free vehicle under the customer store policy');
    foreach (['delete', 'select', 'default', 'save'] as $operation) {
        $reject(static fn () => $management->execute($owner, ['operation' => $operation, 'vehicle_id' => $second->id] + $base));
    }
    $check(count($otherGarage->getList($other)) === 1, 'Every admin mutation rejects a vehicle owned by another customer');
    $type = $types->save(['code' => $prefix, 'market' => 'GB', 'make' => $prefix, 'model' => 'Canonical', 'model_year' => '2020']);
    $management->execute($owner, ['vehicle_id' => $first->id, 'vehicle_code' => $type->code] + $base);
    $check($garage->get($owner, $first->id)->model === 'Canonical' && $garage->get($owner, $first->id)->isDefault,
        'Admin edits resolve canonical details by code and preserve saved identity and preferences');
    $reject(static fn () => $management->execute($owner, ['vehicle_code' => 'unknown'] + $base));
    $reject(static fn () => $management->execute(2147483647, $base));
    $management->execute($owner, ['vehicle_id' => $first->id] + $base);
    $check($garage->get($owner, $first->id)->vehicleTypeId === null, 'Blank admin catalogue code explicitly returns a saved vehicle to manual entry');
    $filterClass = \Automotive\Garage\Model\Adminhtml\GarageFilter::class;
    $found = $listing->search(new $filterClass(['q' => $prefix]));
    $check($found['total'] === 2 && !array_key_exists('vin', $found['rows'][0]), 'Admin query finds both customer garages without projecting full VINs');
    $check($listing->search(new $filterClass(['q' => 'Admin Fixture']))['total'] === 2,
        'Admin search accepts a full customer name across first and last name columns');
    $check($listing->search(new $filterClass(['q' => '20% discount']))['total'] === 1
        && $listing->search(new $filterClass(['q' => "%' OR 1=1 --"]))['total'] === 0,
        'Search treats SQL metacharacters and wildcard characters literally');
    $check($listing->search(new $filterClass(['q' => $prefix, 'market' => 'US']))['total'] === 1
        && $listing->search(new $filterClass(['q' => $prefix, 'website_id' => 2147483647]))['total'] === 0,
        'Market and website filters apply before counts and pagination');
    $request = $objects->get(\Magento\Framework\App\Request\Http::class);
    $request->setParams(['customer_id' => $other, 'vehicle_id' => $second->id]);
    $html = $render(\Automotive\Garage\Block\Adminhtml\CustomerGarage::class, 'Automotive_Garage::customer/view.phtml', $auth);
    $check(str_contains($html, 'read-only') && str_contains($html, '2788') && !str_contains($html, $second->vin)
        && !str_contains($html, '<form') && !str_contains($html, 'Edit vehicle'),
        'Actual read-only admin template omits mutation forms and full VIN even on a crafted edit URL');
    $auth->manage = true;
    $html = $render(\Automotive\Garage\Block\Adminhtml\CustomerGarage::class, 'Automotive_Garage::customer/view.phtml', $auth);
    $check(str_contains($html, $second->vin) && str_contains($html, 'name="form_key"') && str_contains($html, 'method="post"'),
        'Managers can edit the full VIN through a protected POST form');
    $request->setParams(['customer_id' => $owner, 'vehicle_id' => $second->id]);
    $html = $render(\Automotive\Garage\Block\Adminhtml\CustomerGarage::class, 'Automotive_Garage::customer/view.phtml', $auth);
    $check(str_contains($html, 'could not be found in this customer') && !str_contains($html, $second->vin)
        && !str_contains($html, '<script>20%'), 'Crafted cross-customer edit URLs reveal no foreign fields and customer text is escaped');
    $registry = $objects->get(\Magento\Framework\Registry::class);
    $registry->register(\Magento\Customer\Controller\RegistryConstants::CURRENT_CUSTOMER_ID, $owner);
    $html = $render(\Automotive\Garage\Block\Adminhtml\Customer\GarageTab::class, 'Automotive_Garage::customer/tab.phtml', $auth);
    $check(str_contains($html, 'Open customer garage') && str_contains($html, 'AB12CDE') && !str_contains($html, '<form'),
        'Customer edit tab renders a garage summary without nesting mutation forms in the customer form');
    $auth->view = false;
    $check($render(\Automotive\Garage\Block\Adminhtml\Customer\GarageTab::class, 'Automotive_Garage::customer/tab.phtml', $auth) === '',
        'Customer tab emits no garage data without its view permission');
    $acl = $objects->get(\Magento\Framework\Acl\Builder::class)->getAcl();
    $roleClass = class_exists(\Laminas\Permissions\Acl\Role\GenericRole::class) ? \Laminas\Permissions\Acl\Role\GenericRole::class : \Zend_Acl_Role::class;
    $acl->addRole(new $roleClass($prefix));
    $acl->allow($prefix, 'Automotive_Garage::customer_view');
    $check($acl->isAllowed($prefix, 'Automotive_Garage::customer_view') && !$acl->isAllowed($prefix, 'Automotive_Garage::customer_manage')
        && !$acl->isAllowed($prefix, 'Automotive_Garage::manage'), 'Real Magento ACL grants garage viewing independently of customer writes and catalogue management');
    $auth->view = true;
    $auth->manage = false;
    $context = $objects->create(\Magento\Backend\App\Action\Context::class, ['authorization' => $auth]);
    $saveController = $objects->create(\Automotive\Garage\Controller\Adminhtml\Customer\Save::class, ['context' => $context]);
    $viewController = $objects->create(\Automotive\Garage\Controller\Adminhtml\Customer\View::class, ['context' => $context]);
    $permission = new ReflectionMethod(\Magento\Backend\App\AbstractAction::class, '_isAllowed');
    $check($permission->invoke($viewController) && !$permission->invoke($saveController),
        'Core backend controller authorization permits a viewer to read and rejects direct mutation access');
    // Add enough synthetic rows to check stable paging in the real SQL projection.
    for ($i = 0; $i < 51; $i++) {
        $db->insert($resource->getTableName('automotive_garage_vehicle'), ['customer_id' => $other, 'make' => $prefix,
            'model' => 'Paging', 'registration' => null, 'vehicle_market' => 'US', 'registration_country' => 'US']);
    }
    $page1 = $listing->search(new $filterClass(['q' => $prefix]));
    $page2 = $listing->search(new $filterClass(['q' => $prefix, 'p' => '2']));
    $check($page1['total'] === 53 && count($page1['rows']) === 50 && count($page2['rows']) === 3
        && !array_intersect(array_column($page1['rows'], 'entity_id'), array_column($page2['rows'], 'entity_id')),
        'Admin listing paginates in SQL with stable distinct pages and accurate totals');
    $management->execute($owner, ['registration' => 'XY21ABC'] + $base);
    $last = $garage->getList($owner)[1];
    $management->execute($owner, ['operation' => 'default', 'vehicle_id' => $last->id]);
    $management->execute($owner, ['operation' => 'select', 'vehicle_id' => $last->id]);
    $management->execute($owner, ['operation' => 'delete', 'vehicle_id' => $last->id]);
    $check($garage->get($owner, $first->id)->isDefault && $garage->get($owner, $first->id)->isSelected,
        'Admin default/select/delete actions reuse atomic customer preference fallback');
    // Core dispatch enforces role ACL; the explicit execute guard also rejects CSRF and GET.
    $before = $garage->getList($owner)[0]->toArray();
    $request->setMethod('POST')->setPostValue(['customer_id' => $owner, 'vehicle_id' => $first->id, 'operation' => 'delete', 'form_key' => 'invalid']);
    $saveController->execute();
    $request->setMethod('GET');
    $saveController->execute();
    $check($garage->get($owner, $first->id)->toArray() === $before, 'Admin save controller rejects invalid form keys and GET without changing rows');
    echo $count . ' admin runtime checks passed.' . PHP_EOL;
} finally {
    foreach ($ids as $id) { $customers->deleteById($id); }
    $db->delete($resource->getTableName('automotive_garage_vehicle_type'), ['code = ?' => $prefix]);
    if ($store !== null && $store->getId()) {
        $writer = $objects->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        foreach ($paths as $path => $value) { $writer->delete($path, 'stores', $store->getId()); }
        $objects->get(\Magento\Store\Model\ResourceModel\Store::class)->delete($store);
    }
    $objects->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
}
