<?php

use Magento\Framework\Component\ComponentRegistrar;

spl_autoload_register(static function ($class) {
    $prefix = 'Hybridauth\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BP . '/vendor/hybridauth/hybridauth/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Dalactive_HybridauthAutoload',
    __DIR__
);
