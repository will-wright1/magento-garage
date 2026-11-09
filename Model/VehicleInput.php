<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Exception\GarageException;

final class VehicleInput
{
    private function __construct(
        public readonly string $registration,
        public readonly string $make,
        public readonly string $model,
        public readonly string $nickname,
        public readonly ?int $vehicleTypeId,
        public readonly ?int $modelYear,
        public readonly string $variant,
        public readonly string $market,
        public readonly string $registrationCountry,
        public readonly string $registrationRegion,
        public readonly string $registrationDisplay,
        public readonly string $vin,
        public readonly string $vinFormat
    ) {
    }

    public static function fromArray(array $input, ?bool $plateRequired = null): self
    {
        $market = Market::validate($input['vehicle_market'] ?? 'GB');
        $country = Market::validate($input['registration_country'] ?? 'GB');
        $display = strtoupper(self::text($input, 'registration', false));
        $registration = $country === 'GB' ? (string) preg_replace('/[\s-]+/u', '', $display) : $display;
        $required = $plateRequired ?? $country === 'GB';
        if ($registration === '' && $required) {
            throw new GarageException('Enter a registration number or license plate.');
        }
        if ($registration !== '' && !preg_match($country === 'GB' ? '/\A[A-Z0-9]{1,10}\z/D'
            : '/\A(?=.*[A-Z0-9])[A-Z0-9 *+♥★-]{1,20}\z/uD', $registration)) {
            throw new GarageException('Enter a valid plate: up to 10 letters/numbers for the UK or 20 supported characters for the US.');
        }
        if (mb_strlen($display, 'UTF-8') > 30) {
            throw new GarageException('Enter a plate within 30 displayed characters.');
        }
        $region = $input['registration_region'] ?? '';
        if (!is_string($region) || ($region !== '' && ($country !== 'US' || !isset(Market::US_REGIONS[$region])))
            || ($country === 'US' && $registration !== '' && $region === '')) {
            throw new GarageException('Choose the issuing US state or jurisdiction for this license plate.');
        }
        $vin = strtoupper(self::text($input, 'vin', false));
        $format = $input['vin_format'] ?? 'standard';
        if (!in_array($format, ['standard', 'legacy'], true)) {
            throw new GarageException('Choose a valid VIN or chassis number format.');
        }
        if ($vin !== '' && !preg_match($format === 'standard' ? '/\A[A-HJ-NPR-Z0-9]{17}\z/D'
            : '/\A[A-Z0-9][A-Z0-9.\/-]{0,39}\z/D', $vin)) {
            throw new GarageException('Enter a 17-character VIN, or select the older/imported chassis number format.');
        }
        $typeId = self::optionalInteger($input['vehicle_type_id'] ?? null, 1, PHP_INT_MAX);
        $year = self::optionalInteger($input['model_year'] ?? null, 1886, 2100);
        if ($market === 'US' && $year === null) {
            throw new GarageException('Enter the model year for a US-specification vehicle.');
        }
        $variant = self::text($input, 'variant', false, 160);
        return new self($registration, self::text($input, 'make', true), self::text($input, 'model', true),
            self::text($input, 'nickname', false), $typeId, $year, $variant, $market, $country, $region, $display, $vin, $format);
    }

    private static function text(array $input, string $field, bool $required, int $max = 100): string
    {
        $value = $input[$field] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new GarageException('Enter valid text for your vehicle details.');
        }
        if (strlen($value) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
            throw new GarageException('Vehicle details contain invalid or excessively long text.');
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
            throw new GarageException('Enter make and model within the vehicle field length limits.');
        }
        return $value;
    }

    public function toArray(): array
    {
        // NULL permits several plate-free vehicles under the database's jurisdictional unique key.
        return ['registration' => $this->registration !== '' ? $this->registration : null, 'make' => $this->make,
            'model' => $this->model, 'nickname' => $this->nickname, 'vehicle_type_id' => $this->vehicleTypeId,
            'model_year' => $this->modelYear, 'variant' => $this->variant, 'vehicle_market' => $this->market,
            'registration_country' => $this->registrationCountry, 'registration_region' => $this->registrationRegion,
            'registration_display' => $this->registrationDisplay, 'vin' => $this->vin !== '' ? $this->vin : null,
            'vin_format' => $this->vinFormat];
    }

    private static function optionalInteger(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($number === false) {
            throw new GarageException('Choose a valid vehicle definition and model year.');
        }
        return $number;
    }
}
