<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\Adminhtml;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Garage;
use Automotive\Garage\Model\GarageFactory;
use Automotive\Garage\Model\Settings;
use Automotive\Garage\Model\SettingsFactory;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/** Admin entry point: resolve a real owner, then reuse all customer garage invariants. ACL belongs to the caller. */
class CustomerGarage
{
    public function __construct(private readonly CustomerRepositoryInterface $customers,
        private readonly StoreManagerInterface $stores, private readonly GarageFactory $garages,
        private readonly SettingsFactory $settingsFactory, private readonly VehicleTypeStorage $types)
    {
    }

    public static function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new GarageException('Choose a valid customer or vehicle.');
        }
        return $id;
    }

    public function customer(int $id): CustomerInterface
    {
        try {
            return $this->customers->getById(self::positiveId($id));
        } catch (NoSuchEntityException) {
            throw new GarageException('The customer could not be found.');
        }
    }

    public function storeId(CustomerInterface $customer): int
    {
        // Customer store_id can be zero or refer to another website after a customer move.
        $storeId = (int) $customer->getStoreId();
        if ($storeId > 0) {
            try {
                $store = $this->stores->getStore($storeId);
                if ((int) $store->getWebsiteId() === (int) $customer->getWebsiteId()) {
                    return $storeId;
                }
            } catch (NoSuchEntityException) {
                // Use the owner's website default below.
            }
        }
        $store = $this->stores->getWebsite((int) $customer->getWebsiteId())->getDefaultStore();
        if (!$store || !(int) $store->getId()) {
            throw new GarageException('Configure a default store view for this customer’s website first.');
        }
        return (int) $store->getId();
    }

    public function settings(CustomerInterface $customer): Settings
    {
        return $this->settingsFactory->create(['storeId' => $this->storeId($customer)]);
    }

    public function garage(CustomerInterface $customer): Garage
    {
        // Explicit store scope avoids accidentally applying the admin store's UK defaults to US customers.
        return $this->garages->create(['settings' => $this->settings($customer)]);
    }

    public function execute(int $customerId, array $post): void
    {
        $customer = $this->customer($customerId);
        $garage = $this->garage($customer);
        $operation = $post['operation'] ?? '';
        $rawId = $post['vehicle_id'] ?? '';
        $vehicleId = $rawId === '' ? null : self::positiveId($rawId);
        if ($operation === 'save') {
            $code = $post['vehicle_code'] ?? '';
            if (!is_string($code) || strlen($code) > 100) {
                throw new GarageException('Enter a valid vehicle catalogue code, or leave it blank for manual entry.');
            }
            $type = trim($code) !== '' ? $this->types->getByCode(trim($code)) : null;
            if (trim($code) !== '' && (!$type || !$type->enabled)) {
                throw new GarageException('Choose an enabled vehicle catalogue code, or leave it blank for manual entry.');
            }
            // Do not accept a posted internal type ID or preference/owner flags.
            $data = self::formData($post);
            unset($data['vehicle_code']);
            $data['vehicle_type_id'] = $type?->id;
            $garage->save($customerId, $data, $vehicleId);
            return;
        }
        if ($vehicleId === null) {
            throw new GarageException('Choose a vehicle from this customer’s garage.');
        }
        match ($operation) {
            'delete' => $garage->delete($customerId, $vehicleId),
            'select' => $garage->select($customerId, $vehicleId),
            'default' => $garage->setDefault($customerId, $vehicleId),
            default => throw new GarageException('Choose a valid garage operation.'),
        };
    }

    /** Keep only editable vehicle fields; identity and preference flags are never accepted. */
    public static function formData(array $post): array
    {
        return array_intersect_key($post, array_flip(['registration', 'make', 'model', 'nickname', 'model_year', 'variant',
            'vehicle_market', 'registration_country', 'registration_region', 'vin', 'vin_format', 'vehicle_code']));
    }
}
