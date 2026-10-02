<?php
declare(strict_types=1);

/**
 * Runs once per container start (docker/entrypoint.sh), before Apache. On XAMPP run it by hand: php database/seed.php
 * Waits for MySQL, creates the database if it is missing, loads database/schema.sql only when there is no
 * `users` table yet (schema.sql drops tables), then applies any new database/migrations/*.sql.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

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

/** Runs a multi-statement SQL file, stepping through every statement so an error in any of them is raised. */
function run_sql(PDO $pdo, string $sql): void
{
    $stmt = $pdo->query($sql);
    while ($stmt->nextRowset()) {
    }
}

if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false) {
    log_line('Database ' . DB_NAME . ' already has tables; keeping its data.');
} else {
    log_line('Empty database ' . DB_NAME . ': loading database/schema.sql');
    // schema.sql creates and selects its own `clafs` database for XAMPP; here the configured database is used instead.
    run_sql($pdo, preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', (string) file_get_contents(APP_ROOT . '/database/schema.sql')));
    log_line('Schema and seed data loaded.');
}

// Schema changes since schema.sql, each applied once in file-name order. They only add columns, tables and rows.
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$applied = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach (glob(APP_ROOT . '/database/migrations/*.sql') as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    log_line("Applying migration $name");
    run_sql($pdo, (string) file_get_contents($file));
    $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
}

// The seeded accounts share the password printed in the README; SEED_PASSWORD replaces it on a public deploy,
// including on accounts a later migration adds.
const SAMPLE_PASSWORD_HASH = '$2y$10$IAwEs/B32tlkg/sfgBNKRe5sveKeQ9wBgzD87w3nhoFjEtWd0AFAi';
$seedPassword = (string) getenv('SEED_PASSWORD');
if ($seedPassword !== '') {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE password_hash = ?');
    $stmt->execute([password_hash($seedPassword, PASSWORD_DEFAULT), SAMPLE_PASSWORD_HASH]);
    if ($stmt->rowCount()) {
        log_line('Sample accounts now use SEED_PASSWORD.');
    }
}
