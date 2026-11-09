<?php
declare(strict_types=1);

namespace Automotive\Garage\Api;

use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Model\VehicleInput;

/** Internal persistence boundary; not exposed as a public web API. */
interface VehicleStorageInterface
{
    /** @return Vehicle[] Ordered by entity ID, oldest first. */
    public function getAll(int $customerId): array;

    /** Serialise this customer's mutations and roll back all writes on failure. */
    public function transaction(int $customerId, callable $operation): mixed;

    public function insert(int $customerId, VehicleInput $input): int;

    public function update(int $customerId, int $vehicleId, VehicleInput $input): void;

    public function delete(int $customerId, int $vehicleId): void;

    public function setDefault(int $customerId, int $vehicleId): void;

    public function select(int $customerId, int $vehicleId): void;
}
