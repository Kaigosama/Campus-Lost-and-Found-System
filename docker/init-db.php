<?php
declare(strict_types=1);

/**
 * Runs once per container start (docker/entrypoint.sh), before Apache.
 * Waits for MySQL, creates the database if it is missing, and loads docs/schema.sql
 * only when there is no `users` table yet — schema.sql drops tables, so an
 * existing database is never touched.
 */

require dirname(__DIR__) . '/config/db_connect.php';

const ATTEMPTS = 30;

function log_line(string $message): void
{
    fwrite(STDERR, "[init-db] $message\n");
}

function connect(bool $withDatabase): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET)
        . ($withDatabase ? ';dbname=' . DB_NAME : '');
    return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

for ($attempt = 1; ; $attempt++) {
    try {
        $pdo = connect(true);
        break;
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 1049) {   // Unknown database: create it, then connect again.
            log_line('Creating database ' . DB_NAME);
            connect(false)->exec('CREATE DATABASE `' . str_replace('`', '``', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            continue;
        }
        if ($attempt >= ATTEMPTS) {
            log_line('Cannot reach MySQL at ' . DB_HOST . ':' . DB_PORT . ' — ' . $e->getMessage());
            exit(1);
        }
        log_line('Waiting for MySQL at ' . DB_HOST . ':' . DB_PORT . " ($attempt/" . ATTEMPTS . ')');
        sleep(2);
    }
}

if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false) {
    log_line('Database ' . DB_NAME . ' already has tables; leaving it as is.');
    exit(0);
}

log_line('Empty database ' . DB_NAME . ': loading docs/schema.sql');
// schema.sql creates and selects its own `clafs` database for XAMPP; here the configured database is used instead.
$sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', (string) file_get_contents(APP_ROOT . '/docs/schema.sql'));
$stmt = $pdo->query($sql);
while ($stmt->nextRowset()) {
    // Step through every statement so an error in any of them is raised.
}
log_line('Schema and seed data loaded.');

// The seeded accounts share the password printed in the README; SEED_PASSWORD replaces it on a public deploy.
$seedPassword = (string) getenv('SEED_PASSWORD');
if ($seedPassword !== '') {
    $pdo->prepare('UPDATE users SET password_hash = ?')->execute([password_hash($seedPassword, PASSWORD_DEFAULT)]);
    log_line('Seeded accounts now use SEED_PASSWORD.');
}
