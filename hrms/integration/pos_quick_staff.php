<?php
/**
 * integration/pos_quick_staff.php  — HRMS side  (NEW file)
 * Quick "Add as POS Staff" action directly from the Job Postings page.
 *
 * 1. Auto-tags the job as POS-targeted if not already tagged.
 * 2. Finds the POS user by email or username.
     * 3. Assigns their POS role in milk_tea_pos.
 * 4. Logs everything + sends email notification.
 * 5. HRMS roles are NEVER changed.
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
             'title'=>'Access Denied','message'=>'Only HR and Admin can add POS staff.'], 403);
}

// ── Input ─────────────────────────────────────────────────────────────────────
$jobId      = filter_input(INPUT_POST, 'job_id',          FILTER_VALIDATE_INT);
$posEmail   = trim(filter_input(INPUT_POST, 'pos_email',   FILTER_SANITIZE_EMAIL) ?? '');
$posUser    = trim($_POST['pos_username'] ?? '');
$targetRole = null;
$posLabel   = trim($_POST['pos_label'] ?? 'POS Staff');
$byId       = (int)$_SESSION['user_id'];
$byUser     = (string)($_SESSION['username'] ?? 'unknown');
$ip         = $_SERVER['REMOTE_ADDR'] ?? null;
$ua         = $_SERVER['HTTP_USER_AGENT'] ?? null;

if (!$jobId || $jobId <= 0)
    jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Field','message'=>'Job ID is required.']);
if (empty($posEmail) && empty($posUser))
    jsonOut(['success'=>false,'icon'=>'warning','title'=>'Missing Field','message'=>'Enter the POS staff email or username.']);
if (empty($posLabel) || mb_strlen($posLabel) < 2)
    $posLabel = 'POS Staff';

try {
    $db  = getDB();
    $pos = getPosDB();

    // ── 1. Verify job posting exists ──────────────────────────────────────────
    $jp = $db->prepare("SELECT id, title, status FROM job_postings WHERE id = ? LIMIT 1");
    $jp->execute([$jobId]);
    $posting = $jp->fetch();
    if (!$posting)
        jsonOut(['success'=>false,'icon'=>'error','title'=>'Not Found','message'=>"Job posting #{$jobId} not found."]);
    $targetRole = posRoleFromJobTitle($posting['title']);
    if (!$targetRole)
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'Not a POS Role','message'=>'Only POS Super Admin, Cashier, and Inventory Staff postings can create POS users.']);
    ensurePosIntegrationSchema($pos);

    // ── 2. Auto-tag as POS if not yet tagged ──────────────────────────────────
    $checkTag = $db->prepare("SELECT id, pos_role_target, pos_position_label FROM pos_job_postings_meta WHERE job_posting_id = ? LIMIT 1");
    $checkTag->execute([$jobId]);
    $existingTag = $checkTag->fetch();
    $autoTagged  = false;

    if (!$existingTag) {
        // Tag it now with defaults
        $db->prepare(
            "INSERT INTO pos_job_postings_meta (job_posting_id, pos_role_target, pos_position_label, created_by) VALUES (?, ?, ?, ?)"
        )->execute([$jobId, $targetRole, $posLabel, $byId]);

        $db->prepare(
            "INSERT INTO audit_logs (user_id, username, action, module, description, ip_address)
             VALUES (?, ?, 'CREATE', 'POS Integration', ?, ?)"
        )->execute([$byId, $byUser, "Auto-tagged job posting #{$jobId} \"{$posting['title']}\" as POS-targeted ({$posLabel})", $ip]);

        $autoTagged = true;
    } else {
        if (in_array($existingTag['pos_role_target'], ['super_admin','cashier','inventory_staff'], true)) {
            $targetRole = $existingTag['pos_role_target'];
        }
        $posLabel = $existingTag['pos_position_label'];
    }

    // ── 3. Locate POS user ────────────────────────────────────────────────────
    $posRecord = null;
    if (!empty($posEmail))  $posRecord = findPosUserByEmail($posEmail);
    if (!$posRecord && !empty($posUser)) $posRecord = findPosUserByUsername($posUser);

    if (!$posRecord) {
        $identifier = !empty($posEmail) ? $posEmail : $posUser;
        jsonOut(['success'=>false,'icon'=>'error','title'=>'POS User Not Found',
                 'message'=>'No POS account found for <strong>'.htmlspecialchars($identifier).'</strong>.<br><small>Make sure the POS system is running and the user exists.</small>']);
    }

    if ((int)$posRecord['is_active'] !== 1)
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'POS Account Inactive',
                 'message'=>'POS account <strong>'.htmlspecialchars($posRecord['username']).'</strong> is inactive. Activate it in the POS system first.']);

    // Keep HRMS' separate role field aligned as well. The HRMS login role is
    // deliberately left as employee.
    $hrmsUserStmt = $db->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
    $hrmsUserStmt->execute([$posRecord['username'], $posRecord['email'] ?? '']);
    $hrmsUser = $hrmsUserStmt->fetch();
    if ($hrmsUser) {
        $db->prepare("UPDATE users SET pos_role=? WHERE id=? AND role='employee'")->execute([$targetRole, $hrmsUser['id']]);
    }

    if ($posRecord['role'] === $targetRole)
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Assigned',
                 'message'=>'<strong>'.htmlspecialchars($posRecord['username']).'</strong> already has the '.$targetRole.' role on the POS system — no changes made.']);

    $oldRole  = $posRecord['role'];
    $posUid   = (int)$posRecord['id'];
    $posUname = $posRecord['username'];
    $posUemail= $posRecord['email'];

    // ── 4. Idempotency check ──────────────────────────────────────────────────
    $dup = $db->prepare(
        "SELECT id FROM pos_role_upgrade_log WHERE pos_user_id = ? AND new_pos_role = ? AND status = 'success' LIMIT 1"
    );
    $dup->execute([$posUid, $targetRole]);
    if ($dup->fetch())
        jsonOut(['success'=>false,'icon'=>'info','title'=>'Already Upgraded',
                 'message'=>'<strong>'.htmlspecialchars($posUname).'</strong> already has this POS role. Check the POS Integration History tab for details.']);

    // ── 5. Phase A — Update POS role ──────────────────────────────────────────
    $pos->beginTransaction();
    $upd = $pos->prepare("UPDATE users SET role = ? WHERE id = ? AND role != ?");
    $upd->execute([$targetRole, $posUid, $targetRole]);
    $rows = $upd->rowCount();
    if ($rows === 0) {
        $pos->rollBack();
        jsonOut(['success'=>false,'icon'=>'warning','title'=>'No Change',
                 'message'=>'Could not assign the POS role to <strong>'.htmlspecialchars($posUname).'</strong>. They may already have it.']);
    }
    $pos->commit();

    // ── 6. Phase B — HRMS audit ───────────────────────────────────────────────
    // Try to find a linked hired applicant for this job (optional)
    $linkedApp = null;
    if (!empty($posUemail)) {
        $lq = $db->prepare("SELECT id, firstname, lastname FROM applicants WHERE email=? AND job_id=? AND status='hired' LIMIT 1");
        $lq->execute([$posUemail, $jobId]);
        $linkedApp = $lq->fetch();
    }

    try {
        $db->beginTransaction();

        $db->prepare(
            "INSERT INTO pos_role_upgrade_log
                   (hrms_applicant_id, pos_user_id, pos_username, pos_email,
                    old_pos_role, new_pos_role, hrms_role_unchanged,
                    job_posting_id, performed_by, performed_by_username, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, 'employee', ?, ?, ?, ?, 'success')"
        )->execute([
            $linkedApp['id'] ?? null,
            $posUid, $posUname, $posUemail,
            $oldRole, $targetRole,
            $jobId, $byId, $byUser,
            "Quick staff assignment from Job Postings page: '{$posUname}' assigned POS {$targetRole} ({$posLabel}). Job: \"{$posting['title']}\". HRMS role unchanged."
                . ($autoTagged ? ' Job was auto-tagged as POS.' : ''),
        ]);

        $db->prepare(
            "INSERT INTO audit_logs (user_id, username, action, module, description, old_value, new_value, ip_address, user_agent)
             VALUES (?, ?, 'UPDATE', 'POS Integration', ?, ?, ?, ?, ?)"
        )->execute([
            $byId, $byUser,
            "Quick POS Staff: '{$posUname}' assigned {$targetRole} for job \"{$posting['title']}\". HRMS role unchanged.",
            "pos_role={$oldRole}", "pos_role={$targetRole}", $ip, $ua,
        ]);

        // Notify all HR/Admin
        $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','hr') AND status='active'")->fetchAll();
        $notif  = $db->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link)
             VALUES (?, '🎯 POS Staff Added', ?, 'success', '/hrms/integration/pos_upgrade_dashboard.php')"
        );
        foreach ($admins as $adm) {
            $notif->execute([$adm['id'], "'{$posUname}' added as POS {$targetRole} ({$posLabel}) for job \"{$posting['title']}\". HRMS role stays employee."]);
        }

        $db->commit();

    } catch (PDOException $logErr) {
        if ($db->inTransaction()) $db->rollBack();
        // Compensating rollback
        $rollNote = '';
        try {
            $pos->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$oldRole, $posUid]);
            $rollNote = ' POS role has been automatically reversed.';
        } catch (PDOException $rb) {
            $rollNote = " ⚠️ Rollback FAILED — please manually reset '{$posUname}' to '{$oldRole}' in POS.";
        }
        jsonOut(['success'=>false,'icon'=>'error','title'=>'Logging Failed',
                 'message'=>'Upgrade audit failed.'.$rollNote], 500);
    }

    // ── 7. Email notification ─────────────────────────────────────────────────
    if (!empty($posUemail)) {
        $emailSubject = "🎉 You've been added as POS Staff — {$posLabel}";
        $emailBody    = "Hello {$posUname},\n\n"
            . "You have been added as a staff member on the Milk Tea POS system.\n\n"
            . "  • Role Granted : {$targetRole} ({$posLabel})\n"
            . "  • Job Posting  : {$posting['title']}\n"
            . "  • Added By     : {$byUser} (HR/Admin)\n"
            . "  • HRMS Role    : employee (unchanged)\n\n"
            . "You can now log in to the POS system with your existing credentials and access your new admin features.\n\n"
            . "If you have questions, contact your HR department.\n\n"
            . "Congratulations!\n"
            . "— HRMS System";
        sendSmtpEmail($posUemail, $emailSubject, $emailBody);
    }

    // ── 8. Success response ───────────────────────────────────────────────────
    jsonOut([
        'success'      => true,
        'icon'         => 'success',
        'title'        => '🎉 POS Staff Added Successfully!',
        'message'      => '<strong>'.htmlspecialchars($posUname).'</strong> is now a <strong>POS '.$targetRole.'</strong>.<br><br>'
                        . '🟢 POS Role: <code>'.$oldRole.'</code> → <code>'.$targetRole.'</code><br>'
                        . '🔵 HRMS Role: <code>employee</code> (unchanged — not an HR admin)<br>'
                        . '📋 Job: <em>'.htmlspecialchars($posting['title']).'</em>'
                        . ($autoTagged ? '<br>🏷️ Job was automatically tagged as POS-targeted.' : '')
                        . (!empty($posUemail) ? '<br>📧 Notification email sent to <em>'.htmlspecialchars($posUemail).'</em>.' : ''),
        'pos_username' => $posUname,
        'old_pos_role' => $oldRole,
        'new_pos_role' => $targetRole,
    ]);

} catch (PDOException $e) {
    jsonOut(['success'=>false,'icon'=>'error','title'=>'Database Error',
             'message'=>'An unexpected error occurred. Please try again.'], 500);
}
