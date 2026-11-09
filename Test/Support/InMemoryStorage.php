<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Support;

use Automotive\Garage\Api\VehicleStorageInterface;
use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Model\VehicleInput;

/** Behaviour-test double, not a substitute for the Magento/MySQL integration tests. */
final class InMemoryStorage implements VehicleStorageInterface
{
    private array $rows = [];
    private int $nextId = 1;
    private ?int $lockedCustomer = null;
    public bool $failNextFlag = false;

    public function getAll(int $customerId): array
    {
        $rows = array_filter($this->rows, static fn (array $row): bool => $row['customer_id'] === $customerId);
        ksort($rows);
        return array_values(array_map(Vehicle::fromRow(...), $rows));
    }

    public function transaction(int $customerId, callable $operation): mixed
    {
        if ($this->lockedCustomer !== null) {
            throw new \LogicException('Unexpected nested garage transaction.');
        }
        $before = $this->rows;
        $nextId = $this->nextId;
        $this->lockedCustomer = $customerId;
        try {
            return $operation();
        } catch (\Throwable $exception) {
            $this->rows = $before;
            $this->nextId = $nextId;
            throw $exception;
        } finally {
            $this->lockedCustomer = null;
        }
    }

    public function insert(int $customerId, VehicleInput $input): int
    {
        $this->requireLock($customerId);
        $id = $this->nextId++;
        $this->rows[$id] = ['entity_id' => $id, 'customer_id' => $customerId,
            'is_default' => 0, 'is_selected' => 0] + $input->toArray();
        return $id;
    }

    public function update(int $customerId, int $vehicleId, VehicleInput $input): void
    {
        $this->requireLock($customerId);
        if (($this->rows[$vehicleId]['customer_id'] ?? null) === $customerId) {
            $this->rows[$vehicleId] = array_replace($this->rows[$vehicleId], $input->toArray());
        }
    }

    public function delete(int $customerId, int $vehicleId): void
    {
        $this->requireLock($customerId);
        if (($this->rows[$vehicleId]['customer_id'] ?? null) === $customerId) {
            unset($this->rows[$vehicleId]);
        }
    }

    public function setDefault(int $customerId, int $vehicleId): void
    {
        $this->flag($customerId, $vehicleId, 'is_default');
    }

    public function select(int $customerId, int $vehicleId): void
    {
        $this->flag($customerId, $vehicleId, 'is_selected');
    }

    private function flag(int $customerId, int $vehicleId, string $column): void
    {
        $this->requireLock($customerId);
        foreach ($this->rows as &$row) {
            if ($row['customer_id'] === $customerId) {
                $row[$column] = 0;
            }
        }
        unset($row);
        if ($this->failNextFlag) {
            $this->failNextFlag = false;
            throw new \RuntimeException('Simulated write failure after clearing previous flag.');
        }
        if (($this->rows[$vehicleId]['customer_id'] ?? null) === $customerId) {
            $this->rows[$vehicleId][$column] = 1;
        }
    }

    private function requireLock(int $customerId): void
    {
        if ($this->lockedCustomer !== $customerId) {
            throw new \LogicException('All garage writes must run in the owning customer transaction.');
        }
    }
}
