<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Adminhtml\Customer;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Adminhtml\CustomerGarage;
use Magento\Backend\App\Action;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Automotive_Garage::customer_manage';

    public function __construct(Action\Context $context, private readonly CustomerGarage $management,
        private readonly Validator $formKeyValidator, private readonly BackendSession $backendSession,
        private readonly Session $authSession, private readonly LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Refresh the page and try again.'));
            return $redirect->setPath('garage/customer/index');
        }
        $customerId = null;
        $vehicleId = null;
        $post = [];
        $this->backendSession->unsGarageVehicleForm();
        try {
            $post = $this->getRequest()->getPostValue();
            if (!is_array($post)) {
                throw new GarageException('Invalid form submission.');
            }
            $customerId = (int) $this->management->customer(CustomerGarage::positiveId($post['customer_id'] ?? null))->getId();
            $vehicleId = ($post['vehicle_id'] ?? '') === '' ? null : CustomerGarage::positiveId($post['vehicle_id']);
            $this->management->execute($customerId, $post);
            $this->logger->info('Customer garage changed by administrator.', [
                'admin_id' => (int) $this->authSession->getUser()?->getId(), 'customer_id' => $customerId,
                'vehicle_id' => $vehicleId, 'operation' => $post['operation'],
            ]);
            $this->messageManager->addSuccessMessage(__('Customer garage updated.'));
        } catch (GarageException $exception) {
            if ($customerId !== null && ($post['operation'] ?? '') === 'save') {
                $values = CustomerGarage::formData($post);
                $values = array_map(static fn ($value) => is_string($value) ? mb_substr($value, 0, 200, 'UTF-8') : '', $values);
                $this->backendSession->setGarageVehicleForm(['customer_id' => $customerId, 'vehicle_id' => $vehicleId, 'values' => $values]);
            }
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
            return $redirect->setPath($customerId ? 'garage/customer/view' : 'garage/customer/index',
                $customerId ? ['customer_id' => $customerId, 'vehicle_id' => $vehicleId] : []);
        } catch (\Throwable $exception) {
            $this->logger->error('Customer garage administration failed.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The garage changes could not be saved. Check the logs and try again.'));
        }
        return $redirect->setPath($customerId ? 'garage/customer/view' : 'garage/customer/index',
            $customerId ? ['customer_id' => $customerId] : []);
    }
}
