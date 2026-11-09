<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\Csv;

use Automotive\Garage\Exception\GarageException;

final class Reader
{
    /** @param resource $stream */
    public function read($stream, array $headers, array $optionalHeaders = []): array
    {
        if (!is_resource($stream)) {
            throw new GarageException('Choose a readable CSV file.');
        }
        $head = fgetcsv($stream, 0, ',', '"', '');
        if ($head === false) {
            throw new GarageException('The CSV file is empty.');
        }
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
        $valid = $optionalHeaders === [] ? $head === $headers
            : count($head) === count(array_unique($head)) && array_diff($headers, $head) === []
                && array_diff($head, array_merge($headers, $optionalHeaders)) === [];
        if (!$valid) {
            throw new GarageException('The CSV headers do not match the documented format.');
        }
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            if (count($values) !== count($head) || in_array(null, $values, true)) {
                throw new GarageException('A CSV row has the wrong number of columns. Check line ' . $line . '.');
            }
            if (count($rows) >= 5000) {
                throw new GarageException('Import at most 5,000 CSV rows at a time.');
            }
            $rows[] = array_combine($head, $values);
        }
        if ($rows === []) {
            throw new GarageException('The CSV must contain at least one data row.');
        }
        return $rows;
    }
}
