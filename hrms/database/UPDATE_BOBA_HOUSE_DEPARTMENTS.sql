-- Boba House Milk Tea HRMS department cleanup
-- Safe for the existing hrms database. No DROP/DELETE/TRUNCATE.
USE `hrms`;

UPDATE departments SET name='Management & Administration',
 description='Branch leadership, POS administration and workforce coordination', status='active' WHERE id=1;
UPDATE departments SET name='Inventory & Supply',
 description='Ingredients, stock, purchasing and inventory control', status='active' WHERE id=2;
UPDATE departments SET name='Delivery & Logistics',
 description='Delivery riders, dispatch and branch logistics', status='active' WHERE id=3;
UPDATE departments SET name='Store Operations',
 description='Cashier, counter service and day-to-day milk tea store operations', status='active' WHERE id=4;
UPDATE departments SET status='inactive' WHERE id NOT IN (1,2,3,4);

UPDATE positions SET department_id=1, status='active' WHERE title='POS Super Admin';
UPDATE positions SET department_id=2, status='active' WHERE title='Inventory Staff';
UPDATE positions SET department_id=4, status='active' WHERE title='Cashier';
UPDATE positions SET status='inactive' WHERE title NOT IN ('POS Super Admin','Inventory Staff','Cashier');

UPDATE employees e LEFT JOIN positions p ON p.id=e.position_id
SET e.department_id=CASE
 WHEN p.title='POS Super Admin' THEN 1
 WHEN p.title='Inventory Staff' THEN 2
 WHEN p.title='Cashier' THEN 4
 ELSE 4 END
WHERE e.status='active';

UPDATE job_postings SET department_id=1 WHERE title='POS Super Admin';
UPDATE job_postings SET department_id=2 WHERE title='Inventory Staff';
UPDATE job_postings SET department_id=4 WHERE title='Cashier';

UPDATE contracts c JOIN positions p ON p.id=c.position_id
SET c.department_id=p.department_id
WHERE c.status='active' AND p.title IN ('POS Super Admin','Inventory Staff','Cashier');
