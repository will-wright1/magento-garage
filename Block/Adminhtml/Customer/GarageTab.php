<?php
declare(strict_types=1);

namespace Automotive\Garage\Block\Adminhtml\Customer;

use Automotive\Garage\Model\Garage;
use Magento\Backend\Block\Template;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabInterface;

class GarageTab extends Template implements TabInterface
{
    public function __construct(Template\Context $context, private readonly Registry $registry,
        private readonly Garage $garage, private readonly AuthorizationInterface $authorization, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function customerId(): int { return (int) $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID); }
    public function getTabLabel() { return __('Garage'); }
    public function getTabTitle() { return __('Customer Garage'); }
    public function canShowTab() { return $this->customerId() > 0 && $this->authorization->isAllowed('Automotive_Garage::customer_view'); }
    public function isHidden() { return !$this->canShowTab(); }
    public function getTabClass() { return ''; }
    public function getTabUrl() { return ''; }
    public function isAjaxLoaded() { return false; }
    public function vehicles(): array { return $this->canShowTab() ? $this->garage->getList($this->customerId()) : []; }

    protected function _toHtml()
    {
        return $this->canShowTab() ? parent::_toHtml() : '';
    }
}
