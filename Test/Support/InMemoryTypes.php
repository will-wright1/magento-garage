<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Support;

use Automotive\Garage\Api\VehicleTypeProviderInterface;
use Automotive\Garage\Model\VehicleType;

final class InMemoryTypes implements VehicleTypeProviderInterface
{
    /** @param VehicleType[] $types */
    public function __construct(private readonly array $types)
    {
    }

    public function getEnabled(int $id): ?VehicleType
    {
        foreach ($this->types as $type) {
            if ($type->id === $id && $type->enabled) {
                return $type;
            }
        }
        return null;
    }
}
