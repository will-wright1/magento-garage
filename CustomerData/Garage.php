<?php
declare(strict_types=1);

namespace Automotive\Garage\CustomerData;

use Automotive\Garage\Model\Garage as GarageService;
use Automotive\Garage\Model\Vehicle;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;

/** Private section for storefront integrations; keep registrations out of cached pages. */
class Garage implements SectionSourceInterface
{
    public function __construct(private readonly Session $session, private readonly GarageService $garage)
    {
    }

    public function getSectionData(): array
    {
        if (!$this->session->isLoggedIn()) {
            return ['vehicles' => [], 'selected_vehicle_id' => null];
        }
        $customerId = (int) $this->session->getCustomerId();
        $vehicles = $this->garage->getList($customerId);
        $selectedId = $defaultId = null;
        foreach ($vehicles as $vehicle) {
            if ($vehicle->isSelected) {
                $selectedId = $vehicle->id;
            }
            if ($vehicle->isDefault) {
                $defaultId = $vehicle->id;
            }
        }
        return [
            'vehicles' => array_map(static function (Vehicle $vehicle): array {
                $data = $vehicle->toArray();
                // VINs are editable in the private account form, not needed in browser customer-data storage.
                unset($data['vin'], $data['vin_format']);
                $data['display_name'] = $vehicle->displayName();
                return $data;
            }, $vehicles),
            'selected_vehicle_id' => $selectedId ?? $defaultId ?? ($vehicles[0]->id ?? null),
        ];
    }
}
