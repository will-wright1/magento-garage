<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Vehicle;

class Delete extends AbstractPost
{
    protected function perform(int $customerId): void
    {
        $this->garage->delete($customerId, $this->vehicleId());
    }

    protected function successMessage(): string
    {
        return 'Your vehicle has been removed.';
    }
}
