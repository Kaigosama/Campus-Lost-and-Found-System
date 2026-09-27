<?php
declare(strict_types=1);

/**
 * JSON responses for public/api/ and safe redirects.
 */

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(int $code, string $message, array $extra = []): void
{
    json_response(['ok' => false, 'error' => $message] + $extra, $code);
}

/** Decoded JSON body when sent as application/json, otherwise the form fields. */
function json_input(): array
{
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_error(405, "Use $method.");
    }
}

/** Only same-site paths are safe redirect targets after login. */
function safe_redirect(string $next): string
{
    return ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//')) ? $next : url('/');
}
