<?php
declare(strict_types=1);

use Automotive\Garage\Model\Garage;
use Automotive\Garage\Model\Market;
use Automotive\Garage\Model\Specification;
use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Model\VehicleInput;
use Automotive\Garage\Model\VehicleType;
use Automotive\Garage\Model\VehicleTypeInput;
use Automotive\Garage\Model\Csv\Reader;
use Automotive\Garage\Test\Support\InMemoryStorage;
use Automotive\Garage\Test\Support\InMemoryTypes;
use Automotive\Garage\Test\Support\Settings;
use Automotive\Garage\Test\Support\TemplateRenderer;

function usDetails(array $replace = []): array
{
    return array_replace(['vehicle_market' => 'US', 'registration_country' => 'US', 'registration' => '',
        'make' => 'Ford', 'model' => 'Ranger', 'model_year' => '2020'], $replace);
}

$tests['US defaults allow several plate-free vehicles with stable IDs and preferences'] = static function (): void {
    $garage = new Garage(new InMemoryStorage(), 50, null, new Settings('US', 'US'));
    $first = $garage->save(1, ['make' => 'Ford', 'model' => 'Ranger', 'model_year' => '2020']);
    $second = $garage->save(1, ['make' => 'Ford', 'model' => 'Ranger', 'model_year' => '2020']);
    same('', $first->registration);
    same('US', $first->market);
    same(true, $first->id !== $second->id);
    choices($garage, 1, $first->id, $first->id);
    same('2020 Ford Ranger', $first->displayName());
    same(null, VehicleInput::fromArray(usDetails())->toArray()['registration']);
};
$tests['plate requirement is enforced on the server independently of vehicle market'] = static function (): void {
    rejects(static fn () => VehicleInput::fromArray(usDetails(['registration_country' => 'GB'])));
    $required = new Garage(new InMemoryStorage(), 50, null, new Settings('US', 'US', 'required'));
    rejects(static fn () => $required->save(1, usDetails()));
    $optional = new Garage(new InMemoryStorage(), 50, null, new Settings('GB', 'GB', 'optional'));
    same('', $optional->save(1, ['make' => 'Ford', 'model' => 'Ranger'])->registration);
    rejects(static fn () => VehicleInput::fromArray(usDetails(['model_year' => ''])));
};
$tests['duplicate plates are scoped to customer country and issuing jurisdiction'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $ca = $garage->save(1, usDetails(['registration' => 'ABC123', 'registration_region' => 'CA']));
    $ny = $garage->save(1, usDetails(['registration' => 'ABC123', 'registration_region' => 'NY']));
    $garage->save(1, details('ABC123'));
    rejects(static fn () => $garage->save(1, usDetails(['registration' => 'abc123', 'registration_region' => 'CA'])));
    rejects(static fn () => $garage->save(1, usDetails(['registration' => 'ABC123', 'registration_region' => 'CA']), $ny->id));
    same('NY', $garage->get(1, $ny->id)->registrationRegion);
    same(3, count($garage->getList(1)));
    choices($garage, 1, $ca->id, $ca->id);
};
$tests['jurisdiction validation rejects unsupported countries states and malformed identifiers'] = static function (): void {
    foreach ([['registration_country' => 'UK'], ['vehicle_market' => 'CA'], ['registration_region' => 'XX'],
        ['registration_region' => []], ['registration' => 'ABC123'], ['vin_format' => 'guess'],
        ['registration' => '<script>', 'registration_region' => 'CA']] as $invalid) {
        rejects(static fn () => VehicleInput::fromArray(usDetails($invalid)));
    }
    rejects(static fn () => VehicleInput::fromArray(details() + ['registration_region' => 'CA']));
    same('PR', VehicleInput::fromArray(usDetails(['registration' => 'ABC123', 'registration_region' => 'PR']))->registrationRegion);
};
$tests['UK plates normalise for comparison while US plate spacing and display are retained'] = static function (): void {
    $uk = VehicleInput::fromArray(details(' ab12-cde '));
    same('AB12CDE', $uk->registration);
    same('AB12-CDE', $uk->registrationDisplay);
    $us = VehicleInput::fromArray(usDetails(['registration' => ' ab  12-c ', 'registration_region' => 'CA']));
    same('AB 12-C', $us->registration);
    same('AB 12-C', $us->registrationDisplay);
};
$tests['VIN and legacy chassis entry have separate validation and account-scoped duplicate guards'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $vin = '1M8GDM9AXKP042788';
    $first = $garage->save(1, usDetails(['vin' => strtolower($vin)]));
    same($vin, $first->vin);
    rejects(static fn () => $garage->save(1, usDetails(['vin' => $vin, 'registration' => 'OTHER', 'registration_region' => 'NY'])));
    $garage->save(2, usDetails(['vin' => $vin]));
    rejects(static fn () => VehicleInput::fromArray(usDetails(['vin' => '1I8GDM9AXKP042788'])));
    rejects(static fn () => VehicleInput::fromArray(usDetails(['vin' => 'ABC123'])));
    $legacy = $garage->save(1, usDetails(['vin' => 'io-123/old', 'vin_format' => 'legacy']));
    same('IO-123/OLD', $legacy->vin);
    rejects(static fn () => VehicleInput::fromArray(usDetails(['vin' => 'ABC<script>', 'vin_format' => 'legacy'])));
};
$tests['US-spec imported vehicles retain their specification independently of UK plates'] = static function (): void {
    $us = new VehicleType(8, 'us-ranger', 'Ford', 'Ranger', 2020, 'Crew Cab', true, 'US');
    $garage = new Garage(new InMemoryStorage(), 50, new InMemoryTypes([$us]));
    rejects(static fn () => $garage->save(1, details() + ['vehicle_type_id' => 8]));
    $saved = $garage->save(1, ['registration' => 'AB12CDE', 'vehicle_market' => 'US', 'registration_country' => 'GB', 'vehicle_type_id' => 8]);
    same('US', $saved->market);
    same('GB', $saved->registrationCountry);
    same(8, $saved->vehicleTypeId);
    same(2020, $saved->modelYear);
};
$tests['editing a UK vehicle on a US-default store preserves its jurisdiction and preferences'] = static function (): void {
    $storage = new InMemoryStorage();
    $legacy = (new Garage($storage))->save(1, details());
    $usStore = new Garage($storage, 50, null, new Settings('US', 'US'));
    $edited = $usStore->save(1, details('XY20ABC'), $legacy->id);
    same('GB', $edited->market);
    same('GB', $edited->registrationCountry);
    same($legacy->id, $edited->id);
    choices($usStore, 1, $legacy->id, $legacy->id);
    $row = Vehicle::fromRow(['entity_id' => 90, 'registration' => 'AB12CDE', 'make' => 'Ford', 'model' => 'Ranger', 'nickname' => '', 'is_default' => 1, 'is_selected' => 1]);
    same('GB', $row->market);
    same('GB', $row->registrationCountry);
    same('AB12CDE', $row->displayName());
};
$tests['changing a US plate to UK registration clears an omitted US jurisdiction'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $vehicle = $garage->save(1, usDetails(['registration' => 'ABC123', 'registration_region' => 'CA']));
    $edited = $garage->save(1, usDetails(['registration' => 'AB12CDE', 'registration_country' => 'GB']), $vehicle->id);
    same('US', $edited->market);
    same('GB', $edited->registrationCountry);
    same('', $edited->registrationRegion);
    choices($garage, 1, $vehicle->id, $vehicle->id);
};
$tests['selector predecessor filters distinguish GB make-first and US year-first catalogues'] = static function (): void {
    same(['market' => 'GB'], Market::filters('GB', 'make', '', '', null));
    same(['market' => 'US'], Market::filters('US', 'year', '', '', null));
    same(['market' => 'US', 'model_year' => 2020], Market::filters('US', 'make', '', '', 2020));
    same(['market' => 'GB', 'make' => 'Ford', 'model' => 'Ranger'], Market::filters('GB', 'year', 'Ford', 'Ranger', null));
    rejects(static fn () => Market::filters('US', 'make', '', '', null));
    rejects(static fn () => Market::filters('GB', 'variant', 'Ford', 'Ranger', null));
};
$tests['structured specifications validate enums and retain canonical millimetres'] = static function (): void {
    $base = ['code' => 'us-ranger', 'market' => 'US', 'make' => 'Ford', 'model' => 'Ranger', 'model_year' => 2020,
        'engine' => ' 2.3L ', 'steering' => 'LHD', 'fuel_type' => 'petrol', 'bed_length_mm' => '1500'];
    $data = VehicleTypeInput::fromArray($base);
    same('US', $data['market']); same('2.3L', $data['engine']); same(1500, $data['bed_length_mm']);
    same(true, str_contains(Specification::summary($data, 'US'), '59.06 in (1500 mm)'));
    same(true, str_contains(Specification::summary($data, 'GB'), '1500 mm'));
    foreach ([['market' => 'EU'], ['steering' => 'Right'], ['bed_length_mm' => '5 ft'], ['wheelbase_mm' => '-1'], ['engine' => []]] as $invalid) {
        rejects(static fn () => VehicleTypeInput::fromArray(array_replace($base, $invalid)));
    }
};
$tests['vehicle CSV supports explicit markets and specifications while retaining legacy files'] = static function (): void {
    $reader = new Reader();
    foreach (["code,market,make,model,model_year,variant,is_enabled,engine\nus-ranger,US,Ford,Ranger,2020,Crew Cab,1,2.3L\n",
        "code,make,model,model_year,variant,is_enabled\ngb-ranger,Ford,Ranger,2020,Double Cab,1\n"] as $csv) {
        $stream = fopen('php://memory', 'w+'); fwrite($stream, $csv); rewind($stream);
        $rows = $reader->read($stream, ['code', 'make', 'model', 'model_year', 'variant', 'is_enabled'], array_merge(['market'], Specification::fields()));
        fclose($stream);
        same(str_starts_with($rows[0]['code'], 'us-') ? 'US' : 'GB', VehicleTypeInput::fromArray($rows[0])['market']);
    }
    foreach (["code,make,model,model_year,variant,is_enabled,market,market\na,Ford,Ranger,2020,Cab,1,US,GB\n",
        "code,make,model,model_year,variant,is_enabled,unknown\na,Ford,Ranger,2020,Cab,1,value\n"] as $csv) {
        $stream = fopen('php://memory', 'w+'); fwrite($stream, $csv); rewind($stream);
        rejects(static fn () => $reader->read($stream, ['code', 'make', 'model', 'model_year', 'variant', 'is_enabled'], ['market'])); fclose($stream);
    }
};
$tests['US account form is year-first with optional plate and separate jurisdiction fields'] = static function (): void {
    $html = TemplateRenderer::render([], [], false, true, new Settings('US', 'US'));
    $document = new DOMDocument(); @$document->loadHTML($html); $xpath = new DOMXPath($document);
    same(0, $xpath->query('//input[@name="registration" and @required]')->length);
    same(1, $xpath->query('//input[@name="model_year" and @required]')->length);
    same('year', $xpath->query('//*[@data-manual-fields]/*[@data-field]')->item(0)->getAttribute('data-field'));
    same('year', $xpath->query('//*[@data-catalogue-fields]/*[@data-field]')->item(0)->getAttribute('data-field'));
    same(1, $xpath->query('//select[@name="registration_region"]')->length);
    same(1, $xpath->query('//input[@name="vin"]')->length);
};
$tests['plate-free vehicle actions have useful labels and identifiers remain escaped'] = static function (): void {
    $vehicle = new Vehicle(1, '', 'Ford', 'Ranger', 'Work truck', true, true, 8, 2020, 'Crew Cab', 'US', 'US');
    $html = TemplateRenderer::render([$vehicle]);
    same(true, str_contains($html, 'Shop for vehicle Work truck'));
    same(true, str_contains($html, 'garage-registration-neutral'));
    $attack = '\"><script>alert(1)</script>';
    $html = TemplateRenderer::render([], ['vin' => $attack, 'registration' => $attack]);
    $document = new DOMDocument(); @$document->loadHTML($html); $xpath = new DOMXPath($document);
    same(0, $xpath->query('//script|//*[@onfocus]')->length);
};
