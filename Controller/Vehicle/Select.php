<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Vehicle;

class Select extends AbstractPost
{
    protected function perform(int $customerId): void
    {
        $this->garage->select($customerId, $this->vehicleId());
    }

    protected function successMessage(): string
    {
        return 'Your selected vehicle has been updated.';
    }
}
