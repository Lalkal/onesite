<?php
declare(strict_types=1);

namespace LoganX;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        Config::load();

        $driver = strtolower((string)Config::get('DB_CONNECTION', ''));
        $host   = (string)Config::get('DB_HOST', '127.0.0.1');
        $port   = (string)Config::get('DB_PORT', '3306');
        $dbname = (string)Config::get('DB_DATABASE', 'loganx_downloads');
        $user   = (string)Config::get('DB_USERNAME', 'root');
        $pass   = (string)Config::get('DB_PASSWORD', '');

        // Auto-detect Supabase PostgreSQL
        $isPostgres = ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'supabase' ||
            str_contains($host, 'supabase.co') || str_contains($host, 'supabase.com') ||
            (int)$port === 5432 || (int)$port === 6543);

        if ($isPostgres) {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
                self::$pdo->exec("SET TIME ZONE 'UTC'");
                return self::$pdo;
            } catch (PDOException $e) {
                throw new RuntimeException("Supabase PostgreSQL connection failed: " . $e->getMessage(), (int)$e->getCode());
            }
        }

        // MySQL / MariaDB (Local or Cloud MySQL like TiDB)
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'"
        ];

        $ssl = Config::get('DB_SSL', false);
        if ($ssl === true || $ssl === 'true' || (int)$port === 4000) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
            return self::$pdo;
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
