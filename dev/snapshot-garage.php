<?php
declare(strict_types=1);

// Export only synthetic data before a disposable upgrade so market-smoke.php can verify preservation.
$root = $argv[1] ?? '';
$file = $argv[2] ?? '';
if (!is_file($root . '/app/bootstrap.php') || $file === '' || ($argv[3] ?? '') !== '--disposable' || file_exists($file)) {
    fwrite(STDERR, "Usage: php dev/snapshot-garage.php /path/to/magento /new/snapshot.json --disposable\n");
    exit(1);
}
require $root . '/app/bootstrap.php';
$objects = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$resource = $objects->get(\Magento\Framework\App\ResourceConnection::class);
$data = [];
foreach (['automotive_garage_vehicle', 'automotive_garage_vehicle_type', 'automotive_garage_product_rule', 'automotive_garage_product_fitment'] as $table) {
    $data[$table] = $resource->getConnection()->fetchAll($resource->getConnection()->select()->from($resource->getTableName($table)));
}
if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
    throw new RuntimeException('Could not write the disposable snapshot.');
}
echo 'Disposable garage snapshot saved.' . PHP_EOL;
