<?php
/**
 * integration/pos_application_handler.php  — HRMS side  (NEW file)
 * Handles POS role application lifecycle: submit, approve, reject.
 *
 * Actions (POST field: action):
 *   submit_pos_application   — POS portal user submits (no HRMS session needed)
 *   approve_pos_application  — HR approves  (HRMS session required)
 *   reject_pos_application   — HR rejects   (HRMS session required)
 *
 * Method : POST  |  Returns: JSON shaped for SweetAlert2
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/pos_common.php';

function jsonOut(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d);
    exit;
}

$action = trim($_POST['action'] ?? '');

// ═══════════════════════════════════════════════════════════════════════════════
// submit_pos_application  — called from POS portal, no HRMS session
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'submit_pos_application') {
    $posUid = filter_input(INPUT_POST, 'pos_user_id',    FILTER_VALIDATE_INT);
    $jobId  = filter_input(INPUT_POST, 'job_posting_id', FILTER_VALIDATE_INT);
    $note   = trim($_POST['cover_note'] ?? '');

    if (!$posUid) jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Data','message'=>'POS user ID required.'], 400);
    if (!$jobId)  jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Data','message'=>'Job posting ID required.'], 400);

    try {
        $pos = getPosDB();
        $db  = getDB();

        $pu = $pos->prepare("SELECT id,username,email,role,is_active FROM users WHERE id=? LIMIT 1");
        $pu->execute([$posUid]);
        $posUser = $pu->fetch();

        if (!$posUser)        jsonOut(['success'=>false,'icon'=>'error','title'=>'Not Found','message'=>'POS account not found.']);
        if (!$posUser['is_active']) jsonOut(['success'=>false,'icon'=>'warning','title'=>'Inactive','message'=>'Your POS account is inactive.']);
        if ($posUser['role'] === 'super_admin')
            jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Admin','message'=>'You already have the super_admin role on the POS system.']);

        $pj = $db->prepare(
            "SELECT jp.id, jp.title, jp.status, pm.pos_role_target, pm.pos_position_label
               FROM job_postings jp
               JOIN pos_job_postings_meta pm ON pm.job_posting_id = jp.id
              WHERE jp.id = ? AND jp.status = 'open' LIMIT 1"
        );
        $pj->execute([$jobId]);
        $job = $pj->fetch();
        if (!$job) jsonOut(['success'=>false,'icon'=>'error','title'=>'Not Available','message'=>'This posting is not accepting POS applications right now.']);

        $dup = $db->prepare(
            "SELECT id FROM pos_role_applications WHERE pos_user_id=? AND job_posting_id=? AND status IN ('pending','approved') LIMIT 1"
        );
        $dup->execute([$posUid, $jobId]);
        if ($dup->fetch())
            jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Applied',
                     'message'=>'You already applied for <strong>'.htmlspecialchars($job['title']).'</strong>.']);

        $db->prepare(
            "INSERT INTO pos_role_applications (pos_user_id, pos_username, pos_email, job_posting_id, cover_note, status)
             VALUES (?, ?, ?, ?, ?, 'pending')"
        )->execute([$posUid, $posUser['username'], $posUser['email'], $jobId, $note ?: null]);
        $newId = $db->lastInsertId();

        // Notify HR/Admin
        $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','hr') AND status='active'")->fetchAll();
        $notif  = $db->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link)
             VALUES (?, '📥 New POS Role Application', ?, 'info', '/hrms/integration/pos_upgrade_dashboard.php')"
        );
        foreach ($admins as $a) {
            $notif->execute([$a['id'], "POS user '{$posUser['username']}' applied for {$job['pos_position_label']}."]);
        }

        jsonOut([
            'success'        => true,
            'icon'           => 'success',
            'title'          => '✅ Application Submitted!',
            'message'        => 'Your application for <strong>'.htmlspecialchars($job['pos_position_label']).'</strong> was submitted. HR will review it and notify you.',
            'application_id' => (int)$newId,
        ]);

    } catch (PDOException $e) {
        jsonOut(['success'=>false,'icon'=>'error','title'=>'Server Error','message'=>'Could not submit. Please try again.'], 500);
    }
}

// ── HR/Admin session required for approve / reject ────────────────────────────
if (!in_array($action, ['approve_pos_application','reject_pos_application'], true)) {
    jsonOut(['success'=>false,'icon'=>'error','title'=>'Invalid Action','message'=>'Unknown action.'], 400);
}
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['hr','admin'], true)) {
    jsonOut(['success'=>false,'icon'=>'error','title'=>'Access Denied','message'=>'HR/Admin only.'], 403);
}

$appId  = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);
$notes  = trim($_POST['hr_notes'] ?? '');
$byId   = (int)$_SESSION['user_id'];
$byUser = (string)($_SESSION['username'] ?? 'unknown');

if (!$appId) jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Data','message'=>'Application ID required.'], 400);

try {
    $db = getDB();
    $st = $db->prepare(
        "SELECT pra.*, jp.title AS job_title, pm.pos_position_label, pm.pos_role_target
           FROM pos_role_applications pra
      LEFT JOIN job_postings jp ON jp.id = pra.job_posting_id
      LEFT JOIN pos_job_postings_meta pm ON pm.job_posting_id = pra.job_posting_id
          WHERE pra.id = ? LIMIT 1"
    );
    $st->execute([$appId]);
    $app = $st->fetch();

    if (!$app) jsonOut(['success'=>false,'icon'=>'error','title'=>'Not Found','message'=>'Application not found.']);
    if ($app['status'] !== 'pending')
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Processed',
                 'message'=>'Application is already <strong>'.$app['status'].'</strong>.']);
} catch (PDOException $e) {
    jsonOut(['success'=>false,'icon'=>'error','title'=>'DB Error','message'=>'Could not load application.'], 500);
}

// ── reject ────────────────────────────────────────────────────────────────────
if ($action === 'reject_pos_application') {
    try {
        $db->prepare(
            "UPDATE pos_role_applications SET status='rejected', reviewed_by=?, reviewed_at=NOW(), hr_notes=? WHERE id=? AND status='pending'"
        )->execute([$byId, $notes ?: null, $appId]);

        $db->prepare(
            "INSERT INTO audit_logs (user_id, username, action, module, description, ip_address)
             VALUES (?, ?, 'UPDATE', 'POS Integration', ?, ?)"
        )->execute([$byId, $byUser, "Rejected POS role app #{$appId} from '{$app['pos_username']}' for {$app['pos_position_label']}.", $_SERVER['REMOTE_ADDR'] ?? null]);

        // Email the applicant
        if (!empty($app['pos_email'])) {
            $body = "Hello {$app['pos_username']},\n\nYour application for the POS role \"{$app['pos_position_label']}\" has been reviewed.\n\nStatus: REJECTED\n"
                  . ($notes ? "Reason: {$notes}\n" : "")
                  . "\nIf you have questions, please contact HR.\n\n— HRMS System";
            sendSmtpEmail($app['pos_email'], "POS Role Application — Status Update", $body);
        }

        jsonOut(['success'=>true,'icon'=>'success','title'=>'Application Rejected',
                 'message'=>'Application from <strong>'.htmlspecialchars($app['pos_username']).'</strong> has been rejected.']);
    } catch (PDOException $e) {
        jsonOut(['success'=>false,'icon'=>'error','title'=>'DB Error','message'=>'Could not reject application.'], 500);
    }
}

// ── approve ───────────────────────────────────────────────────────────────────
if ($action === 'approve_pos_application') {
    try {
         $targetRole = in_array($app['pos_role_target'], ['super_admin', 'cashier', 'inventory_staff'], true)
            ? $app['pos_role_target'] : posRoleFromJobTitle($app['job_title']);
        if (!$targetRole) {
             jsonOut(['success'=>false,'icon'=>'warning','title'=>'Invalid POS Role','message'=>'Only POS Super Admin, Cashier, and Inventory Staff applications can be approved.'], 422);
        }
        $pos = getPosDB();
        ensurePosIntegrationSchema($pos);
        $posUser = $pos->prepare("SELECT id, role FROM users WHERE id=? LIMIT 1");
        $posUser->execute([(int)$app['pos_user_id']]);
        if (!$posUser->fetch()) {
            jsonOut(['success'=>false,'icon'=>'error','title'=>'POS User Not Found','message'=>'The linked POS account no longer exists.'], 404);
        }
        $pos->prepare("UPDATE users SET role=? WHERE id=?")->execute([$targetRole, (int)$app['pos_user_id']]);
         $hrmsUser = $db->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
         $hrmsUser->execute([$app['pos_username'], $app['pos_email'] ?? '']);
         $hrmsMatch = $hrmsUser->fetch();
         if ($hrmsMatch) {
             // POS authorization is separate; keep the HRMS login role as employee.
             $db->prepare("UPDATE users SET pos_role=? WHERE id=? AND role='employee'")
                ->execute([$targetRole, $hrmsMatch['id']]);
         }
        $db->prepare(
            "UPDATE pos_role_applications SET status='approved', reviewed_by=?, reviewed_at=NOW(), hr_notes=? WHERE id=? AND status='pending'"
        )->execute([$byId, $notes ?: null, $appId]);

        // Try to locate a matching hired HRMS applicant
        $linked = null;
        if (!empty($app['pos_email'])) {
            $lq = $db->prepare("SELECT id FROM applicants WHERE email=? AND job_id=? AND status='hired' LIMIT 1");
            $lq->execute([$app['pos_email'], $app['job_posting_id']]);
            $linked = $lq->fetch();
        }

        $db->prepare(
            "INSERT INTO audit_logs (user_id, username, action, module, description, ip_address)
             VALUES (?, ?, 'UPDATE', 'POS Integration', ?, ?)"
        )->execute([$byId, $byUser, "Approved POS role app #{$appId} from '{$app['pos_username']}' for {$app['pos_position_label']}.", $_SERVER['REMOTE_ADDR'] ?? null]);

        // Email the applicant
        if (!empty($app['pos_email'])) {
            $body = "Hello {$app['pos_username']},\n\nGreat news! Your application for the POS role \"{$app['pos_position_label']}\" has been APPROVED by HR.\n\nYour POS account will be upgraded shortly. Please log in to the POS system to access your new features.\n\nIf you have questions, contact HR.\n\n— HRMS System";
            sendSmtpEmail($app['pos_email'], "POS Role Application — APPROVED 🎉", $body);
        }

        jsonOut([
            'success'           => true,
            'icon'              => 'success',
            'title'             => '✅ Application Approved',
             'message'           => 'Application from <strong>'.htmlspecialchars($app['pos_username']).'</strong> approved and assigned the <code>'.htmlspecialchars($targetRole).'</code> POS role. Their HRMS login role remains <code>employee</code>.',
            'hrms_applicant_id' => $linked['id'] ?? null,
            'pos_username'      => $app['pos_username'],
            'pos_email'         => $app['pos_email'],
            'job_posting_id'    => $app['job_posting_id'],
        ]);
    } catch (PDOException $e) {
        jsonOut(['success'=>false,'icon'=>'error','title'=>'DB Error','message'=>'Could not approve application.'], 500);
    }
}
