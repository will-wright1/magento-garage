<?php
declare(strict_types=1);

namespace Automotive\Garage\Block\Shop;

use Automotive\Garage\Model\ShopContext;
use Magento\Framework\View\Element\Template;

class Summary extends Template
{
    public function __construct(Template\Context $context, private readonly ShopContext $shop, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getVehicleName(): string
    {
        return $this->shop->vehicle()->displayName();
    }
}
