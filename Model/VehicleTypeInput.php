<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Exception\GarageException;

final class VehicleTypeInput
{
    public static function fromArray(array $input): array
    {
        $result = [];
        foreach (['code' => 100, 'make' => 100, 'model' => 100, 'variant' => 160] as $field => $limit) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || strlen($value) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new GarageException('Enter valid text for the vehicle definition.');
            }
            $value = trim((string) preg_replace('/\s+/u', ' ', $value));
            if (($field !== 'variant' && $value === '') || mb_strlen($value, 'UTF-8') > $limit) {
                throw new GarageException('Enter a code, make and model within the field length limits.');
            }
            $result[$field] = $value;
        }
        $result['code'] = strtolower($result['code']);
        if (!preg_match('/\A[a-z0-9][a-z0-9._-]{0,99}\z/D', $result['code'])) {
            throw new GarageException('Vehicle codes may contain letters, numbers, dots, hyphens and underscores.');
        }
        $year = filter_var($input['model_year'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1886, 'max_range' => 2100]]);
        if ($year === false) {
            throw new GarageException('Enter a model year between 1886 and 2100.');
        }
        $enabled = $input['is_enabled'] ?? '1';
        if (!in_array($enabled, ['0', '1', 0, 1], true)) {
            throw new GarageException('Enabled must be 0 or 1.');
        }
        return $result + ['model_year' => $year, 'is_enabled' => (int) $enabled,
            'market' => Market::validate($input['market'] ?? 'GB')] + Specification::fromArray($input);
    }
}
