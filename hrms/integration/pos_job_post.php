<?php
/**
 * integration/pos_job_post.php  — HRMS side  (NEW file)
 * Tags an existing hrms.job_postings row as a POS-targeted opening.
 *
 * Method : POST  |  Access: HRMS role = hr | admin
 * Returns: JSON shaped for SweetAlert2
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/pos_common.php';

function jsonOut(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d);
    exit;
}

// ── Auth guard ────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['hr', 'admin'], true)) {
    jsonOut(['success'=>false,'icon'=>'error',
             'title'=>'Access Denied','message'=>'Only HR and Admin can tag POS postings.'], 403);
}

$jobId  = filter_input(INPUT_POST, 'job_posting_id',    FILTER_VALIDATE_INT);
$role   = null;
$requestedRole = trim($_POST['pos_role_target'] ?? '');
$label  = trim($_POST['pos_position_label'] ?? '');
$byId   = (int)$_SESSION['user_id'];
$byUser = (string)($_SESSION['username'] ?? 'unknown');

if (!$jobId || $jobId <= 0)
    jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Field','message'=>'Select a valid job posting.']);

try {
    $db = getDB();

    $jp = $db->prepare("SELECT id, title, status FROM job_postings WHERE id = ? LIMIT 1");
    $jp->execute([$jobId]);
    $posting = $jp->fetch();

    if (!$posting)
        jsonOut(['success'=>false,'icon'=>'error','title'=>'Not Found','message'=>"Job posting #{$jobId} does not exist."]);
    $role = in_array($requestedRole, ['super_admin', 'cashier', 'inventory_staff'], true)
        ? $requestedRole
        : posRoleFromJobTitle($posting['title']);
    if (!$role)
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'Invalid POS Role','message'=>'Only POS Super Admin, Cashier, and Inventory Staff postings can be linked to POS.']);
    if ($label === '') $label = $posting['title'];
    if (mb_strlen($label) < 3 || mb_strlen($label) > 100)
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'Invalid Label','message'=>'Position label must be 3–100 characters.']);
    if (in_array($posting['status'], ['closed','filled'], true))
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'Posting Closed',
                 'message'=>'"'.htmlspecialchars($posting['title']).'" is already '.$posting['status'].'.']);

    $dup = $db->prepare("SELECT id FROM pos_job_postings_meta WHERE job_posting_id = ? LIMIT 1");
    $dup->execute([$jobId]);
    if ($dup->fetch())
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Tagged',
                 'message'=>'"'.htmlspecialchars($posting['title']).'" is already a POS-targeted posting.']);

    $db->prepare(
        "INSERT INTO pos_job_postings_meta (job_posting_id, pos_role_target, pos_position_label, created_by)
         VALUES (?, ?, ?, ?)"
    )->execute([$jobId, $role, $label, $byId]);

    $db->prepare(
        "INSERT INTO audit_logs (user_id, username, action, module, description, ip_address, user_agent)
         VALUES (?, ?, 'CREATE', 'POS Integration', ?, ?, ?)"
    )->execute([
        $byId, $byUser,
        "Tagged job posting #{$jobId} \"{$posting['title']}\" as POS-targeted ({$label} / {$role})",
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    jsonOut([
        'success'        => true,
        'icon'           => 'success',
        'title'          => '✅ Job Posting Tagged!',
        'message'        => '<strong>"'.htmlspecialchars($posting['title']).'"</strong> is now a POS-targeted opening for <em>'.htmlspecialchars($label).'</em> (<code>'.htmlspecialchars($role).'</code>). Hired applicants keep the HRMS Employee role and receive this POS role.',
        'job_posting_id' => $jobId,
        'pos_role'       => $role,
    ]);

} catch (PDOException $e) {
    jsonOut(['success'=>false,'icon'=>'error','title'=>'Database Error',
             'message'=>'An error occurred. Please try again.'], 500);
}
