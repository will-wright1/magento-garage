<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Vehicle;

class DefaultVehicle extends AbstractPost
{
    protected function perform(int $customerId): void
    {
        $this->garage->setDefault($customerId, $this->vehicleId());
    }

    protected function successMessage(): string
    {
        return 'Your default vehicle has been updated.';
    }
}
