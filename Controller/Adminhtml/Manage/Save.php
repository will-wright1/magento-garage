<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Adminhtml\Manage;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Csv\Importer;
use Automotive\Garage\Model\Fitment;
use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Automotive_Garage::manage';

    public function __construct(Action\Context $context, private readonly VehicleTypeStorage $types,
        private readonly Fitment $fitment, private readonly Importer $importer,
        private readonly Validator $formKeyValidator, private readonly LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Refresh the page and try again.'));
            return $redirect->setPath('garage/manage/index');
        }
        $params = [];
        try {
            $post = $this->getRequest()->getPostValue();
            if (!is_array($post)) {
                throw new GarageException('Invalid form submission.');
            }
            $operation = $post['operation'] ?? '';
            if ($operation === 'save_type') {
                $id = $post['entity_id'] ?? '';
                $validId = $id === '' ? null : filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($validId === false) {
                    throw new GarageException('Choose a valid vehicle definition.');
                }
                $saved = $this->types->save($post, $validId);
                $params['type_id'] = $saved->id;
                $this->messageManager->addSuccessMessage(__('Vehicle definition saved.'));
            } elseif ($operation === 'save_fitment' || $operation === 'clear_fitment') {
                $sku = $post['sku'] ?? null;
                if (!is_string($sku)) {
                    throw new GarageException('Enter an existing product SKU.');
                }
                $sku = trim($sku);
                $params['sku'] = $sku;
                if ($operation === 'clear_fitment') {
                    $this->fitment->clear($sku);
                } else {
                    $universal = $post['is_universal'] ?? '0';
                    if (!in_array($universal, ['0', '1'], true)) {
                        throw new GarageException('Choose a valid universal fitment setting.');
                    }
                    $universalMarket = $post['universal_market'] ?? 'GB';
                    if (!is_string($universalMarket)) {
                        throw new GarageException('Choose a valid universal product market.');
                    }
                    $this->fitment->save($sku, $post['vehicle_codes'] ?? '', $universal === '1', $universalMarket);
                }
                $this->messageManager->addSuccessMessage(__('Product fitment saved.'));
            } elseif ($operation === 'import_types' || $operation === 'import_fitments') {
                $stream = $this->uploadedCsv();
                try {
                    $count = $operation === 'import_types' ? $this->importer->importTypes($stream) : $this->importer->importFitments($stream);
                } finally {
                    fclose($stream);
                }
                $this->messageManager->addSuccessMessage(__('Imported %1 records.', $count));
            } else {
                throw new GarageException('Choose a valid garage operation.');
            }
        } catch (GarageException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->logger->error('Garage administration failed.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('The garage changes could not be saved. Check the logs and try again.'));
        }
        return $redirect->setPath('garage/manage/index', $params);
    }

    /** @return resource */
    private function uploadedCsv()
    {
        $file = $this->getRequest()->getFiles('csv');
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
            || (int) ($file['size'] ?? 0) > 1048576 || filesize($file['tmp_name']) > 1048576) {
            throw new GarageException('Choose an uploaded CSV file no larger than 1 MiB.');
        }
        $stream = fopen($file['tmp_name'], 'rb');
        if (!$stream) {
            throw new GarageException('The CSV file could not be read.');
        }
        return $stream;
    }
}
