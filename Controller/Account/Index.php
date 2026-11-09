<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Account;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Garage;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly Garage $garage,
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $url
    ) {
    }

    public function execute()
    {
        if (!$this->session->isLoggedIn()) {
            $this->session->setBeforeAuthUrl($this->url->getUrl('garage/account/index'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $id = $this->request->getParam('id');
        $vehicle = null;
        if ($id !== null) {
            $validId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            try {
                if ($validId === false) {
                    throw new GarageException('The vehicle could not be found in your garage.');
                }
                $vehicle = $this->garage->get((int) $this->session->getCustomerId(), $validId);
            } catch (GarageException $exception) {
                $this->messageManager->addErrorMessage(__($exception->getMessage()));
                return $this->redirectFactory->create()->setPath('garage/account/index');
            }
        }
        $formData = $this->session->getGarageFormData(true);
        $values = $vehicle?->toArray() ?? ['vehicle_id' => '', 'registration' => '', 'make' => '', 'model' => '', 'nickname' => ''];
        if ($vehicle !== null) {
            $values['registration'] = $vehicle->registrationDisplay ?: $vehicle->registration;
        }
        if (is_array($formData) && (string) ($formData['vehicle_id'] ?? '') === (string) $values['vehicle_id']) {
            $values = array_replace($values, $formData);
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Garage'));
        $block = $page->getLayout()->getBlock('garage.account');
        if ($block) {
            $block->setData('form_values', $values);
            $block->setData('editing', $vehicle !== null);
        }
        return $page;
    }
}
