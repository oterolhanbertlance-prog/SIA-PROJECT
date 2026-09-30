USE `milk_tea_pos`;
ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','preparing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS processed_by INT NULL AFTER user_id;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL AFTER date_created;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER accepted_at;
