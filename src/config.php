<?php

$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (trim($line) === '' || str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    global $env;
    $runtime = getenv($key);
    if ($runtime !== false) {
        return $runtime;
    }
    return $env[$key] ?? $default;
}

define('DB_HOST', env_value('DB_HOST', 'db'));
define('DB_PORT', env_value('DB_PORT', '3306'));
define('DB_NAME', env_value('DB_NAME', 'bachy'));
define('DB_USER', env_value('DB_USER', 'bachy'));
define('DB_PASS', env_value('DB_PASS', 'secret'));
define('JWT_SECRET', env_value('JWT_SECRET', 'cambiar_esta_clave'));
