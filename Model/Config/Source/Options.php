<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\Config\Source;

use Automotive\Garage\Model\Market;
use Magento\Framework\Data\OptionSourceInterface;

class Options implements OptionSourceInterface
{
    public function __construct(private readonly string $kind = 'market')
    {
    }

    public function toOptionArray(): array
    {
        $options = match ($this->kind) {
            'plate' => ['auto' => 'UK required; US optional', 'required' => 'Required for both countries', 'optional' => 'Optional for both countries'],
            'badge' => ['auto' => 'Yellow for UK plates; neutral for US plates', 'yellow' => 'Yellow', 'neutral' => 'Neutral'],
            default => Market::LABELS,
        };
        $result = [];
        foreach ($options as $value => $label) {
            $result[] = ['value' => $value, 'label' => __($label)];
        }
        return $result;
    }
}
