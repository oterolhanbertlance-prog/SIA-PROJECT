<?php
/**
 * Two-way HRMS <-> POS branch synchronization.
 * POS branches are linked to HRMS branches through hrms.branches.pos_branch_id.
 */

function openPosBranchDb(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=milk_tea_pos;charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function ensureHrmsBranchSyncSchema(PDO $hrms): void
{
    $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER city");
    $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
    $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS pos_branch_id INT NULL AFTER id");
    try {
        $hrms->exec("ALTER TABLE branches ADD UNIQUE KEY uq_hrms_pos_branch (pos_branch_id)");
    } catch (Throwable $e) {
        // Index already exists, or old duplicate data must be cleaned manually.
    }
}

function ensurePosBranchSyncSchema(PDO $pos): void
{
    $pos->exec("CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_code VARCHAR(30) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        address VARCHAR(255) NOT NULL,
        city VARCHAR(100) DEFAULT NULL,
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        manager_name VARCHAR(150) DEFAULT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pos->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER city");
    $pos->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
    $pos->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS manager_name VARCHAR(150) NULL AFTER phone");
}

/** Pull every POS branch into HRMS. Used by recruitment/apply pages. */
function syncBranchesFromPos(PDO $hrms): void
{
    try {
        ensureHrmsBranchSyncSchema($hrms);
        $pos = openPosBranchDb();
        ensurePosBranchSyncSchema($pos);
        $rows = $pos->query('SELECT id,branch_code,name,address,city,latitude,longitude,phone,status FROM branches ORDER BY id')->fetchAll();

        foreach ($rows as $b) {
            $find = $hrms->prepare('SELECT id FROM branches WHERE pos_branch_id=? OR branch_code=? LIMIT 1');
            $find->execute([(int)$b['id'], $b['branch_code']]);
            $id = $find->fetchColumn();

            if ($id) {
                $hrms->prepare('UPDATE branches SET pos_branch_id=?,branch_code=?,name=?,address=?,city=?,latitude=?,longitude=?,phone=?,status=?,updated_at=NOW() WHERE id=?')
                     ->execute([(int)$b['id'],$b['branch_code'],$b['name'],$b['address'],$b['city'],$b['latitude'],$b['longitude'],$b['phone'],$b['status'],$id]);
            } else {
                $hrms->prepare('INSERT INTO branches (pos_branch_id,branch_code,name,address,city,latitude,longitude,phone,status) VALUES (?,?,?,?,?,?,?,?,?)')
                     ->execute([(int)$b['id'],$b['branch_code'],$b['name'],$b['address'],$b['city'],$b['latitude'],$b['longitude'],$b['phone'],$b['status']]);
            }
        }
    } catch (Throwable $e) {
        error_log('HRMS POS branch pull failed: ' . $e->getMessage());
    }
}

/**
 * Push one HRMS branch into POS and store the POS id back on the HRMS row.
 * Returns ['ok' => bool, 'error' => ?string, 'pos_branch_id' => ?int].
 */
function syncHrmsBranchToPos(PDO $hrms, int $hrBranchId): array
{
    try {
        ensureHrmsBranchSyncSchema($hrms);

        $stmt = $hrms->prepare("SELECT b.*, CONCAT_WS(' ', e.firstname, e.lastname) AS manager_name
                                FROM branches b
                                LEFT JOIN employees e ON e.id=b.manager_id
                                WHERE b.id=? LIMIT 1");
        $stmt->execute([$hrBranchId]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$branch) {
            return ['ok' => false, 'error' => 'HRMS branch not found.', 'pos_branch_id' => null];
        }

        $pos = openPosBranchDb();
        ensurePosBranchSyncSchema($pos);

        $posId = !empty($branch['pos_branch_id']) ? (int)$branch['pos_branch_id'] : 0;
        $existing = false;

        if ($posId > 0) {
            $q = $pos->prepare('SELECT id FROM branches WHERE id=? LIMIT 1');
            $q->execute([$posId]);
            $existing = $q->fetchColumn();
        }

        if (!$existing) {
            $q = $pos->prepare('SELECT id FROM branches WHERE branch_code=? LIMIT 1');
            $q->execute([$branch['branch_code']]);
            $existing = $q->fetchColumn();
        }

        $values = [
            $branch['branch_code'], $branch['name'], $branch['address'], $branch['city'] ?: null,
            $branch['latitude'], $branch['longitude'], $branch['phone'] ?: null,
            trim((string)($branch['manager_name'] ?? '')) ?: null,
            $branch['status'] ?: 'active'
        ];

        if ($existing) {
            $posId = (int)$existing;
            $pos->prepare('UPDATE branches SET branch_code=?,name=?,address=?,city=?,latitude=?,longitude=?,phone=?,manager_name=?,status=?,updated_at=NOW() WHERE id=?')
                ->execute([...$values, $posId]);
        } else {
            $pos->prepare('INSERT INTO branches (branch_code,name,address,city,latitude,longitude,phone,manager_name,status) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute($values);
            $posId = (int)$pos->lastInsertId();
        }

        if ((int)($branch['pos_branch_id'] ?? 0) !== $posId) {
            $hrms->prepare('UPDATE branches SET pos_branch_id=? WHERE id=?')->execute([$posId, $hrBranchId]);
        }

        return ['ok' => true, 'error' => null, 'pos_branch_id' => $posId];
    } catch (Throwable $e) {
        error_log('HRMS→POS branch sync failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage(), 'pos_branch_id' => null];
    }
}
