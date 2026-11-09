<?php
declare(strict_types=1);

use Automotive\Garage\Model\Csv\Reader;
use Automotive\Garage\Model\FitmentInput;
use Automotive\Garage\Model\Garage;
use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Model\VehicleType;
use Automotive\Garage\Model\VehicleTypeInput;
use Automotive\Garage\Test\Support\InMemoryStorage;
use Automotive\Garage\Test\Support\InMemoryTypes;
use Automotive\Garage\Test\Support\TemplateRenderer;

$tests['catalogue selections resolve canonical details rather than trusting submitted labels'] = static function (): void {
    $type = new VehicleType(7, 'ford-ranger-2020-double', 'Ford', 'Ranger', 2020, 'Double Cab', true);
    $garage = new Garage(new InMemoryStorage(), 50, new InMemoryTypes([$type]));
    $vehicle = $garage->save(1, ['registration' => 'AB12 CDE', 'vehicle_type_id' => '7',
        'make' => 'Incorrect', 'model' => 'Incorrect', 'model_year' => '2025', 'variant' => 'Incorrect']);
    same('Ford', $vehicle->make);
    same('Ranger', $vehicle->model);
    same(2020, $vehicle->modelYear);
    same('Double Cab', $vehicle->variant);
    same(7, $vehicle->vehicleTypeId);
};
$tests['unknown disabled malformed and unconfigured catalogue IDs cannot be saved'] = static function (): void {
    $types = new InMemoryTypes([new VehicleType(7, 'disabled', 'Ford', 'Ranger', 2020, '', false)]);
    $garage = new Garage(new InMemoryStorage(), 50, $types);
    foreach (['7', '8', '-1', 'garbage', ['7']] as $id) {
        rejects(static fn () => $garage->save(1, details() + ['vehicle_type_id' => $id]));
    }
    rejects(static fn () => (new Garage(new InMemoryStorage()))->save(1, details() + ['vehicle_type_id' => 7]));
    same([], $garage->getList(1));
};
$tests['existing free-text vehicles remain saved and can be matched without changing identity or preferences'] = static function (): void {
    $storage = new InMemoryStorage();
    $garage = new Garage($storage);
    $legacy = $garage->save(1, details());
    $next = new Garage($storage, 50, new InMemoryTypes([new VehicleType(7, 'ranger', 'Ford', 'Ranger', 2020, 'Double Cab', true)]));
    same(null, $next->get(1, $legacy->id)->vehicleTypeId);
    $matched = $next->save(1, ['registration' => 'AB12CDE', 'vehicle_type_id' => '7'], $legacy->id);
    same($legacy->id, $matched->id);
    same(7, $matched->vehicleTypeId);
    choices($next, 1, $legacy->id, $legacy->id);
};
$tests['returning to manual entry removes the fitment association instead of retaining a stale vehicle type'] = static function (): void {
    $garage = new Garage(new InMemoryStorage(), 50,
        new InMemoryTypes([new VehicleType(7, 'ranger', 'Ford', 'Ranger', 2020, 'Double Cab', true)]));
    $vehicle = $garage->save(1, ['registration' => 'AB12CDE', 'vehicle_type_id' => '7']);
    $manual = $garage->save(1, ['registration' => 'AB12CDE', 'make' => 'Toyota', 'model' => 'Hilux',
        'vehicle_type_id' => '', 'model_year' => '2021', 'variant' => 'Extra Cab'], $vehicle->id);
    same(null, $manual->vehicleTypeId);
    same('Toyota', $manual->make);
    same(2021, $manual->modelYear);
};
$tests['upgraded vehicle DTOs can read legacy rows without new columns'] = static function (): void {
    $legacy = Vehicle::fromRow(['entity_id' => 1, 'registration' => 'AB12CDE', 'make' => 'Ford', 'model' => 'Ranger',
        'nickname' => '', 'is_default' => 1, 'is_selected' => 1]);
    same(null, $legacy->vehicleTypeId);
    same(null, $legacy->modelYear);
    same('', $legacy->variant);
};
$tests['vehicle definitions validate stable codes years Unicode and enabled state'] = static function (): void {
    $input = ['code' => '  FORD-2020 ', 'make' => ' Ford ', 'model' => ' Ranger ',
        'model_year' => '2020', 'variant' => ' Double  Cab ', 'is_enabled' => '1'];
    $data = VehicleTypeInput::fromArray($input);
    same('ford-2020', $data['code']);
    same('Double Cab', $data['variant']);
    same(2020, $data['model_year']);
    foreach ([['model_year' => '0'], ['code' => '../escape'], ['make' => []], ['variant' => str_repeat('x', 161)],
        ['is_enabled' => 'yes'], ['model_year' => '2020.5']] as $invalid) {
        rejects(static fn () => VehicleTypeInput::fromArray(array_replace($input, $invalid)));
    }
};
$tests['fitment codes deduplicate normalise and retain a valid numeric code'] = static function (): void {
    same(['ranger', 'hilux', '0'], FitmentInput::codes(" RANGER\nranger,hilux\n0", false));
    same([], FitmentInput::codes('', true));
    rejects(static fn () => FitmentInput::codes('ranger', true));
    rejects(static fn () => FitmentInput::codes('', false));
    rejects(static fn () => FitmentInput::codes('ranger; DROP TABLE', false));
    rejects(static fn () => FitmentInput::codes(['ranger'], false));
};
$tests['CSV reader accepts BOM quoted fields and blank lines without changing column alignment'] = static function (): void {
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, "\xEF\xBB\xBFsku,vehicle_code,universal\n\"SKU,ONE\",ranger,0\n\nSKU2,,1\n");
    rewind($stream);
    $rows = (new Reader())->read($stream, ['sku', 'vehicle_code', 'universal']);
    fclose($stream);
    same('SKU,ONE', $rows[0]['sku']);
    same('', $rows[1]['vehicle_code']);
    same(2, count($rows));
};
$tests['CSV reader rejects wrong headers malformed row widths and empty files'] = static function (): void {
    foreach (["wrong,header\na,b\n", "sku,vehicle_code,universal\nSKU,ranger\n", "sku,vehicle_code,universal\n", ''] as $csv) {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        rejects(static fn () => (new Reader())->read($stream, ['sku', 'vehicle_code', 'universal']));
        fclose($stream);
    }
};
$tests['mapped vehicles get a protected shop form while legacy vehicles get a matching link'] = static function (): void {
    $vehicles = [new Vehicle(1, 'AB12CDE', 'Ford', 'Ranger', '', true, true, 7, 2020, 'Double Cab'),
        new Vehicle(2, 'XY20ABC', 'Toyota', 'Hilux', '', false, false)];
    $html = TemplateRenderer::render($vehicles);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    same(1, $xpath->query('//form[contains(@action,"garage/vehicle/shop") and @method="post"]')->length);
    same(1, $xpath->query('//form[contains(@action,"garage/vehicle/shop")]//input[@name="form_key"]')->length);
    same(true, str_contains($html, '2020 Double Cab'));
    same(true, str_contains($html, 'Match vehicle to find parts'));
};
$tests['structured selector configuration cannot inject HTML and does not submit make/model as canonical identity'] = static function (): void {
    $html = TemplateRenderer::render([], ['make' => '"><script>alert(1)</script>', 'model' => 'Ranger', 'model_year' => 2020], false, true);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    same(0, $xpath->query('//script')->length);
    same(4, $xpath->query('//select[@data-level]')->length);
    $config = $xpath->query('//*[@data-mage-init]')->item(0)->getAttribute('data-mage-init');
    $data = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
    same('"><script>alert(1)</script>', $data['Automotive_Garage/js/vehicle-selector']['make']);
};
