<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Vehicle;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Garage;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractPost implements HttpPostActionInterface
{
    public function __construct(
        protected readonly Session $session,
        protected readonly Http $request,
        protected readonly Garage $garage,
        private readonly RedirectFactory $redirectFactory,
        private readonly Validator $formKeyValidator,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $url
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->session->isLoggedIn()) {
            $this->session->setBeforeAuthUrl($this->url->getUrl('garage/account/index'));
            return $redirect->setPath('customer/account/login');
        }
        if (!$this->request->isPost() || !$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Your session has expired. Refresh the page and try again.'));
            return $redirect->setPath('garage/account/index');
        }
        try {
            $this->perform((int) $this->session->getCustomerId());
            $this->messageManager->addSuccessMessage(__($this->successMessage()));
        } catch (GarageException $exception) {
            $this->rememberForm();
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
            return $redirect->setPath('garage/account/index', $this->errorParameters());
        } catch (\Throwable $exception) {
            $this->rememberForm();
            $this->logger->error('Unable to update the customer garage.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('We could not update your garage. Please try again.'));
            return $redirect->setPath('garage/account/index', $this->errorParameters());
        }
        return $redirect->setPath($this->successPath());
    }

    protected function vehicleId(bool $optional = false): ?int
    {
        $id = $this->request->getPostValue('vehicle_id');
        if ($optional && ($id === null || $id === '')) {
            return null;
        }
        $validId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validId === false) {
            throw new GarageException('The vehicle could not be found in your garage.');
        }
        return $validId;
    }

    protected function rememberForm(): void
    {
    }

    protected function errorParameters(): array
    {
        return [];
    }

    protected function successPath(): string
    {
        return 'garage/account/index';
    }

    abstract protected function perform(int $customerId): void;

    abstract protected function successMessage(): string;
}
