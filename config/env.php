<?php
/** Chargeur .env minimal, sans dépendance externe. */

function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    if ($values === false) {
        throw new RuntimeException('Le fichier de configuration .env est invalide.');
    }

    foreach ($values as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim((string) $value);
        if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
