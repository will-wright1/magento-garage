<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Adminhtml\Customer;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Adminhtml\CustomerGarage;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Automotive_Garage::customer_view';

    public function __construct(Action\Context $context, private readonly PageFactory $pages,
        private readonly CustomerGarage $management)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $customer = $this->management->customer(CustomerGarage::positiveId($this->getRequest()->getParam('customer_id')));
            $this->management->storeId($customer);
        } catch (GarageException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
            return $this->resultRedirectFactory->create()->setPath('garage/customer/index');
        }
        $page = $this->pages->create();
        $page->setActiveMenu(self::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend(__('Customer Garage'));
        return $page;
    }
}
