<?php

declare(strict_types=1);

function load_environment_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');

        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/Di', $name) !== 1 || getenv($name) !== false) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        $length = strlen($value);

        if ($length >= 2) {
            $firstCharacter = $value[0];
            $lastCharacter = $value[$length - 1];

            if (($firstCharacter === '"' && $lastCharacter === '"')
                || ($firstCharacter === "'" && $lastCharacter === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

load_environment_file(__DIR__ . '/../.env');
