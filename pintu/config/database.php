<?php
/**
 * PDO Database Connection (Singleton)
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $host = getenv('DB_HOST') ?: 'localhost';
        $db   = getenv('DB_NAME') ?: 'pintu';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (getenv('APP_DEBUG') === '1') {
                die('DB connection failed: ' . $e->getMessage());
            }
            error_log('[PINTU] DB connect error: ' . $e->getMessage());
            http_response_code(500);
            die('Terjadi kesalahan sistem. Silakan coba kembali nanti.');
        }
        return self::$pdo;
    }
}