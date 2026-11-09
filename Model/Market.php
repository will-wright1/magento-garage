<?php
declare(strict_types=1);

namespace Automotive\Garage\Model;

use Automotive\Garage\Exception\GarageException;

/** Vehicle specification market and plate jurisdiction are deliberately independent. */
final class Market
{
    public const LABELS = ['GB' => 'United Kingdom', 'US' => 'United States'];
    public const US_REGIONS = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
        'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
        'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'AS' => 'American Samoa', 'GU' => 'Guam', 'MP' => 'Northern Mariana Islands', 'PR' => 'Puerto Rico',
        'VI' => 'US Virgin Islands', 'AA' => 'Armed Forces Americas', 'AE' => 'Armed Forces Europe',
        'AP' => 'Armed Forces Pacific',
    ];

    public static function validate(mixed $value): string
    {
        if (!is_string($value) || !isset(self::LABELS[$value])) {
            throw new GarageException('Choose the United Kingdom or United States.');
        }
        return $value;
    }

    public static function levels(string $market): array
    {
        return self::validate($market) === 'US' ? ['year', 'make', 'model', 'variant'] : ['make', 'model', 'year', 'variant'];
    }

    /** The same predecessor rules drive the public endpoint and both selector orders. */
    public static function filters(string $market, string $level, string $make, string $model, ?int $year): array
    {
        $levels = self::levels($market);
        $index = array_search($level, $levels, true);
        if ($index === false) {
            throw new GarageException('Unknown vehicle selector level.');
        }
        $values = ['make' => $make, 'model' => $model, 'year' => $year];
        $filters = ['market' => $market];
        foreach (array_slice($levels, 0, $index) as $previous) {
            if ($values[$previous] === '' || $values[$previous] === null) {
                throw new GarageException('Choose the preceding vehicle options first.');
            }
            $filters[$previous === 'year' ? 'model_year' : $previous] = $values[$previous];
        }
        return $filters;
    }
}
