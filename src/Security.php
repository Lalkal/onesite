<?php
declare(strict_types=1);

namespace LoganX;

class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = self::isHttps();
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        return false;
    }

    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ipList = explode(',', $_SERVER[$header]);
                $ip = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    public static function generateCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizeFilename(string $filename): string
    {
        $name = basename($filename);
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
        return $name ?: 'download';
    }

    public static function isPathSafe(string $baseDir, string $targetPath): bool
    {
        $realBase = realpath($baseDir);
        $realTarget = realpath($targetPath);

        if ($realBase === false || $realTarget === false) {
            return false;
        }

        // Normalize directory separators for Windows/Unix
        $realBase = str_replace('\\', '/', $realBase);
        $realTarget = str_replace('\\', '/', $realTarget);

        if (!str_ends_with($realBase, '/')) {
            $realBase .= '/';
        }

        return str_starts_with($realTarget, $realBase) || $realTarget === rtrim($realBase, '/');
    }

    public static function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    public static function sanitizeLogData(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $k => $v) {
                if (preg_match('/(token|password|secret|key|auth|smtp_pass)/i', (string)$k)) {
                    $sanitized[$k] = '[REDACTED]';
                } else {
                    $sanitized[$k] = self::sanitizeLogData($v);
                }
            }
            return $sanitized;
        }

        if (is_string($data)) {
            // Strip any raw token strings (hex sequences of 64 chars)
            $cleaned = preg_replace('/[a-f0-9]{64}/i', '[TOKEN_HASH_OR_HEX_REDACTED]', $data);
            // Strip passwords or secrets
            $cleaned = preg_replace('/(password|pass|secret|token)[\s:=]+([^\s,;]+)/i', '$1 [REDACTED]', $cleaned);
            return $cleaned;
        }

        return $data;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $cleanContext = self::sanitizeLogData($context);
        $logDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            self::sanitizeLogData($message),
            !empty($cleanContext) ? json_encode($cleanContext, JSON_UNESCAPED_SLASHES) : ''
        );

        // Emit to error_log for Vercel Serverless Logs Dashboard
        error_log(trim($line));

        if (@file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX) === false) {
            @file_put_contents('/tmp/app.log', $line, FILE_APPEND | LOCK_EX);
        }
    }
}
