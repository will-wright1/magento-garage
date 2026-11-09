<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Support;

use Automotive\Garage\Api\SettingsInterface;

final class Settings implements SettingsInterface
{
    public function __construct(private readonly string $market = 'GB', private readonly string $country = 'GB',
        private readonly string $requirement = 'auto', private readonly string $badge = 'auto')
    {
    }

    public function defaultMarket(): string { return $this->market; }
    public function defaultCountry(): string { return $this->country; }
    public function plateRequirement(): string { return $this->requirement; }
    public function badgeStyle(): string { return $this->badge; }
    public function isPlateRequired(string $country): bool
    {
        return $this->requirement === 'required' || ($this->requirement === 'auto' && $country === 'GB');
    }
}
