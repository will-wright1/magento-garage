<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Vehicle;

use Automotive\Garage\Exception\GarageException;

class Shop extends AbstractPost
{
    protected function perform(int $customerId): void
    {
        $id = $this->vehicleId();
        if ($this->garage->get($customerId, $id)->vehicleTypeId === null) {
            throw new GarageException('Match this vehicle to the vehicle catalogue before shopping for compatible parts.');
        }
        $this->garage->select($customerId, $id);
    }

    protected function successPath(): string
    {
        return 'garage/shop/index';
    }

    protected function successMessage(): string
    {
        return 'Showing products for your selected vehicle.';
    }
}
