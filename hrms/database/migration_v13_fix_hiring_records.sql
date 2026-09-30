-- ============================================================================
-- HRMS v13 — repair hiring, deployment, contracts, and POS department links
-- Run once against the `hrms` database after the POS integration migration.
-- Safe to run more than once: existing active contracts are not duplicated.
-- ============================================================================

USE `hrms`;

-- Older POS job postings were created without position_id. Link them to the
-- canonical POS positions so new hires inherit both position and department.
UPDATE `job_postings` j
JOIN `positions` p
  ON p.id = (
      SELECT MIN(p2.id)
      FROM `positions` p2
      WHERE p2.title = j.title
        AND p2.status = 'active'
  )
SET j.position_id = p.id,
    j.department_id = p.department_id
WHERE j.title IN ('POS Super Admin', 'Cashier', 'Inventory Staff');

-- Repair employees that were created from a hired POS applicant without the
-- department/position fields being copied from the job posting.
UPDATE `employees` e
JOIN `applicants` a
  ON LOWER(a.email) = LOWER(e.email)
 AND a.status = 'hired'
JOIN `job_postings` j ON j.id = a.job_id
JOIN `positions` p
  ON p.id = COALESCE(j.position_id, (
      SELECT MIN(p2.id)
      FROM `positions` p2
      WHERE p2.title = j.title
        AND p2.status = 'active'
  ))
SET e.department_id = p.department_id,
    e.position_id = p.id
WHERE j.title IN ('POS Super Admin', 'Cashier', 'Inventory Staff');

-- Repair existing contracts for those employees and create the missing
-- contract record when the old hire flow only created a login/user.
UPDATE `contracts` c
JOIN `employees` e ON e.id = c.employee_id
SET c.department_id = e.department_id,
    c.position_id = e.position_id,
    c.salary = CASE WHEN c.salary = 0 THEN e.basic_salary ELSE c.salary END
WHERE c.status IN ('active', 'renewed')
  AND (c.department_id IS NULL OR c.position_id IS NULL);

INSERT INTO `contracts`
    (employee_id, contract_number, contract_type, start_date,
     department_id, position_id, salary, status, notes, created_by)
SELECT e.id,
       CONCAT('CON-AUTO-', LPAD(e.id, 6, '0')),
       'Probationary',
       COALESCE(e.date_hired, CURDATE()),
       e.department_id,
       e.position_id,
       COALESCE(e.basic_salary, 0),
       'active',
       'Repaired automatically from an existing hired applicant record.',
       NULL
FROM `employees` e
JOIN `applicants` a
  ON LOWER(a.email) = LOWER(e.email)
 AND a.status = 'hired'
JOIN `job_postings` j ON j.id = a.job_id
WHERE j.title IN ('POS Super Admin', 'Cashier', 'Inventory Staff')
  AND e.department_id IS NOT NULL
  AND e.position_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `contracts` c
      WHERE c.employee_id = e.id
        AND c.status IN ('active', 'renewed')
  );

SELECT 'Migration v13 complete: hiring records, contracts, and departments repaired.' AS status;