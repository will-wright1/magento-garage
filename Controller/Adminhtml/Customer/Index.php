<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Automotive_Garage::customer_view';

    public function __construct(Action\Context $context, private readonly PageFactory $pages)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pages->create();
        $page->setActiveMenu(self::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend(__('Customer Garages'));
        return $page;
    }
}
