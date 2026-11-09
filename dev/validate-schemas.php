<?php
declare(strict_types=1);

// Validate against the exact Magento release used by the target application.
$magentoRoot = $argv[1] ?? '';
if ($magentoRoot === '' || !is_dir($magentoRoot . '/lib/internal/Magento/Framework')) {
    fwrite(STDERR, 'Usage: php dev/validate-schemas.php /path/to/magento-source' . PHP_EOL);
    exit(1);
}
$resolve = static function (string $uri) use ($magentoRoot): ?string {
    if (str_starts_with($uri, 'urn:magento:framework:')) {
        return $magentoRoot . '/lib/internal/Magento/Framework/' . substr($uri, strlen('urn:magento:framework:'));
    }
    if (preg_match('/^urn:magento:module:Magento_([A-Za-z0-9]+):(.+)$/', $uri, $match)) {
        return $magentoRoot . '/app/code/Magento/' . $match[1] . '/' . $match[2];
    }
    return is_file($uri) ? $uri : null;
};
libxml_use_internal_errors(true);
libxml_set_external_entity_loader(static function ($publicId, $systemId, $context) use ($resolve) {
    $path = $resolve((string) $systemId);
    return $path && is_file($path) ? fopen($path, 'rb') : null;
});
$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$failed = 0;
$checked = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'xml' || preg_match('~/(runtime|vendor|node_modules|\.git)/~', $file->getPathname())) {
        continue;
    }
    $document = new DOMDocument();
    $document->load($file->getPathname(), LIBXML_NONET);
    $urn = $document->documentElement?->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'noNamespaceSchemaLocation');
    if (!$urn) {
        continue;
    }
    $path = $resolve($urn);
    $checked++;
    if (!$path || !is_file($path) || !$document->schemaValidate($path)) {
        $failed++;
        fwrite(STDERR, 'FAIL ' . substr($file->getPathname(), strlen($root) + 1) . PHP_EOL);
        foreach (libxml_get_errors() as $error) {
            fwrite(STDERR, trim($error->message) . PHP_EOL);
        }
    }
    libxml_clear_errors();
}
echo sprintf("Validated %d Magento XML files against source XSDs; %d failures.\n", $checked, $failed);
exit($failed === 0 ? 0 : 1);
