<?php
declare(strict_types=1);

require_once __DIR__ . '/constants.php';

$localConfigPath = __DIR__ . '/local.php';
$config = is_file($localConfigPath) ? require $localConfigPath : [];

function connection_config(string $key): array
{
    global $config;
    $section = $config[$key] ?? [];
    $prefix = strtoupper($key === 'finance' ? 'FIN' : $key);
    return [
        'host' => getenv($prefix . '_DB_HOST') ?: ($section['host'] ?? '127.0.0.1'),
        'name' => getenv($prefix . '_DB_NAME') ?: ($section['name'] ?? ''),
        'user' => getenv($prefix . '_DB_USER') ?: ($section['user'] ?? 'root'),
        'pass' => getenv($prefix . '_DB_PASS') ?: ($section['pass'] ?? ''),
        'charset' => $section['charset'] ?? 'utf8mb4',
    ];
}

function pdo_from_config(array $settings): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $settings['host'],
        $settings['name'],
        $settings['charset']
    );
    return new PDO($dsn, $settings['user'], $settings['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
}

function db_finance(): PDO
{
    static $pdo;
    if (!$pdo instanceof PDO) {
        $pdo = pdo_from_config(connection_config('finance'));
    }
    return $pdo;
}

function db_hrms(): PDO
{
    static $pdo;
    if (!$pdo instanceof PDO) {
        $pdo = pdo_from_config(connection_config('hrms'));
    }
    return $pdo;
}

function db_pos(): PDO
{
    static $pdo;
    if (!$pdo instanceof PDO) {
        $pdo = pdo_from_config(connection_config('pos'));
    }
    return $pdo;
}

function test_connection(string $system): array
{
    try {
        $pdo = match ($system) {
            'finance' => db_finance(),
            'hrms' => db_hrms(),
            'pos' => db_pos(),
            default => throw new InvalidArgumentException('Unknown connection'),
        };
        $pdo->query('SELECT 1');
        return ['ok' => true, 'message' => 'Connected'];
    } catch (Throwable $error) {
        return ['ok' => false, 'message' => $error->getMessage()];
    }
}