<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Integration;

use Automotive\Garage\Model\Garage;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/** @magentoAppArea frontend */
class AccountControllerTest extends AbstractController
{
    /** @magentoDbIsolation enabled */
    public function testGuestAccountRequestReturnsLoginRedirect(): void
    {
        Bootstrap::getObjectManager()->get(Session::class)->logout();
        $this->getRequest()->setMethod('GET');
        $this->dispatch('garage/account/index');
        self::assertSame(302, $this->getResponse()->getHttpResponseCode());
        self::assertStringContainsString('/customer/account/login',
            $this->getResponse()->getHeader('Location')->getFieldValue());
    }

    /** @magentoDbIsolation enabled */
    public function testValidPostSavesAgainstTheSignedInCustomer(): void
    {
        $customer = $this->customer('garage-http@example.test');
        $this->signIn($customer);
        $this->getRequest()->setMethod('POST')->setPostValue($this->postData() + ['customer_id' => 999999]);
        $this->dispatch('garage/vehicle/save');
        $vehicles = $this->garage()->getList((int) $customer->getId());
        self::assertCount(1, $vehicles);
        self::assertSame('AB12CDE', $vehicles[0]->registration);
        self::assertSame('Ford', $vehicles[0]->make);
    }

    /** @magentoDbIsolation enabled */
    public function testInvalidFormKeyBlocksAjaxMutations(): void
    {
        $customer = $this->customer('garage-csrf@example.test');
        $this->signIn($customer);
        $this->getRequest()->setMethod('POST')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->getRequest()->setPostValue(array_replace($this->postData(), ['form_key' => 'invalid']));
        $this->dispatch('garage/vehicle/save');
        self::assertSame([], $this->garage()->getList((int) $customer->getId()));
    }

    /** @magentoDbIsolation enabled */
    public function testDeleteCannotUseAnotherCustomersVehicle(): void
    {
        $owner = $this->customer('garage-http-owner@example.test');
        $other = $this->customer('garage-http-other@example.test');
        $vehicle = $this->garage()->save((int) $owner->getId(), $this->postData());
        $this->signIn($other);
        $this->getRequest()->setMethod('POST')->setPostValue([
            'form_key' => Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey(),
            'vehicle_id' => $vehicle->id, 'customer_id' => $owner->getId(),
        ]);
        $this->dispatch('garage/vehicle/delete');
        self::assertCount(1, $this->garage()->getList((int) $owner->getId()));
        self::assertSame([], $this->garage()->getList((int) $other->getId()));
    }

    /** @magentoDbIsolation enabled */
    public function testGetCannotDeleteVehicle(): void
    {
        $customer = $this->customer('garage-get@example.test');
        $this->signIn($customer);
        $vehicle = $this->garage()->save((int) $customer->getId(), $this->postData());
        $this->getRequest()->setMethod('GET')->setParam('vehicle_id', $vehicle->id);
        $this->dispatch('garage/vehicle/delete');
        self::assertCount(1, $this->garage()->getList((int) $customer->getId()));
    }

    private function customer(string $email): CustomerInterface
    {
        $objects = Bootstrap::getObjectManager();
        $customer = $objects->get(CustomerInterfaceFactory::class)->create();
        $websiteId = (int) $objects->get(StoreManagerInterface::class)->getStore()->getWebsiteId();
        $customer->setWebsiteId($websiteId)->setFirstname('Garage')->setLastname('HTTP')->setEmail($email);
        return $objects->get(CustomerRepositoryInterface::class)->save($customer);
    }

    private function signIn(CustomerInterface $customer): void
    {
        Bootstrap::getObjectManager()->get(Session::class)->setCustomerDataAsLoggedIn($customer);
    }

    private function garage(): Garage
    {
        return Bootstrap::getObjectManager()->get(Garage::class);
    }

    private function postData(): array
    {
        return ['form_key' => Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey(),
            'registration' => 'AB12 CDE', 'make' => 'Ford', 'model' => 'Ranger'];
    }
}
