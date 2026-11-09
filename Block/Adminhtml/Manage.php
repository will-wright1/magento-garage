<?php
declare(strict_types=1);

namespace Automotive\Garage\Block\Adminhtml;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Fitment;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\FormKey;

class Manage extends Template
{
    public function __construct(Template\Context $context, private readonly VehicleTypeStorage $types,
        private readonly Fitment $fitment, private readonly FormKey $garageFormKey, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getTypeRows(): array
    {
        return $this->types->getPage($this->page());
    }

    public function getTypeValues(): array
    {
        $id = filter_var($this->getRequest()->getParam('type_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id !== false ? ($this->types->get($id)?->toArray() ?? []) : [];
    }

    public function page(): int
    {
        return max(1, (int) $this->getRequest()->getParam('p', 1));
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->types->count() / 50));
    }

    public function getSku(): string
    {
        $sku = $this->getRequest()->getParam('sku', '');
        return is_string($sku) ? mb_substr($sku, 0, 256, 'UTF-8') : '';
    }

    public function getFitmentValues(): ?array
    {
        if ($this->getSku() === '') {
            return null;
        }
        try {
            return $this->fitment->describe($this->getSku());
        } catch (GarageException) {
            return null;
        }
    }

    public function getGarageFormKey(): string
    {
        return $this->garageFormKey->getFormKey();
    }
}
