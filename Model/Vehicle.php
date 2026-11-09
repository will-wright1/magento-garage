<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

final class Vehicle
{
    public function __construct(
        public readonly int $id,
        public readonly string $registration,
        public readonly string $make,
        public readonly string $model,
        public readonly string $nickname,
        public readonly bool $isDefault,
        public readonly bool $isSelected,
        public readonly ?int $vehicleTypeId = null,
        public readonly ?int $modelYear = null,
        public readonly string $variant = '',
        public readonly string $market = 'GB',
        public readonly string $registrationCountry = 'GB',
        public readonly string $registrationRegion = '',
        public readonly string $registrationDisplay = '',
        public readonly string $vin = '',
        public readonly string $vinFormat = 'standard'
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['entity_id'], (string) $row['registration'],
            (string) $row['make'], (string) $row['model'], (string) $row['nickname'],
            (bool) $row['is_default'], (bool) $row['is_selected'],
            isset($row['vehicle_type_id']) ? (int) $row['vehicle_type_id'] : null,
            isset($row['model_year']) ? (int) $row['model_year'] : null,
            (string) ($row['variant'] ?? ''), (string) ($row['vehicle_market'] ?? 'GB'),
            (string) ($row['registration_country'] ?? 'GB'), (string) ($row['registration_region'] ?? ''),
            (string) ($row['registration_display'] ?? ''), (string) ($row['vin'] ?? ''),
            (string) ($row['vin_format'] ?? 'standard'));
    }

    public function toArray(): array
    {
        return ['vehicle_id' => $this->id, 'registration' => $this->registration,
            'make' => $this->make, 'model' => $this->model, 'nickname' => $this->nickname,
            'is_default' => $this->isDefault, 'is_selected' => $this->isSelected,
            'vehicle_type_id' => $this->vehicleTypeId, 'model_year' => $this->modelYear, 'variant' => $this->variant,
            'vehicle_market' => $this->market, 'registration_country' => $this->registrationCountry,
            'registration_region' => $this->registrationRegion, 'registration_display' => $this->registrationDisplay,
            'vin' => $this->vin, 'vin_format' => $this->vinFormat];
    }

    public function displayName(): string
    {
        return $this->registrationDisplay ?: ($this->registration ?: ($this->nickname ?: trim(($this->modelYear ?? '') . ' ' . $this->make . ' ' . $this->model)));
    }
}
