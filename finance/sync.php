<?php
declare(strict_types=1);

/**
 * Optional Windows Task Scheduler entry point:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\finance\sync.php
 *
 * It performs the same idempotent sync as the web Integration screen.
 * Configure a dedicated local service user before scheduling.
 */
require_once __DIR__ . '/includes/auth.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only.');
}
$userId = (int) (db_finance()->query("SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
if ($userId < 1) {
    fwrite(STDERR, "No active Finance user exists. Complete install.php first.\n");
    exit(1);
}
require_once __DIR__ . '/services/IntegrationService.php';
$optional = static function (string $system): ?PDO {
    try {
        return $system === 'hrms' ? db_hrms() : db_pos();
    } catch (Throwable) {
        return null;
    }
};
$service = new IntegrationService(db_finance(), $optional('hrms'), $optional('pos'));
$result = $service->syncEverything($userId);
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);