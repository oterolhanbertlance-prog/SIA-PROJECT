<?php
/**
 * Router for PHP's built-in dev server (php -S ... router.php).
 * Serves static files as-is; lets .php files execute normally;
 * falls back to index.php for the bare root request.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // Let the built-in server handle static assets (css/js/images) itself.
    return false;
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

return false;
