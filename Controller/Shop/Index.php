<?php
declare(strict_types=1);

namespace Automotive\Garage\Controller\Shop;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\ShopContext;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(private readonly Session $session, private readonly ShopContext $shop,
        private readonly PageFactory $pages, private readonly RedirectFactory $redirects,
        private readonly ManagerInterface $messages, private readonly UrlInterface $url)
    {
    }

    public function execute()
    {
        if (!$this->session->isLoggedIn()) {
            $this->session->setBeforeAuthUrl($this->url->getUrl('garage/shop/index'));
            return $this->redirects->create()->setPath('customer/account/login');
        }
        try {
            $type = $this->shop->type();
        } catch (GarageException $exception) {
            $this->messages->addErrorMessage(__($exception->getMessage()));
            return $this->redirects->create()->setPath('garage/account/index');
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set(__('Parts for %1', $type->label()));
        return $page;
    }
}
