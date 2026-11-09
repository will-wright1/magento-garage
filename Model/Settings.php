<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Api\SettingsInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Settings implements SettingsInterface
{
    public function __construct(private readonly ScopeConfigInterface $config)
    {
    }

    public function defaultMarket(): string
    {
        return $this->choice('default_market', ['GB', 'US'], 'GB');
    }

    public function defaultCountry(): string
    {
        return $this->choice('default_country', ['GB', 'US'], 'GB');
    }

    public function plateRequirement(): string
    {
        return $this->choice('plate_requirement', ['auto', 'required', 'optional'], 'auto');
    }

    public function isPlateRequired(string $country): bool
    {
        return $this->plateRequirement() === 'required'
            || ($this->plateRequirement() === 'auto' && $country === 'GB');
    }

    public function badgeStyle(): string
    {
        return $this->choice('badge_style', ['auto', 'yellow', 'neutral'], 'auto');
    }

    private function choice(string $field, array $allowed, string $fallback): string
    {
        $value = $this->config->getValue('automotive_garage/general/' . $field, ScopeInterface::SCOPE_STORE);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
