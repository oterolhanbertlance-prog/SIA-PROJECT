<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$q  = sanitize($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$db      = getDB();
$results = [];
$like    = "%$q%";

// Search employees
if (hasRole('admin','hr')) {
    $stmt = $db->prepare("SELECT id, CONCAT(firstname,' ',lastname) as name, employee_id FROM employees WHERE (firstname LIKE ? OR lastname LIKE ? OR employee_id LIKE ?) AND status='active' LIMIT 5");
    $stmt->execute([$like,$like,$like]);
    foreach ($stmt->fetchAll() as $e) {
        $results[] = ['type'=>'Employee','label'=>$e['name'].' ('.$e['employee_id'].')','url'=>SITE_URL.'modules/employees/profile.php?id='.$e['id']];
    }

    // Search applicants
    $stmt = $db->prepare("SELECT id, CONCAT(firstname,' ',lastname) as name, applicant_code FROM applicants WHERE (firstname LIKE ? OR lastname LIKE ? OR applicant_code LIKE ?) LIMIT 5");
    $stmt->execute([$like,$like,$like]);
    foreach ($stmt->fetchAll() as $a) {
        $results[] = ['type'=>'Applicant','label'=>$a['name'].' ('.$a['applicant_code'].')','url'=>SITE_URL.'modules/recruitment/view_applicant.php?id='.$a['id']];
    }

    // Search job postings
    $stmt = $db->prepare("SELECT id, title FROM job_postings WHERE title LIKE ? LIMIT 3");
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll() as $j) {
        $results[] = ['type'=>'Job','label'=>$j['title'],'url'=>SITE_URL.'modules/recruitment/jobs.php'];
    }
}

// Search current employee's own records (available to every logged-in role,
// including "employee" who has no visibility into the admin/hr search above)
$empId = $_SESSION['employee_id'] ?? null;
if ($empId) {
    $stmt = $db->prepare(
        "SELECT lr.id, lr.leave_code, lr.status, lt.name AS type_name
         FROM leave_requests lr
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.employee_id = ? AND (lt.name LIKE ? OR lr.leave_code LIKE ?)
         LIMIT 5"
    );
    $stmt->execute([$empId, $like, $like]);
    foreach ($stmt->fetchAll() as $l) {
        $results[] = ['type'=>'Leave','label'=>$l['type_name'].' — '.$l['leave_code'].' ('.$l['status'].')','url'=>SITE_URL.'modules/leave/my_leave.php'];
    }
}

echo json_encode($results);
