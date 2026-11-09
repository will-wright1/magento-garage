<?php
declare(strict_types=1);

namespace Automotive\Garage\Block\Shop;

use Automotive\Garage\Model\Fitment;
use Automotive\Garage\Model\ShopContext;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Config;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\Url\Helper\Data;

class ProductList extends ListProduct
{
    public function __construct(Context $context, PostHelper $postDataHelper, Resolver $layerResolver,
        CategoryRepositoryInterface $categoryRepository, Data $urlHelper,
        private readonly CollectionFactory $collections, private readonly Config $catalogConfig,
        private readonly Visibility $visibility, private readonly Stock $stock,
        private readonly Fitment $fitment, private readonly ShopContext $shop, array $data = [])
    {
        parent::__construct($context, $postDataHelper, $layerResolver, $categoryRepository, $urlHelper, $data);
        $this->setAvailableOrders(['name' => __('Name'), 'price' => __('Price')]);
        $this->setSortBy('name');
        $this->setDefaultDirection('asc');
        $this->setModes(['grid' => __('Grid'), 'list' => __('List')]);
    }

    protected function _getProductCollection()
    {
        if ($this->_productCollection === null) {
            $storeId = (int) $this->_storeManager->getStore()->getId();
            $collection = $this->collections->create()->setStoreId($storeId)->addStoreFilter($storeId)
                ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInCatalogIds()])
                ->addMinimalPrice()->addFinalPrice()->addTaxPercents();
            $this->stock->addIsInStockFilterToCollection($collection);
            $this->fitment->applyToCollection($collection, $this->shop->type()->id);
            $this->_productCollection = $collection;
        }
        return $this->_productCollection;
    }
}
