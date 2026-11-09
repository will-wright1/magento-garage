<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Catalog;

use Automotive\Garage\Model\ResourceModel\VehicleTypeStorage;
use Automotive\Garage\Model\Market;
use Automotive\Garage\Exception\GarageException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Options implements HttpGetActionInterface
{
    public function __construct(private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory, private readonly VehicleTypeStorage $types)
    {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $params = [];
        foreach (['level', 'make', 'model'] as $key) {
            $value = $this->request->getParam($key, '');
            if (!is_string($value) || strlen($value) > 400 || !mb_check_encoding($value, 'UTF-8')) {
                return $result->setHttpResponseCode(400)->setData(['options' => []]);
            }
            $params[$key] = $value;
        }
        if (!in_array($params['level'], ['make', 'model', 'year', 'variant'], true)) {
            return $result->setHttpResponseCode(400)->setData(['options' => []]);
        }
        $year = filter_var($this->request->getParam('year'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1886, 'max_range' => 2100]]);
        try {
            $market = Market::validate($this->request->getParam('market', 'GB'));
            return $result->setData(['options' => $this->types->options(
                $params['level'], $params['make'], $params['model'], $year === false ? null : $year, $market
            )]);
        } catch (GarageException) {
            return $result->setHttpResponseCode(400)->setData(['options' => []]);
        }
    }
}
