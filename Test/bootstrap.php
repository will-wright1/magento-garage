<?php
declare(strict_types=1);

// Translation helper for rendering templates without bootstrapping Magento.
if (!function_exists('__')) {
    function __(string $message, mixed ...$arguments): string
    {
        foreach ($arguments as $index => $argument) {
            $message = str_replace('%' . ($index + 1), (string) $argument, $message);
        }
        return $message;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Automotive\\Garage\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
