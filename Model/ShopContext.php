<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Api\VehicleTypeProviderInterface;
use Automotive\Garage\Exception\GarageException;
use Magento\Customer\Model\Session;

class ShopContext
{
    private ?Vehicle $vehicle = null;
    private ?VehicleType $type = null;

    public function __construct(private readonly Session $session, private readonly Garage $garage,
        private readonly VehicleTypeProviderInterface $types)
    {
    }

    public function vehicle(): Vehicle
    {
        if (!$this->session->isLoggedIn()) {
            throw new GarageException('Please sign in to use your garage.');
        }
        if ($this->vehicle === null) {
            $this->vehicle = $this->garage->getSelected((int) $this->session->getCustomerId());
        }
        return $this->vehicle ?? throw new GarageException('Add and select a vehicle in your garage first.');
    }

    public function type(): VehicleType
    {
        if ($this->type === null) {
            $id = $this->vehicle()->vehicleTypeId;
            $this->type = $id ? $this->types->getEnabled($id) : null;
        }
        if ($this->type === null || $this->type->market !== $this->vehicle()->market) {
            throw new GarageException('Edit your selected vehicle and choose an available catalogue vehicle first.');
        }
        return $this->type;
    }
}
