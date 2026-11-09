<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

final class VehicleType
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $make,
        public readonly string $model,
        public readonly int $year,
        public readonly string $variant,
        public readonly bool $enabled,
        public readonly string $market = 'GB',
        public readonly array $specifications = []
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['entity_id'], $row['code'], $row['make'], $row['model'],
            (int) $row['model_year'], $row['variant'], (bool) $row['is_enabled'], $row['market'] ?? 'GB',
            array_intersect_key($row, array_flip(Specification::fields())));
    }

    public function label(): string
    {
        return $this->make . ' ' . $this->model . ' ' . $this->year
            . ($this->variant !== '' ? ' · ' . $this->variant : '')
            . ' · ' . (string) __(Market::LABELS[$this->market]);
    }

    public function toArray(): array
    {
        return ['entity_id' => $this->id, 'code' => $this->code, 'make' => $this->make,
            'model' => $this->model, 'model_year' => $this->year, 'variant' => $this->variant,
            'is_enabled' => (int) $this->enabled, 'market' => $this->market] + $this->specifications;
    }

    public function selectionLabel(): string
    {
        $summary = Specification::summary($this->specifications, $this->market);
        return ($this->variant ?: (string) __('Standard')) . ($summary !== '' ? ' · ' . $summary : '');
    }
}
