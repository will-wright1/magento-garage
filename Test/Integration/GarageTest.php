<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Integration;

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Garage;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/** @magentoAppArea frontend */
class GarageTest extends TestCase
{
    private Garage $garage;
    private CustomerRepositoryInterface $customers;
    private CustomerInterface $owner;
    private CustomerInterface $other;

    protected function setUp(): void
    {
        parent::setUp();
        $objects = Bootstrap::getObjectManager();
        $this->garage = $objects->create(Garage::class);
        $this->customers = $objects->get(CustomerRepositoryInterface::class);
        $this->owner = $this->createCustomer('garage-owner@example.test');
        $this->other = $this->createCustomer('garage-other@example.test');
    }

    /** @magentoDbIsolation enabled */
    public function testPersistenceAndPreferences(): void
    {
        $id = (int) $this->owner->getId();
        $first = $this->garage->save($id, $this->details('AB12 CDE'));
        $second = $this->garage->save($id, $this->details('XY20 ABC'));
        $this->garage->select($id, $second->id);
        $fresh = Bootstrap::getObjectManager()->create(Garage::class);
        self::assertSame($second->id, $fresh->getSelected($id)?->id);
        self::assertTrue($fresh->get($id, $first->id)->isDefault);
        $fresh->setDefault($id, $second->id);
        $updated = $fresh->save($id, ['registration' => 'XY20 ABC', 'make' => 'Toyota', 'model' => 'Hilux'], $second->id);
        self::assertSame('Toyota', $updated->make);
        self::assertTrue($updated->isDefault);
        $fresh->delete($id, $second->id);
        self::assertSame($first->id, $fresh->getSelected($id)?->id);
        self::assertTrue($fresh->get($id, $first->id)->isDefault);
    }

    /** @magentoDbIsolation enabled */
    public function testCustomerScopeAcrossAllOperations(): void
    {
        $ownerId = (int) $this->owner->getId();
        $otherId = (int) $this->other->getId();
        $vehicle = $this->garage->save($ownerId, $this->details('AB12 CDE'));
        self::assertSame([], $this->garage->getList($otherId));
        foreach ([fn () => $this->garage->get($otherId, $vehicle->id),
            fn () => $this->garage->save($otherId, $this->details('ZZ99 ZZZ'), $vehicle->id),
            fn () => $this->garage->delete($otherId, $vehicle->id),
            fn () => $this->garage->select($otherId, $vehicle->id),
            fn () => $this->garage->setDefault($otherId, $vehicle->id)] as $operation) {
            try {
                $operation();
                self::fail('Cross-customer access must fail.');
            } catch (GarageException $exception) {
                self::assertSame('The vehicle could not be found in your garage.', $exception->getMessage());
            }
        }
        self::assertSame('AB12CDE', $this->garage->get($ownerId, $vehicle->id)->registration);
        $this->garage->save($otherId, $this->details('AB12 CDE'));
        self::assertCount(1, $this->garage->getList($otherId));
    }

    /** @magentoDbIsolation enabled */
    public function testDatabaseUniqueConstraint(): void
    {
        $id = (int) $this->owner->getId();
        $this->garage->save($id, $this->details('AB12 CDE'));
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $this->expectException(DuplicateException::class);
        $resource->getConnection()->insert($resource->getTableName('automotive_garage_vehicle'),
            ['customer_id' => $id, 'registration' => 'AB12CDE', 'make' => 'Ford', 'model' => 'Ranger', 'nickname' => '']);
    }

    /** @magentoDbIsolation enabled */
    public function testCustomerDeletionCascades(): void
    {
        $id = (int) $this->owner->getId();
        $this->garage->save($id, $this->details('AB12 CDE'));
        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $wasSecureArea = $registry->registry('isSecureArea');
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        try {
            $this->customers->deleteById($id);
        } finally {
            $registry->unregister('isSecureArea');
            if ($wasSecureArea !== null) {
                $registry->register('isSecureArea', $wasSecureArea);
            }
        }
        self::assertSame([], $this->garage->getList($id));
    }

    private function createCustomer(string $email): CustomerInterface
    {
        $objects = Bootstrap::getObjectManager();
        $customer = $objects->get(CustomerInterfaceFactory::class)->create();
        $websiteId = (int) $objects->get(StoreManagerInterface::class)->getStore()->getWebsiteId();
        $customer->setWebsiteId($websiteId)->setFirstname('Garage')->setLastname('Test')->setEmail($email);
        return $this->customers->save($customer);
    }

    private function details(string $registration): array
    {
        return ['registration' => $registration, 'make' => 'Ford', 'model' => 'Ranger'];
    }
}
