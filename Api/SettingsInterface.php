<?php
declare(strict_types=1);

namespace Automotive\Garage\Api;

interface SettingsInterface
{
    public function defaultMarket(): string;
    public function defaultCountry(): string;
    public function plateRequirement(): string;
    public function isPlateRequired(string $country): bool;
    public function badgeStyle(): string;
}
