<?php
declare(strict_types=1);

namespace Automotive\Garage\Block\Adminhtml;

use Automotive\Garage\Model\Adminhtml\GarageFilter;
use Automotive\Garage\Model\ResourceModel\CustomerGarageListing;
use Magento\Backend\Block\Template;
use Magento\Store\Model\StoreManagerInterface;

class CustomerGarages extends Template
{
    private ?array $result = null;

    public function __construct(Template\Context $context, private readonly CustomerGarageListing $listing,
        private readonly StoreManagerInterface $stores, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function filter(): GarageFilter
    {
        return new GarageFilter($this->getRequest()->getParams());
    }

    public function result(): array
    {
        return $this->result ??= $this->listing->search($this->filter());
    }

    public function websites(): array
    {
        return $this->stores->getWebsites();
    }
}
