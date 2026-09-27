<?php
declare(strict_types=1);

/**
 * Rate limiting: attempt times kept in temp files, like the reset-email stamps, so no table is needed.
 */

/** The visitor's IP. Railway's edge proxy sets X-Real-IP; anywhere else that header could be forged. */
function client_ip(): string
{
    if (setting('RAILWAY_PROJECT_ID') !== '' && !empty($_SERVER['HTTP_X_REAL_IP'])) {
        return (string) $_SERVER['HTTP_X_REAL_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function attempts_file(string $bucket): string
{
    return sys_get_temp_dir() . '/clafs-limit-' . hash('sha256', $bucket);
}

/** Unix times of the attempts in $bucket during the last LIMIT_WINDOW_MINUTES. */
function recent_attempts(string $bucket): array
{
    $file  = attempts_file($bucket);
    $times = is_file($file) ? array_map('intval', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
    $since = time() - LIMIT_WINDOW_MINUTES * 60;
    return array_values(array_filter($times, fn ($t) => $t > $since));
}

function rate_limited(string $bucket, int $max): bool
{
    return count(recent_attempts($bucket)) >= $max;
}

function record_attempt(string $bucket): void
{
    $times   = recent_attempts($bucket);
    $times[] = time();
    file_put_contents(attempts_file($bucket), implode("\n", $times), LOCK_EX);
}

function clear_attempts(string $bucket): void
{
    @unlink(attempts_file($bucket));
}
