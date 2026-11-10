<?php
declare(strict_types=1);

namespace Automotive\Garage\Model\ResourceModel;

use Automotive\Garage\Model\Adminhtml\GarageFilter;
use Magento\Framework\App\ResourceConnection;

/** Read-only admin projection. Mutations must go through the customer-scoped Garage service. */
class CustomerGarageListing
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function search(GarageFilter $filter): array
    {
        $db = $this->resource->getConnection();
        $select = $db->select()->from(['v' => $this->resource->getTableName('automotive_garage_vehicle')], [])
            ->joinInner(['c' => $this->resource->getTableName('customer_entity')], 'c.entity_id = v.customer_id', [])
            ->joinInner(['w' => $this->resource->getTableName('store_website')], 'w.website_id = c.website_id', []);
        if ($filter->query !== '') {
            // Treat wildcard characters literally, and let the adapter quote every value.
            $like = '%' . addcslashes($filter->query, '\\%_') . '%';
            $clauses = [];
            foreach (['c.email', 'c.firstname', 'c.lastname', 'v.registration', 'v.registration_display', 'v.make', 'v.model', 'v.nickname'] as $column) {
                $clauses[] = $db->quoteInto($column . ' LIKE ?', $like);
            }
            $clauses[] = $db->quoteInto("CONCAT_WS(' ', c.firstname, c.lastname) LIKE ?", $like);
            $clauses[] = $db->quoteInto("CONCAT_WS(' ', v.make, v.model) LIKE ?", $like);
            $select->where('(' . implode(' OR ', $clauses) . ')');
        }
        if ($filter->market !== '') {
            $select->where('v.vehicle_market = ?', $filter->market);
        }
        if ($filter->websiteId !== null) {
            $select->where('c.website_id = ?', $filter->websiteId);
        }
        $total = (int) $db->fetchOne((clone $select)->columns(['total' => new \Zend_Db_Expr('COUNT(*)')]));
        $pages = max(1, (int) ceil($total / 50));
        $page = min($filter->page, $pages);
        // Never load a full VIN into the cross-customer list.
        $select->columns(['v.entity_id', 'v.customer_id', 'v.registration_display', 'v.registration', 'v.make', 'v.model',
            'v.model_year', 'v.variant', 'v.nickname', 'v.vehicle_market', 'v.registration_country', 'v.registration_region',
            'v.is_default', 'v.is_selected', 'v.updated_at', 'c.email', 'c.firstname', 'c.lastname', 'website_name' => 'w.name'])
            ->order('v.entity_id DESC')->limitPage($page, 50);
        return ['rows' => $db->fetchAll($select), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
