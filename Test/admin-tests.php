<?php
declare(strict_types=1);

use Automotive\Garage\Model\Adminhtml\CustomerGarage;
use Automotive\Garage\Model\Adminhtml\GarageFilter;

$tests['admin list filters reject compound values and bound pagination and query length'] = static function (): void {
    $filter = new GarageFilter(['q' => ['bad'], 'market' => ['US'], 'website_id' => ['1'], 'p' => '-2']);
    same('', $filter->query);
    same('', $filter->market);
    same(null, $filter->websiteId);
    same(1, $filter->page);
    $filter = new GarageFilter(['q' => '  Ranger  ', 'market' => 'US', 'website_id' => '2', 'p' => '3']);
    same(['q' => 'Ranger', 'market' => 'US', 'website_id' => 2, 'p' => 4], $filter->params(4));
    same(100, mb_strlen((new GarageFilter(['q' => str_repeat('é', 150)]))->query, 'UTF-8'));
    same(1, (new GarageFilter(['p' => '1000001']))->page);
};
$tests['admin form whitelist cannot submit owner flags or internal catalogue IDs'] = static function (): void {
    same(['make' => 'Ford', 'vehicle_code' => 'ranger-us'], CustomerGarage::formData([
        'customer_id' => 99, 'entity_id' => 20, 'is_default' => 1, 'is_selected' => 1, 'vehicle_type_id' => 123,
        'operation' => 'delete', 'make' => 'Ford', 'vehicle_code' => 'ranger-us',
    ]));
    foreach ([null, '', '0', '-1', '1.1', [], ['1'], 'unknown'] as $id) {
        rejects(static fn () => CustomerGarage::positiveId($id));
    }
    same(42, CustomerGarage::positiveId('42'));
};
