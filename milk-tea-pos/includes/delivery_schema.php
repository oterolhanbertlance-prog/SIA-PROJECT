<?php
/** Branch-driven delivery schema. Delivery zones are legacy and no longer required. */
function ensureDeliveryColumns(PDO $db): void {
    $columns = [
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_name VARCHAR(150) NULL AFTER delivery_address",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_phone VARCHAR(30) NULL AFTER delivery_name",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_notes TEXT NULL AFTER delivery_phone",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_status ENUM('pending','preparing','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'pending' AFTER delivery_notes",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_status",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS rider_name VARCHAR(100) NULL AFTER delivery_fee",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS rider_id INT NULL AFTER rider_name",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS estimated_arrival DATETIME NULL AFTER rider_id",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER estimated_arrival",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER order_type",
    ];
    foreach ($columns as $statement) { try { $db->exec($statement); } catch (Throwable $e) {} }

    $db->exec("CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY, branch_code VARCHAR(30) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL, address VARCHAR(255) NOT NULL, city VARCHAR(100) DEFAULT NULL,
        latitude DECIMAL(10,7) DEFAULT NULL, longitude DECIMAL(10,7) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL, manager_name VARCHAR(150) DEFAULT NULL,
        delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 50.00,
        delivery_radius_km DECIMAL(8,2) NOT NULL DEFAULT 8.00,
        delivery_eta_minutes INT NOT NULL DEFAULT 30,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        "ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER manager_name",
        "ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_radius_km DECIMAL(8,2) NOT NULL DEFAULT 8.00 AFTER delivery_fee",
        "ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_eta_minutes INT NOT NULL DEFAULT 30 AFTER delivery_radius_km",
    ] as $sql) { try { $db->exec($sql); } catch (Throwable $e) {} }

    $db->exec("CREATE TABLE IF NOT EXISTS delivery_riders (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, phone VARCHAR(30) NOT NULL,
        vehicle VARCHAR(100) DEFAULT NULL, plate_no VARCHAR(30) DEFAULT NULL,
        status ENUM('available','on_delivery','off_duty') NOT NULL DEFAULT 'available',
        branch_id INT DEFAULT NULL, zone_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $db->exec("ALTER TABLE delivery_riders ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER status"); } catch (Throwable $e) {}
}
