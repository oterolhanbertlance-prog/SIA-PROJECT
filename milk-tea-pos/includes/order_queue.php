<?php
/** Cashier/customer order queue schema upgrades. Safe on existing databases. */
function ensureOrderQueueSchema(PDO $db): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $sql = [
        "ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','preparing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending'",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS processed_by INT NULL AFTER user_id",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL AFTER date_created",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER accepted_at"
    ];
    foreach ($sql as $q) { try { $db->exec($q); } catch (Throwable $e) { error_log('Order queue schema: '.$e->getMessage()); } }
}

function cashierBranchId(PDO $db, int $userId): ?int {
    try {
        $s=$db->prepare("SELECT branch_id FROM users WHERE id=? LIMIT 1");
        $s->execute([$userId]);
        $id=(int)($s->fetchColumn() ?: 0);
        return $id ?: null;
    } catch (Throwable $e) { return null; }
}
