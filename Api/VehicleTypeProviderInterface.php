<?php
declare(strict_types=1);

namespace Automotive\Garage\Api;

use Automotive\Garage\Model\VehicleType;

interface VehicleTypeProviderInterface
{
    public function getEnabled(int $id): ?VehicleType;
}
