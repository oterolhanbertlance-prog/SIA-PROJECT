USE milk_tea_pos;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER manager_name;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_radius_km DECIMAL(8,2) NOT NULL DEFAULT 8.00 AFTER delivery_fee;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS delivery_eta_minutes INT NOT NULL DEFAULT 30 AFTER delivery_radius_km;
ALTER TABLE delivery_riders ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER status;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER order_type;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS rider_id INT NULL AFTER rider_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS hrms_user_id INT NULL AFTER lastname;
ALTER TABLE users ADD COLUMN IF NOT EXISTS hrms_employee_id INT NULL AFTER hrms_user_id;
CREATE TABLE IF NOT EXISTS staff_attendance (
 id INT AUTO_INCREMENT PRIMARY KEY,
 pos_user_id INT NOT NULL,
 date DATE NOT NULL,
 time_in TIME NULL,
 time_out TIME NULL,
 hours_worked DECIMAL(5,2) NOT NULL DEFAULT 0,
 late_minutes INT NOT NULL DEFAULT 0,
 undertime_minutes INT NOT NULL DEFAULT 0,
 location_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
 time_in_lat DECIMAL(10,7) NULL,
 time_in_lon DECIMAL(10,7) NULL,
 time_out_lat DECIMAL(10,7) NULL,
 time_out_lon DECIMAL(10,7) NULL,
 synced_at DATETIME NULL,
 UNIQUE KEY uq_staff_day(pos_user_id,date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
