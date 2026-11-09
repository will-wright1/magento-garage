<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Api\VehicleStorageInterface;
use Automotive\Garage\Api\VehicleTypeProviderInterface;
use Automotive\Garage\Api\SettingsInterface;
use Automotive\Garage\Exception\GarageException;

/** Callers must obtain customerId from authenticated server-side context. */
class Garage
{
    public function __construct(
        private readonly VehicleStorageInterface $storage,
        private readonly int $maxVehicles = 50,
        private readonly ?VehicleTypeProviderInterface $vehicleTypes = null,
        private readonly ?SettingsInterface $settings = null
    ) {
        if ($maxVehicles < 1) {
            throw new \InvalidArgumentException('The garage limit must be positive.');
        }
    }

    /** @return Vehicle[] */
    public function getList(int $customerId): array
    {
        $this->requireCustomer($customerId);
        return $this->storage->getAll($customerId);
    }

    public function get(int $customerId, int $vehicleId): Vehicle
    {
        return $this->find($this->getList($customerId), $vehicleId);
    }

    public function getSelected(int $customerId): ?Vehicle
    {
        $vehicles = $this->getList($customerId);
        foreach ($vehicles as $vehicle) {
            if ($vehicle->isSelected) {
                return $vehicle;
            }
        }
        foreach ($vehicles as $vehicle) {
            if ($vehicle->isDefault) {
                return $vehicle;
            }
        }
        return $vehicles[0] ?? null;
    }

    public function save(int $customerId, array $data, ?int $vehicleId = null): Vehicle
    {
        $this->requireCustomer($customerId);
        return $this->storage->transaction($customerId, function () use ($customerId, $data, $vehicleId): Vehicle {
            $vehicles = $this->storage->getAll($customerId);
            $existing = $vehicleId !== null ? $this->find($vehicles, $vehicleId) : null;
            $data += ['vehicle_market' => $existing?->market ?? $this->settings?->defaultMarket() ?? 'GB',
                'registration_country' => $existing?->registrationCountry ?? $this->settings?->defaultCountry() ?? 'GB'];
            $data += ['registration_region' => $existing !== null && $data['registration_country'] === $existing->registrationCountry
                ? $existing->registrationRegion : ''];
            $market = Market::validate($data['vehicle_market']);
            $country = Market::validate($data['registration_country']);
            $rawTypeId = $data['vehicle_type_id'] ?? null;
            if ($rawTypeId !== null && $rawTypeId !== '') {
                $typeId = filter_var($rawTypeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $type = $typeId !== false ? $this->vehicleTypes?->getEnabled($typeId) : null;
                if ($type === null) {
                    throw new GarageException('Choose an available vehicle from the vehicle catalogue.');
                }
                if ($type->market !== $market) {
                    throw new GarageException('Choose a vehicle definition from the selected specification market.');
                }
                $data = array_replace($data, ['make' => $type->make, 'model' => $type->model,
                    'model_year' => $type->year, 'variant' => $type->variant, 'vehicle_type_id' => $type->id]);
            }
            $input = VehicleInput::fromArray($data, $this->settings?->isPlateRequired($country) ?? $country === 'GB');
            foreach ($vehicles as $vehicle) {
                if ($input->registration !== '' && $vehicle->registration === $input->registration
                    && $vehicle->registrationCountry === $input->registrationCountry
                    && $vehicle->registrationRegion === $input->registrationRegion && $vehicle->id !== $vehicleId) {
                    throw new GarageException('That registration is already saved in your garage.');
                }
                if ($input->vin !== '' && $vehicle->vin === $input->vin && $vehicle->id !== $vehicleId) {
                    throw new GarageException('That VIN or chassis number is already saved in your garage.');
                }
            }
            if ($vehicleId === null) {
                if (count($vehicles) >= $this->maxVehicles) {
                    throw new GarageException('Your garage is full. Remove a vehicle before adding another.');
                }
                $vehicleId = $this->storage->insert($customerId, $input);
                if ($vehicles === []) {
                    $this->storage->setDefault($customerId, $vehicleId);
                    $this->storage->select($customerId, $vehicleId);
                }
            } else {
                $this->storage->update($customerId, $vehicleId, $input);
            }
            return $this->find($this->storage->getAll($customerId), $vehicleId);
        });
    }

    public function delete(int $customerId, int $vehicleId): void
    {
        $this->requireCustomer($customerId);
        $this->storage->transaction($customerId, function () use ($customerId, $vehicleId): void {
            $vehicle = $this->find($this->storage->getAll($customerId), $vehicleId);
            $this->storage->delete($customerId, $vehicleId);
            $remaining = $this->storage->getAll($customerId);
            if ($remaining === []) {
                return;
            }
            $defaultId = $remaining[0]->id;
            foreach ($remaining as $candidate) {
                if ($candidate->isDefault) {
                    $defaultId = $candidate->id;
                    break;
                }
            }
            if ($vehicle->isDefault) {
                $this->storage->setDefault($customerId, $defaultId);
            }
            if ($vehicle->isSelected) {
                $this->storage->select($customerId, $defaultId);
            }
        });
    }

    public function select(int $customerId, int $vehicleId): void
    {
        $this->changeChoice($customerId, $vehicleId, false);
    }

    public function setDefault(int $customerId, int $vehicleId): void
    {
        $this->changeChoice($customerId, $vehicleId, true);
    }

    private function changeChoice(int $customerId, int $vehicleId, bool $default): void
    {
        $this->requireCustomer($customerId);
        $this->storage->transaction($customerId, function () use ($customerId, $vehicleId, $default): void {
            $this->find($this->storage->getAll($customerId), $vehicleId);
            if ($default) {
                $this->storage->setDefault($customerId, $vehicleId);
            } else {
                $this->storage->select($customerId, $vehicleId);
            }
        });
    }

    /** @param Vehicle[] $vehicles */
    private function find(array $vehicles, int $vehicleId): Vehicle
    {
        foreach ($vehicles as $vehicle) {
            if ($vehicle->id === $vehicleId) {
                return $vehicle;
            }
        }
        throw new GarageException('The vehicle could not be found in your garage.');
    }

    private function requireCustomer(int $customerId): void
    {
        if ($customerId < 1) {
            throw new GarageException('Please sign in to use your garage.');
        }
    }
}
