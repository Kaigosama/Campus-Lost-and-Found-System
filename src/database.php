<?php
declare(strict_types=1);

/**
 * Database connection and access to settings.
 */

/** A setting from a define() in db_connect.local.php, else the environment; '' when unset. */
function setting(string $name): string
{
    return defined($name) ? (string) constant($name) : (string) getenv($name);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // NOW() and column defaults in PHP's time zone, so session expiry and lockout times compare correctly
            // even when the MySQL server runs in UTC (Railway).
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Database connection failed: {$e->getMessage()}\n\nImport database/schema.sql and set DB_* constants in config/db_connect.local.php (or DB_* / MYSQL_URL environment variables).");
        }
    }
    return $pdo;
}
