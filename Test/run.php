<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Automotive\Garage\Exception\GarageException;
use Automotive\Garage\Model\Garage;
use Automotive\Garage\Model\VehicleInput;
use Automotive\Garage\Test\Support\InMemoryStorage;

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', received ' . var_export($actual, true));
    }
}

function rejects(callable $operation, string $exceptionClass = GarageException::class): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('Expected rejection: ' . $exceptionClass);
}

function details(string $registration = 'AB12 CDE'): array
{
    return ['registration' => $registration, 'make' => 'Ford', 'model' => 'Ranger'];
}

function choices(Garage $garage, int $customerId, ?int $default, ?int $selected): void
{
    $defaults = $selections = [];
    foreach ($garage->getList($customerId) as $vehicle) {
        if ($vehicle->isDefault) {
            $defaults[] = $vehicle->id;
        }
        if ($vehicle->isSelected) {
            $selections[] = $vehicle->id;
        }
    }
    same($default === null ? [] : [$default], $defaults);
    same($selected === null ? [] : [$selected], $selections);
}

$tests = [];
$tests['registration is normalised and vehicle text preserves Unicode'] = static function (): void {
    $input = VehicleInput::fromArray(['registration' => ' ab12-cde ', 'make' => '  Citroën  ',
        'model' => ' Grand   C4 ', 'nickname' => ' Family car ']);
    same('AB12CDE', $input->registration);
    same('Citroën', $input->make);
    same('Grand C4', $input->model);
    same('Family car', $input->nickname);
};
$tests['required fields and malformed input are rejected'] = static function (): void {
    foreach (['registration', 'make', 'model'] as $key) {
        $data = details();
        unset($data[$key]);
        rejects(static fn () => VehicleInput::fromArray($data));
        $data[$key] = ['unexpected'];
        rejects(static fn () => VehicleInput::fromArray($data));
    }
    foreach (['AB12<script>', 'ABCDEFGHIJK', '---', "AB12\nCDE"] as $registration) {
        rejects(static fn () => VehicleInput::fromArray(details($registration)));
    }
    rejects(static fn () => VehicleInput::fromArray(details() + ['nickname' => "\xC3\x28"]));
    rejects(static fn () => VehicleInput::fromArray(array_replace(details(), ['make' => str_repeat('é', 101)])));
};
$tests['first vehicle is selected and default; additions preserve choices'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    same(null, $garage->getSelected(1));
    $first = $garage->save(1, details());
    $garage->save(1, details('XY20 ABC'));
    same(2, count($garage->getList(1)));
    choices($garage, 1, $first->id, $first->id);
};
$tests['normalised duplicates are account scoped and edits cannot collide'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $first = $garage->save(1, details());
    rejects(static fn () => $garage->save(1, details(' ab12-cde ')));
    $second = $garage->save(1, details('XY20 ABC'));
    rejects(static fn () => $garage->save(1, details('ab12cde'), $second->id));
    $garage->save(1, details('ab12cde'), $first->id);
    $garage->save(2, details('AB12 CDE'));
    same(2, count($garage->getList(1)));
    same(1, count($garage->getList(2)));
};
$tests['submitted ownership and preference flags cannot be mass assigned'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $first = $garage->save(1, details());
    $garage->save(1, details('XY20 ABC') + ['customer_id' => 2, 'is_default' => 1, 'is_selected' => 1]);
    same([], $garage->getList(2));
    choices($garage, 1, $first->id, $first->id);
};
$tests['every operation rejects a different customer vehicle without changing choices'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $owner = $garage->save(1, details());
    $other = $garage->save(2, details('XY20 ABC'));
    foreach ([static fn () => $garage->get(2, $owner->id),
        static fn () => $garage->save(2, details('ZZ99 ZZZ'), $owner->id),
        static fn () => $garage->delete(2, $owner->id),
        static fn () => $garage->select(2, $owner->id),
        static fn () => $garage->setDefault(2, $owner->id)] as $operation) {
        rejects($operation);
        choices($garage, 1, $owner->id, $owner->id);
        choices($garage, 2, $other->id, $other->id);
    }
    same('AB12CDE', $garage->get(1, $owner->id)->registration);
};
$tests['unauthenticated customer identities are rejected for reads and mutations'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    foreach ([static fn () => $garage->getList(0), static fn () => $garage->getSelected(-1),
        static fn () => $garage->save(0, details()), static fn () => $garage->delete(0, 1),
        static fn () => $garage->select(0, 1), static fn () => $garage->setDefault(0, 1)] as $operation) {
        rejects($operation);
    }
};
$tests['edits retain identity, default and selection'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $first = $garage->save(1, details());
    $saved = $garage->save(1, ['registration' => 'XY20 ABC', 'make' => 'Toyota', 'model' => 'Hilux', 'nickname' => 'Work'], $first->id);
    same($first->id, $saved->id);
    same('Toyota', $saved->make);
    same('Work', $saved->nickname);
    choices($garage, 1, $first->id, $first->id);
};
$tests['selected and default choices change independently and survive a fresh service'] = static function (): void {
    $storage = new InMemoryStorage();
    $garage = new Garage($storage);
    $first = $garage->save(1, details());
    $second = $garage->save(1, details('XY20 ABC'));
    $garage->select(1, $second->id);
    choices($garage, 1, $first->id, $second->id);
    $garage->setDefault(1, $second->id);
    choices($garage, 1, $second->id, $second->id);
    same($second->id, (new Garage($storage))->getSelected(1)?->id);
};
$tests['removing selection falls back to the default'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $first = $garage->save(1, details());
    $second = $garage->save(1, details('XY20 ABC'));
    $garage->select(1, $second->id);
    $garage->delete(1, $second->id);
    choices($garage, 1, $first->id, $first->id);
};
$tests['removing default preserves a separately selected vehicle'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $first = $garage->save(1, details());
    $second = $garage->save(1, details('XY20 ABC'));
    $third = $garage->save(1, details('ZZ99 ZZZ'));
    $garage->select(1, $third->id);
    $garage->delete(1, $first->id);
    choices($garage, 1, $second->id, $third->id);
};
$tests['removing the selected default chooses oldest remaining; last deletion leaves no stale selection'] = static function (): void {
    $garage = new Garage(new InMemoryStorage());
    $first = $garage->save(1, details());
    $second = $garage->save(1, details('XY20 ABC'));
    $garage->delete(1, $first->id);
    choices($garage, 1, $second->id, $second->id);
    $garage->delete(1, $second->id);
    choices($garage, 1, null, null);
    same(null, $garage->getSelected(1));
    rejects(static fn () => $garage->delete(1, $second->id));
};
$tests['account limits allow edits, release capacity on deletion and are independent'] = static function (): void {
    $garage = new Garage(new InMemoryStorage(), 1);
    $vehicle = $garage->save(1, details());
    rejects(static fn () => $garage->save(1, details('XY20 ABC')));
    $garage->save(1, details('XY20 ABC'), $vehicle->id);
    $garage->save(2, details());
    $garage->delete(1, $vehicle->id);
    $garage->save(1, details('ZZ99 ZZZ'));
    same(1, count($garage->getList(1)));
};
$tests['partial preference write failures restore the previous state'] = static function (): void {
    $storage = new InMemoryStorage();
    $garage = new Garage($storage);
    $first = $garage->save(1, details());
    $second = $garage->save(1, details('XY20 ABC'));
    $storage->failNextFlag = true;
    rejects(static fn () => $garage->select(1, $second->id), RuntimeException::class);
    choices($garage, 1, $first->id, $first->id);
    $storage->failNextFlag = true;
    rejects(static fn () => $garage->delete(1, $first->id), RuntimeException::class);
    same(2, count($garage->getList(1)));
    choices($garage, 1, $first->id, $first->id);
};
$tests['failed first save rolls back the inserted vehicle as well as preferences'] = static function (): void {
    $storage = new InMemoryStorage();
    $garage = new Garage($storage);
    $storage->failNextFlag = true;
    rejects(static fn () => $garage->save(1, details()), RuntimeException::class);
    same([], $garage->getList(1));
    $saved = $garage->save(1, details());
    choices($garage, 1, $saved->id, $saved->id);
};

require __DIR__ . '/template-tests.php';
require __DIR__ . '/catalogue-tests.php';
require __DIR__ . '/market-tests.php';
require __DIR__ . '/admin-tests.php';

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo 'PASS ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $exception->getMessage() . PHP_EOL);
    }
}
echo count($tests) . ' tests, ' . $failures . ' failures.' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
