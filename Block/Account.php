<?php
declare(strict_types=1);

namespace Automotive\Garage\Block;

use Automotive\Garage\Model\Garage;
use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Automotive\Garage\Api\SettingsInterface;
use Automotive\Garage\Model\Market;
use Automotive\Garage\Model\Specification;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;

class Account extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Session $session,
        private readonly Garage $garage,
        private readonly VehicleTypeStorage $types,
        private readonly SettingsInterface $settings,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return Vehicle[] */
    public function getVehicles(): array
    {
        return $this->session->isLoggedIn()
            ? $this->garage->getList((int) $this->session->getCustomerId()) : [];
    }

    public function getFormValues(): array
    {
        $values = (array) $this->getData('form_values');
        $values['vehicle_market'] = isset(Market::LABELS[$values['vehicle_market'] ?? ''])
            ? $values['vehicle_market'] : $this->settings->defaultMarket();
        $values['registration_country'] = isset(Market::LABELS[$values['registration_country'] ?? ''])
            ? $values['registration_country'] : $this->settings->defaultCountry();
        return $values + ['registration_region' => '', 'vin' => '', 'vin_format' => 'standard'];
    }

    public function isEditing(): bool
    {
        return (bool) $this->getData('editing');
    }

    public function hasVehicleCatalogue(): bool
    {
        return $this->types->count() > 0;
    }

    public function plateRequirement(): string
    {
        return $this->settings->plateRequirement();
    }

    public function isPlateRequired(string $country): bool
    {
        return $this->settings->isPlateRequired($country);
    }

    public function registrationClass(Vehicle $vehicle): string
    {
        $style = $this->settings->badgeStyle();
        return $style === 'neutral' || ($style === 'auto' && ($vehicle->registrationCountry === 'US' || $vehicle->registration === ''))
            ? 'garage-registration garage-registration-neutral' : 'garage-registration';
    }

    public function specificationLabel(Vehicle $vehicle): string
    {
        $type = $vehicle->vehicleTypeId !== null ? $this->types->get($vehicle->vehicleTypeId) : null;
        return $type ? Specification::summary($type->specifications, $type->market) : '';
    }
}
