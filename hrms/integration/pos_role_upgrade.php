<?php
/**
 * integration/pos_role_upgrade.php  — HRMS side  (NEW file)
     * Assigns the requested POS role after verifying
 * the linked HRMS applicant is hired.
 *
 * KEY GUARANTEES:
 *  ✅ Only milk_tea_pos.users.role is written — HRMS roles are NEVER changed.
 *  ✅ Two-phase commit: POS write → HRMS audit. Compensating rollback if audit fails.
 *  ✅ Idempotency guard: duplicate upgrades for the same applicant are blocked.
 *  ✅ Full audit trail in pos_role_upgrade_log + audit_logs.
 *  ✅ Email notification sent to POS staff member after upgrade.
 *
 * Method : POST  |  Access: HRMS role = hr | admin
 * Returns: JSON shaped for SweetAlert2
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

// ── Auth guard ────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['hr', 'admin'], true)) {
    jsonOut(['success'=>false,'icon'=>'error',
             'title'=>'Access Denied','message'=>'Only HR and Admin can perform POS upgrades.'], 403);
}

// ── Input ─────────────────────────────────────────────────────────────────────
$appId    = filter_input(INPUT_POST, 'hrms_applicant_id', FILTER_VALIDATE_INT);
$posEmail = trim(filter_input(INPUT_POST, 'pos_email',    FILTER_SANITIZE_EMAIL) ?? '');
$posUser  = trim($_POST['pos_username'] ?? '');
$jobId    = filter_input(INPUT_POST, 'job_posting_id',    FILTER_VALIDATE_INT);
$byId     = (int)$_SESSION['user_id'];
$byUser   = (string)($_SESSION['username'] ?? 'unknown');
$ip       = $_SERVER['REMOTE_ADDR'] ?? null;
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;

if (!$appId || $appId <= 0)
    jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Field','message'=>'A valid HRMS Applicant ID is required.']);
if (empty($posEmail) && empty($posUser))
    jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Field','message'=>'Enter the POS email address or username.']);

try {
    $db  = getDB();
    $pos = getPosDB();
    ensurePosIntegrationSchema($pos);

    // ── 1. Verify applicant is hired and job is POS-tagged ────────────────────
    $stA = $db->prepare(
        "SELECT a.id, a.firstname, a.lastname, a.email, a.status, a.job_id,
                jp.title AS job_title,
                pm.pos_role_target, pm.pos_position_label
           FROM applicants a
      LEFT JOIN job_postings jp ON jp.id = a.job_id
      LEFT JOIN pos_job_postings_meta pm ON pm.job_posting_id = a.job_id
          WHERE a.id = ? LIMIT 1"
    );
    $stA->execute([$appId]);
    $applicant = $stA->fetch();

    if (!$applicant)
        jsonOut(['success'=>false,'icon'=>'error','title'=>'Not Found','message'=>"Applicant #{$appId} not found."]);
    if ($applicant['status'] !== 'hired')
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'Not Hired Yet',
                 'message'=>'<strong>'.htmlspecialchars($applicant['firstname'].' '.$applicant['lastname']).'</strong>'
                          .' has status <em>'.$applicant['status'].'</em>. Only <strong>hired</strong> applicants can be upgraded.']);
    if (empty($applicant['pos_role_target']))
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Not a POS Role',
                 'message'=>'Job posting <em>"'.htmlspecialchars($applicant['job_title']).'"</em> is not tagged as a POS opening. Tag it first in the POS Integration dashboard.']);

    $targetRole = in_array($applicant['pos_role_target'], ['super_admin','cashier','inventory_staff'], true)
        ? $applicant['pos_role_target'] : posRoleFromJobTitle($applicant['job_title']);
    if (!$targetRole)
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'Not a POS Role','message'=>'Only POS Super Admin, Cashier, and Inventory Staff applicants can be assigned to POS.']);
    $posLabel   = $applicant['pos_position_label'];

    // ── 2. Locate the POS user ─────────────────────────────────────────────────
    $posRecord = null;
    if (!empty($posEmail))  $posRecord = findPosUserByEmail($posEmail);
    if (!$posRecord && !empty($posUser)) $posRecord = findPosUserByUsername($posUser);

    if (!$posRecord) {
        $id = !empty($posEmail) ? $posEmail : $posUser;
        jsonOut(['success'=>false,'icon'=>'error','title'=>'POS User Not Found',
                 'message'=>'No POS account found for <strong>'.htmlspecialchars($id).'</strong>.']);
    }
    if ((int)$posRecord['is_active'] !== 1)
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'POS Account Inactive',
                 'message'=>'POS account <strong>'.htmlspecialchars($posRecord['username']).'</strong> is inactive.']);

    if ($posRecord['role'] === $targetRole)
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Assigned',
                 'message'=>'POS user <strong>'.htmlspecialchars($posRecord['username']).'</strong> already has this role — no change needed.']);

    $oldRole  = $posRecord['role'];
    $posUid   = (int)$posRecord['id'];
    $posUname = $posRecord['username'];
    $posUemail= $posRecord['email'];

    // ── 3. Idempotency guard ───────────────────────────────────────────────────
    $dup = $db->prepare(
        "SELECT id FROM pos_role_upgrade_log
          WHERE hrms_applicant_id = ? AND pos_user_id = ?
            AND new_pos_role = ? AND status = 'success' LIMIT 1"
    );
    $dup->execute([$appId, $posUid, $targetRole]);
    if ($dup->fetch())
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Processed',
                 'message'=>'This upgrade was already applied successfully. No changes made.']);

    // ── 4. Phase A — UPDATE POS role ──────────────────────────────────────────
    $pos->beginTransaction();
    $upd = $pos->prepare("UPDATE users SET role = ? WHERE id = ? AND role != ?");
    $upd->execute([$targetRole, $posUid, $targetRole]);
    $rows = $upd->rowCount();
    if ($rows === 0) {
        $pos->rollBack();
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'No Change',
                 'message'=>'POS user <strong>'.htmlspecialchars($posUname).'</strong> could not be upgraded (already super_admin?).']);
    }
    $pos->commit();

    // ── 5. Phase B — HRMS audit ───────────────────────────────────────────────
    try {
        $db->beginTransaction();

        $db->prepare(
            "INSERT INTO pos_role_upgrade_log
                   (hrms_applicant_id, hrms_user_id, pos_user_id, pos_username, pos_email,
                    old_pos_role, new_pos_role, hrms_role_unchanged,
                    job_posting_id, performed_by, performed_by_username, notes, status)
             VALUES (?, NULL, ?, ?, ?, ?, ?, 'employee', ?, ?, ?, ?, 'success')"
        )->execute([
            $appId, $posUid, $posUname, $posUemail,
            $oldRole, $targetRole,
            $jobId ?: $applicant['job_id'],
            $byId, $byUser,
            "Upgraded {$applicant['firstname']} {$applicant['lastname']} (app #{$appId}) from {$oldRole} to {$targetRole}. HRMS role kept as employee.",
        ]);

        $db->prepare(
            "INSERT INTO audit_logs (user_id, username, action, module, description, old_value, new_value, ip_address, user_agent)
             VALUES (?, ?, 'UPDATE', 'POS Integration', ?, ?, ?, ?, ?)"
        )->execute([
            $byId, $byUser,
            "POS role upgrade: {$applicant['firstname']} {$applicant['lastname']} → POS '{$posUname}' promoted to {$targetRole} ({$posLabel}). HRMS role unchanged (employee).",
            "pos_role={$oldRole}", "pos_role={$targetRole}", $ip, $ua,
        ]);

        // Notify all HR/Admin
        $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','hr') AND status='active'")->fetchAll();
        $notif  = $db->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link)
             VALUES (?, '🎯 POS Role Upgraded', ?, 'success', '/hrms/integration/pos_upgrade_dashboard.php')"
        );
        foreach ($admins as $a) {
            $notif->execute([
                $a['id'],
                "{$applicant['firstname']} {$applicant['lastname']} (POS: {$posUname}) promoted to {$posLabel}. HRMS role stays employee.",
            ]);
        }

        $db->commit();

    } catch (PDOException $logErr) {
        if ($db->inTransaction()) $db->rollBack();

        // Compensating rollback — revert the POS change
        $rollNote = '';
        try {
            $pos->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$oldRole, $posUid]);
            $rollNote = ' POS role was automatically reversed.';
        } catch (PDOException $rbEx) {
            $rollNote = " ⚠️ POS rollback FAILED — manually reset '{$posUname}' to '{$oldRole}'.";
        }

        try {
            $db->prepare(
                "INSERT INTO pos_role_upgrade_log
                       (hrms_applicant_id, pos_user_id, pos_username, old_pos_role, new_pos_role,
                        performed_by, performed_by_username, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'rolled_back')"
            )->execute([$appId, $posUid, $posUname, $oldRole, $targetRole, $byId, $byUser, 'Audit logging failed.'.$rollNote]);
        } catch (PDOException $ignored) {}

        jsonOut(['success'=>false,'icon'=>'error','title'=>'Logging Failed',
                 'message'=>'Upgrade audit failed.'.$rollNote], 500);
    }

    // Persist the same POS assignment on an existing HRMS employee record.
    // This does not change the HRMS login role.
    $hrmsUserStmt = $db->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
    $hrmsUserStmt->execute([$posRecord['username'], $posRecord['email'] ?? '']);
    $hrmsUser = $hrmsUserStmt->fetch();
    if ($hrmsUser) {
        $db->prepare("UPDATE users SET pos_role=? WHERE id=? AND role='employee'")->execute([$targetRole, $hrmsUser['id']]);
    }

    // ── 6. Email notification to the POS user ─────────────────────────────────
    if (!empty($posUemail)) {
        $emailSubject = "🎉 Congratulations! You've been added as POS Staff";
        $emailBody    = "Hello {$posUname},\n\n"
            . "Great news! Your POS account has been upgraded to the role of \"{$posLabel}\" "
            . "on the Milk Tea POS system.\n\n"
            . "  • Previous Role : {$oldRole}\n"
            . "  • New Role      : {$targetRole} ({$posLabel})\n"
            . "  • HRMS Role     : employee (unchanged)\n\n"
            . "You can now log in to the POS system with your existing credentials and access the additional features that come with your new role.\n\n"
            . "This change was made by the HR/Admin team ({$byUser}).\n\n"
            . "If you have any questions, please contact your HR department.\n\n"
            . "Congratulations again!\n"
            . "— HRMS System";
        sendSmtpEmail($posUemail, $emailSubject, $emailBody);
    }

    // ── 7. Success ────────────────────────────────────────────────────────────
    jsonOut([
        'success'      => true,
        'icon'         => 'success',
        'title'        => '🎉 POS Role Upgraded!',
        'message'      => '<strong>'.htmlspecialchars($applicant['firstname'].' '.$applicant['lastname']).'</strong>'
                        . ' is now a <strong>'.htmlspecialchars($posLabel).'</strong> on the POS system.<br><br>'
                        . '🟢 POS Role: <code>'.$oldRole.'</code> → <code>'.$targetRole.'</code><br>'
                        . '🔵 HRMS Role: <code>employee</code> (unchanged)<br>'
                        . (!empty($posUemail) ? '<br>📧 Notification email sent to <em>'.htmlspecialchars($posUemail).'</em>.' : ''),
        'pos_username' => $posUname,
        'old_pos_role' => $oldRole,
        'new_pos_role' => $targetRole,
    ]);

} catch (PDOException $e) {
    jsonOut(['success'=>false,'icon'=>'error','title'=>'Database Error',
             'message'=>'An unexpected error occurred. Please try again.'], 500);
}
