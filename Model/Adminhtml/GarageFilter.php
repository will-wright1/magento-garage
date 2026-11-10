<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\Adminhtml;

/** Bounded, scalar-only filters shared by the list and its pagination links. */
final class GarageFilter
{
    public readonly string $query;
    public readonly string $market;
    public readonly ?int $websiteId;
    public readonly int $page;

    public function __construct(array $input)
    {
        $this->query = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 100, 'UTF-8') : '';
        $this->market = in_array($input['market'] ?? null, ['GB', 'US'], true) ? $input['market'] : '';
        $website = filter_var($input['website_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $this->websiteId = $website === false ? null : $website;
        $page = filter_var($input['p'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        $this->page = $page === false ? 1 : $page;
    }

    public function params(int $page = 1): array
    {
        return ['q' => $this->query, 'market' => $this->market, 'website_id' => $this->websiteId, 'p' => $page];
    }
}
