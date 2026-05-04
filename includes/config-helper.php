<?php

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [];
    $localConfig = dirname(__DIR__) . '/config.php';
    if (file_exists($localConfig)) {
        $loaded = require $localConfig;
        if (is_array($loaded)) {
            $config = $loaded;
        }
    }

    $envMap = [
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'DB_SSL',
        'MAIL_FROM_EMAIL',
        'MAIL_FROM_NAME',
    ];

    foreach ($envMap as $key) {
        $envValue = getenv($key);
        if ($envValue === false || $envValue === '') {
            continue;
        }

        if ($key === 'DB_SSL') {
            $config[$key] = filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
            continue;
        }

        $config[$key] = $envValue;
    }

    $config += [
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
        'DB_NAME' => 'learnly',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'DB_SSL' => false,
        'MAIL_FROM_EMAIL' => 'no-reply@learnly.local',
        'MAIL_FROM_NAME' => 'Learnly',
    ];

    return $config;
}
