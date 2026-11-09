<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Exception\GarageException;

/** Dimensions are stored in millimetres; labels never participate in identity matching. */
final class Specification
{
    public const TEXT = ['generation' => 'Generation / facelift', 'trim' => 'Trim', 'engine' => 'Engine',
        'body_style' => 'Cab / body style', 'transmission' => 'Transmission'];
    public const CHOICES = [
        'fuel_type' => ['' => 'Not specified', 'petrol' => 'Petrol', 'diesel' => 'Diesel', 'electric' => 'Electric', 'hybrid' => 'Hybrid', 'other' => 'Other'],
        'steering' => ['' => 'Not specified', 'LHD' => 'Left-hand drive', 'RHD' => 'Right-hand drive'],
        'drivetrain' => ['' => 'Not specified', 'FWD' => 'Front-wheel drive', 'RWD' => 'Rear-wheel drive', 'AWD' => 'All-wheel drive', '4WD' => 'Four-wheel drive'],
    ];
    public const CHOICE_LABELS = ['fuel_type' => 'Fuel type', 'steering' => 'Steering position', 'drivetrain' => 'Drivetrain'];
    public const LENGTHS = ['wheelbase_mm' => 'Wheelbase (mm)', 'bed_length_mm' => 'Bed length (mm)'];

    public static function fields(): array
    {
        return array_merge(array_keys(self::TEXT), array_keys(self::CHOICES), array_keys(self::LENGTHS));
    }

    public static function fromArray(array $input): array
    {
        $result = [];
        foreach (self::TEXT as $field => $label) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || strlen($value) > 500
                || mb_strlen($value, 'UTF-8') > 80 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new GarageException('Enter vehicle specification text within 80 characters.');
            }
            $result[$field] = trim((string) preg_replace('/\s+/u', ' ', $value));
        }
        foreach (self::CHOICES as $field => $choices) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || !array_key_exists($value, $choices)) {
                throw new GarageException('Choose a valid fuel type, steering position and drivetrain.');
            }
            $result[$field] = $value;
        }
        foreach (self::LENGTHS as $field => $label) {
            $raw = $input[$field] ?? null;
            $value = $raw === null || $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 50000]]);
            if ($value === false) {
                throw new GarageException('Enter vehicle dimensions as whole millimetres between 1 and 50000.');
            }
            $result[$field] = $value;
        }
        return $result;
    }

    public static function summary(array $data, string $market): string
    {
        $parts = [];
        foreach (self::TEXT as $field => $label) {
            if (($data[$field] ?? '') !== '') {
                $parts[] = $data[$field];
            }
        }
        foreach (self::CHOICES as $field => $choices) {
            if (($data[$field] ?? '') !== '') {
                $parts[] = (string) __($choices[$data[$field]]);
            }
        }
        foreach (self::LENGTHS as $field => $label) {
            if (!empty($data[$field])) {
                $length = $market === 'US' ? number_format((int) $data[$field] / 25.4, 2, '.', '') . ' in (' . $data[$field] . ' mm)' : $data[$field] . ' mm';
                $parts[] = (string) __($field === 'bed_length_mm' ? 'Bed: %1' : 'Wheelbase: %1', $length);
            }
        }
        return implode(' · ', $parts);
    }
}
