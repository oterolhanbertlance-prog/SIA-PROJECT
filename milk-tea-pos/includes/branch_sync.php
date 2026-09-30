<?php
/** Synchronize one POS branch into HRMS. Returns true only when sync succeeds. */
function syncPosBranchToHrms(PDO $pos, int $branchId): bool
{
    $stmt = $pos->prepare('SELECT * FROM branches WHERE id=? LIMIT 1');
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$branch) return false;

    try {
        $hrms = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=hrms;charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $hrms->exec("CREATE TABLE IF NOT EXISTS branches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_code VARCHAR(30) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(100) DEFAULT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            manager_id INT DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER city");
        $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
        $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS pos_branch_id INT NULL AFTER id");
        try { $hrms->exec("ALTER TABLE branches ADD UNIQUE KEY uq_hrms_pos_branch (pos_branch_id)"); } catch (Throwable $e) {}

        $find = $hrms->prepare('SELECT id FROM branches WHERE pos_branch_id=? OR branch_code=? LIMIT 1');
        $find->execute([(int)$branch['id'], $branch['branch_code']]);
        $existing = $find->fetchColumn();
        if ($existing) {
            $hrms->prepare("UPDATE branches SET pos_branch_id=?,branch_code=?,name=?,address=?,city=?,latitude=?,longitude=?,phone=?,status=?,updated_at=NOW() WHERE id=?")
                 ->execute([(int)$branch['id'],$branch['branch_code'],$branch['name'],$branch['address'],$branch['city'] ?: null,$branch['latitude'],$branch['longitude'],$branch['phone'] ?: null,$branch['status'],$existing]);
        } else {
            $hrms->prepare("INSERT INTO branches (pos_branch_id,branch_code,name,address,city,latitude,longitude,phone,status) VALUES (?,?,?,?,?,?,?,?,?)")
                 ->execute([(int)$branch['id'],$branch['branch_code'],$branch['name'],$branch['address'],$branch['city'] ?: null,$branch['latitude'],$branch['longitude'],$branch['phone'] ?: null,$branch['status']]);
        }
        return true;
    } catch (Throwable $e) {
        error_log('POS→HRMS branch sync failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Pull every HRMS branch into POS and preserve the HRMS -> POS link.
 * This fixes older installs where HRMS already has branches that were created
 * before two-way synchronization was introduced.
 *
 * Returns an array with counts and never throws to the calling page.
 */
function syncAllHrmsBranchesToPos(PDO $pos): array
{
    $result = ['ok' => false, 'inserted' => 0, 'updated' => 0, 'error' => null];

    try {
        // POS schema required by branch-aware deliveries / assignments.
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
        $pos->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 50.00");
        $pos->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_radius_km DECIMAL(8,2) NOT NULL DEFAULT 8.00");
        $pos->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_eta_minutes INT NOT NULL DEFAULT 35");

        $hrms = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=hrms;charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER city");
        $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
        $hrms->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS pos_branch_id INT NULL AFTER id");
        try { $hrms->exec("ALTER TABLE branches ADD UNIQUE KEY uq_hrms_pos_branch (pos_branch_id)"); } catch (Throwable $e) {}

        $rows = $hrms->query("SELECT b.*, CONCAT_WS(' ',e.firstname,e.lastname) AS hr_manager_name
                              FROM branches b
                              LEFT JOIN employees e ON e.id=b.manager_id
                              ORDER BY b.id")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $b) {
            $posId = !empty($b['pos_branch_id']) ? (int)$b['pos_branch_id'] : 0;
            $existing = false;

            if ($posId > 0) {
                $q = $pos->prepare('SELECT id FROM branches WHERE id=? LIMIT 1');
                $q->execute([$posId]);
                $existing = $q->fetchColumn();
            }
            if (!$existing) {
                $q = $pos->prepare('SELECT id FROM branches WHERE branch_code=? LIMIT 1');
                $q->execute([$b['branch_code']]);
                $existing = $q->fetchColumn();
            }

            $managerName = trim((string)($b['hr_manager_name'] ?? '')) ?: null;
            $status = in_array(($b['status'] ?? 'active'), ['active','inactive'], true) ? $b['status'] : 'active';

            if ($existing) {
                $posId = (int)$existing;
                $pos->prepare("UPDATE branches
                               SET branch_code=?,name=?,address=?,city=?,latitude=?,longitude=?,phone=?,
                                   manager_name=COALESCE(?,manager_name),status=?,updated_at=NOW()
                               WHERE id=?")
                    ->execute([
                        $b['branch_code'],$b['name'],$b['address'],$b['city'] ?: null,
                        $b['latitude'],$b['longitude'],$b['phone'] ?: null,
                        $managerName,$status,$posId
                    ]);
                $result['updated']++;
            } else {
                $pos->prepare("INSERT INTO branches
                               (branch_code,name,address,city,latitude,longitude,phone,manager_name,status)
                               VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $b['branch_code'],$b['name'],$b['address'],$b['city'] ?: null,
                        $b['latitude'],$b['longitude'],$b['phone'] ?: null,
                        $managerName,$status
                    ]);
                $posId = (int)$pos->lastInsertId();
                $result['inserted']++;
            }

            if ((int)($b['pos_branch_id'] ?? 0) !== $posId) {
                $hrms->prepare('UPDATE branches SET pos_branch_id=? WHERE id=?')->execute([$posId,(int)$b['id']]);
            }
        }

        // Also keep POS-only branches represented in HRMS.
        $posRows = $pos->query('SELECT id FROM branches ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($posRows as $id) {
            syncPosBranchToHrms($pos, (int)$id);
        }

        $result['ok'] = true;
        return $result;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        error_log('HRMS→POS full branch sync failed: ' . $e->getMessage());
        return $result;
    }
}

