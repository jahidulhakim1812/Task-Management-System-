<?php
/** PDO connection. Edit the constants for your environment (XAMPP defaults shown). */
class Database
{
    public const HOST = '127.0.0.1';
    public const NAME = 'tms_db';
    public const USER = 'root';
    public const PASS = '';

    private static ?PDO $pdo = null;
    private const OPTIONS = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO('mysql:host=' . self::HOST . ';dbname=' . self::NAME . ';charset=utf8mb4', self::USER, self::PASS, self::OPTIONS);
                self::$pdo->exec("SET time_zone = '" . DB_TIME_ZONE . "'");
            } catch (PDOException $e) {
                $base = defined('BASE_URL') ? BASE_URL : '';
                die('<h3>Database connection failed</h3><p>Start MySQL in XAMPP, then open <a href="' . htmlspecialchars($base) . '/install.php">install.php</a> to create the database.</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
            }
        }
        return self::$pdo;
    }

    /** Connection to the MySQL server without selecting a database (used by install.php). */
    public static function serverConnection(): PDO
    {
        return new PDO('mysql:host=' . self::HOST . ';charset=utf8mb4', self::USER, self::PASS, self::OPTIONS);
    }
}
