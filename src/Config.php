<?php
declare(strict_types=1);

namespace LoganX;

class Config
{
    private static ?array $settings = null;

    public static function load(?string $envFile = null): void
    {
        if (self::$settings !== null) {
            return;
        }

        self::$settings = [];
        $envPath = $envFile ?? dirname(__DIR__) . '/.env';

        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_contains($line, '=')) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val);
                    // Remove quotes if present
                    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                        $val = substr($val, 1, -1);
                    }
                    self::$settings[$key] = $val;
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $val;
                    }
                }
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (isset(self::$settings[$key]) && self::$settings[$key] !== '') {
            return self::$settings[$key];
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return $default;
    }

    public static function appUrl(): string
    {
        $url = (string)self::get('APP_URL', '');
        if ($url !== '') {
            return rtrim($url, '/');
        }
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = Security::isHttps() ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'];
        }
        if (!empty($_ENV['VERCEL_URL'])) {
            return 'https://' . $_ENV['VERCEL_URL'];
        }
        return 'http://localhost:8000';
    }

    public static function storagePath(): string
    {
        $relOrAbs = (string)self::get('DOWNLOAD_STORAGE_PATH', 'storage/downloads');
        if (str_starts_with($relOrAbs, '/') || (strlen($relOrAbs) > 2 && $relOrAbs[1] === ':')) {
            return rtrim($relOrAbs, '\\/');
        }
        return rtrim(dirname(__DIR__) . '/' . $relOrAbs, '\\/');
    }
}
