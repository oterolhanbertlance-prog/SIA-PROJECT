USE hrms;
ALTER TABLE attendance MODIFY employee_id INT NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS pos_user_id INT NULL AFTER user_id;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS source_system VARCHAR(30) NOT NULL DEFAULT 'hrms' AFTER pos_user_id;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS time_in_lat DECIMAL(10,7) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS time_in_lon DECIMAL(10,7) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS time_out_lat DECIMAL(10,7) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS time_out_lon DECIMAL(10,7) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS time_in_distance DECIMAL(10,2) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS time_out_distance DECIMAL(10,2) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS location_status VARCHAR(20) NOT NULL DEFAULT 'unknown';
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS remote_deduction DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE payroll ADD COLUMN IF NOT EXISTS attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction;
CREATE TABLE IF NOT EXISTS job_posting_branches (
  job_posting_id INT NOT NULL,
  branch_id INT NOT NULL,
  PRIMARY KEY(job_posting_id,branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO job_posting_branches(job_posting_id,branch_id)
SELECT id,branch_id FROM job_postings WHERE branch_id IS NOT NULL;
