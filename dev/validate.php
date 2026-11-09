<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$counts = ['PHP' => 0, 'XML' => 0, 'JSON' => 0];
$failures = [];
libxml_use_internal_errors(true);
foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (preg_match('~(^|/)(runtime|vendor|\.git|node_modules)/~', $relative)) {
        continue;
    }
    $extension = strtolower($file->getExtension());
    if (in_array($extension, ['php', 'phtml'], true)) {
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);
        $counts['PHP']++;
        if ($status !== 0) {
            $failures[] = implode(PHP_EOL, $output);
        }
    } elseif ($extension === 'xml') {
        $document = new DOMDocument();
        if (!$document->load($file->getPathname(), LIBXML_NONET)) {
            $failures[] = 'Malformed XML: ' . $relative;
        }
        $counts['XML']++;
    } elseif ($extension === 'json') {
        try {
            json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $failures[] = $relative . ': ' . $exception->getMessage();
        }
        $counts['JSON']++;
    }
}
foreach ($failures as $failure) {
    fwrite(STDERR, $failure . PHP_EOL);
}
echo sprintf("Checked %d PHP/templates, %d XML and %d JSON files; %d failures.\n",
    $counts['PHP'], $counts['XML'], $counts['JSON'], count($failures));
exit($failures === [] ? 0 : 1);
