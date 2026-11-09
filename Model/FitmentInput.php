<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Exception\GarageException;

final class FitmentInput
{
    public static function codes(mixed $value, bool $universal): array
    {
        if (!is_string($value) || strlen($value) > 500000) {
            throw new GarageException('Enter vehicle codes separated by commas or new lines.');
        }
        $codes = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[,\r\n]+/', strtolower($value)) ?: []),
            static fn (string $code): bool => $code !== ''
        )));
        foreach ($codes as $code) {
            if (!preg_match('/\A[a-z0-9][a-z0-9._-]{0,99}\z/D', $code)) {
                throw new GarageException('A vehicle code is invalid.');
            }
        }
        if ($universal && $codes !== []) {
            throw new GarageException('Choose universal fitment or specific vehicle codes, rather than both.');
        }
        if (!$universal && $codes === []) {
            throw new GarageException('Enter at least one vehicle code or mark the product as universal.');
        }
        return $codes;
    }
}
