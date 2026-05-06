<?php

function app_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $directory = rtrim(dirname($scriptName), '/');

    if ($directory === '' || $directory === '.') {
        $basePath = '/';
        return $basePath;
    }

    $basePath = $directory . '/';
    return $basePath;
}

function app_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return app_base_path();
    }

    return app_base_path() . $path;
}

function app_url_with_query(string $url, array $params = [], string $fragment = ''): string
{
    $parts = parse_url($url);
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }

    $rebuilt = '';

    if (!empty($parts['scheme'])) {
        $rebuilt .= $parts['scheme'] . '://';
    }
    if (!empty($parts['host'])) {
        $rebuilt .= $parts['host'];
    }
    if (!empty($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }

    $rebuilt .= $parts['path'] ?? '';

    if (!empty($query)) {
        $rebuilt .= '?' . http_build_query($query);
    }

    $targetFragment = $fragment !== '' ? $fragment : ($parts['fragment'] ?? '');
    if ($targetFragment !== '') {
        $rebuilt .= '#' . ltrim($targetFragment, '#');
    }

    return $rebuilt;
}

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
