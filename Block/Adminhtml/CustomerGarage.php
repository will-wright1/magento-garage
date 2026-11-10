<?php
declare(strict_types=1);

namespace Automotive\Garage\Block\Adminhtml;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Adminhtml\CustomerGarage as Management;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Magento\Backend\Block\Template;
use Magento\Backend\Model\Session;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;

class CustomerGarage extends Template
{
    private ?CustomerInterface $customer = null;
    private ?array $values = null;

    public function __construct(Template\Context $context, private readonly Management $management,
        private readonly VehicleTypeStorage $types, private readonly AuthorizationInterface $authorization,
        private readonly FormKey $garageFormKey, private readonly Session $backendSession,
        private readonly StoreManagerInterface $stores, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function customer(): CustomerInterface
    {
        return $this->customer ??= $this->management->customer(Management::positiveId($this->getRequest()->getParam('customer_id')));
    }

    public function vehicles(): array
    {
        return $this->management->garage($this->customer())->getList((int) $this->customer()->getId());
    }

    public function canManage(): bool
    {
        return $this->authorization->isAllowed('Automotive_Garage::customer_manage');
    }

    public function canEditCustomer(): bool
    {
        return $this->authorization->isAllowed('Magento_Customer::manage');
    }

    public function garageFormKey(): string
    {
        return $this->garageFormKey->getFormKey();
    }

    public function storeLabel(): string
    {
        $customer = $this->customer();
        $store = $this->stores->getStore($this->management->storeId($customer));
        return $this->stores->getWebsite((int) $customer->getWebsiteId())->getName() . ' / ' . $store->getName();
    }

    public function formValues(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }
        $settings = $this->management->settings($this->customer());
        $this->values = ['vehicle_market' => $settings->defaultMarket(), 'registration_country' => $settings->defaultCountry(),
            'vin_format' => 'standard'];
        if (!$this->canManage()) {
            return $this->values;
        }
        $rawId = $this->getRequest()->getParam('vehicle_id');
        if ($rawId !== null && $rawId !== '') {
            try {
                $vehicle = $this->management->garage($this->customer())->get((int) $this->customer()->getId(), Management::positiveId($rawId));
                $this->values = $vehicle->toArray();
                $this->values['registration'] = $vehicle->registrationDisplay ?: $vehicle->registration;
                $this->values['vehicle_code'] = $vehicle->vehicleTypeId !== null ? ($this->types->get($vehicle->vehicleTypeId)?->code ?? '') : '';
            } catch (GarageException) {
                // Never expose another customer's vehicle, even through a crafted edit URL.
                $this->values['invalid_vehicle'] = true;
                return $this->values;
            }
        }
        $failed = $this->backendSession->getGarageVehicleForm(true);
        if (is_array($failed) && ($failed['customer_id'] ?? null) === (int) $this->customer()->getId()
            && ($failed['vehicle_id'] ?? null) === ($this->values['vehicle_id'] ?? null)) {
            $this->values = array_replace($this->values, $failed['values']);
        }
        return $this->values;
    }

    public function plateRequirement(): string
    {
        return $this->management->settings($this->customer())->plateRequirement();
    }
}
