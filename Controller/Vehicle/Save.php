<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Vehicle;

class Save extends AbstractPost
{
    protected function perform(int $customerId): void
    {
        $data = $this->request->getPostValue();
        $this->garage->save($customerId, is_array($data) ? $data : [], $this->vehicleId(true));
        $this->session->unsGarageFormData();
    }

    protected function successMessage(): string
    {
        return 'Your vehicle has been saved.';
    }

    protected function rememberForm(): void
    {
        $values = [];
        foreach (['vehicle_id', 'registration', 'make', 'model', 'nickname', 'vehicle_type_id', 'model_year', 'variant',
            'vehicle_market', 'registration_country', 'registration_region', 'vin', 'vin_format'] as $key) {
            $value = $this->request->getPostValue($key);
            $values[$key] = is_string($value) && mb_check_encoding($value, 'UTF-8')
                ? mb_substr($value, 0, $key === 'variant' ? 160 : 100, 'UTF-8') : '';
        }
        $this->session->setGarageFormData($values);
    }

    protected function errorParameters(): array
    {
        $id = filter_var($this->request->getPostValue('vehicle_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? [] : ['id' => $id];
    }
}
