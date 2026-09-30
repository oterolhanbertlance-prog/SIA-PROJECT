<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
    <!-- Apply saved theme BEFORE render to prevent flash -->
    <script>(function(){var t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-theme',t);}());</script>
</head>
<body>
<?php $currentUser = currentUser(); ?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" onclick="Sidebar.hideMobile()"></div>

<!-- ── Sidebar ──────────────────────────────────────────────────── -->
<nav class="sidebar">
    <a class="sidebar-brand" href="<?= SITE_URL ?>dashboard.php">
        <div class="sidebar-brand-icon"><i class="bi bi-cup-hot-fill"></i></div>
        <div class="sidebar-brand-text"><?= COMPANY_NAME ?><br><span class="sidebar-brand-sub"><?= htmlspecialchars($currentUser['username']) ?></span></div>
    </a>

    <div class="sidebar-nav">
        <!-- Dashboard -->
        <?php if (!hasRole('employee')): ?>
        <div class="nav-section">Main</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>dashboard.php"
               class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── RECRUITMENT (admin / hr) ───────────────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">Recruitment</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/recruitment/jobs.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'recruitment/jobs') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-briefcase-fill"></i></span>
                <span class="nav-text">Job Postings</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/recruitment/applicants.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'recruitment/applicants') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-people"></i></span>
                <span class="nav-text">Applicants</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/interviews/list.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'interviews') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-calendar-check-fill"></i></span>
                <span class="nav-text">Interviews</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/deployment/list.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'deployment') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-file-earmark-person-fill"></i></span>
                <span class="nav-text">Deployment & Contracts</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/recruitment/send_email.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'send_email') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-envelope-fill"></i></span>
                <span class="nav-text">Email Applicants</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/recruitment/replies.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'replies') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-reply-all-fill"></i></span>
                <span class="nav-text">
                    Applicant Replies
                    <?php try { $repDb=getDB(); $repCnt=$repDb->query("SELECT COUNT(*) FROM applicant_replies WHERE is_read_hr=0")->fetchColumn(); if($repCnt>0): ?><span class="badge bg-danger rounded-pill ms-1" style="font-size:.6rem;"><?= $repCnt ?></span><?php endif; } catch(Exception $e){} ?>
                </span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── EMPLOYEES (admin / hr) ─────────────────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">Employees</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/employees/list.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'employees/list') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-person-lines-fill"></i></span>
                <span class="nav-text">Employee List</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── INVENTORY (admin / hr / inventory_staff) ────────────── -->
        <?php if (hasRole('admin','hr','inventory_staff')): ?>
        <?php if (!hasRole('inventory_staff')): // admin/hr see section label ?>
        <div class="nav-section">Inventory</div>
        <?php else: ?>
        <div class="nav-section">My Tasks</div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/inventory/stock.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'inventory/stock') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-boxes"></i></span>
                <span class="nav-text">Stock Management</span>
            </a>
        </div>
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/inventory/reports.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'inventory/reports') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-graph-up"></i></span>
                <span class="nav-text">Inventory Reports</span>
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- ── TIMEKEEPING & PAYROLL (admin / hr) ────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">Timekeeping & Payroll</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>employee/timekeeping.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'employee/timekeeping') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-clock-fill"></i></span>
                <span class="nav-text">My Time In/Out</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/timekeeping/list.php"
               class="<?= (strpos($_SERVER['PHP_SELF'],'timekeeping/list') !== false) ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-clock-history"></i></span>
                <span class="nav-text">Timekeeping</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/payroll/list.php"
               class="<?= (strpos($_SERVER['PHP_SELF'],'payroll/list') !== false || strpos($_SERVER['PHP_SELF'],'payroll/process') !== false) ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-cash-stack"></i></span>
                <span class="nav-text">Payroll</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/payroll/list.php?view=history"
               class="<?= strpos($_SERVER['PHP_SELF'],'payroll/payslip') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-receipt"></i></span>
                <span class="nav-text">Payslip History</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── LEAVE (admin / hr) ─────────────────────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">Leave</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/leave/list.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'leave/list') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-calendar-x-fill"></i></span>
                <span class="nav-text">Leave Requests</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── FINANCE (admin only) ───────────────────────────────── -->
        <?php if (hasRole('admin')): ?>
        <div class="nav-section">Finance</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/finance/list.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'finance/list') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-coin"></i></span>
                <span class="nav-text">Finance Entries</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/finance/reports.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'finance/reports') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-bar-chart-line-fill"></i></span>
                <span class="nav-text">Finance Reports</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── REPORTS (admin / hr) ───────────────────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">Reports</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/reports/index.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'reports/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-file-earmark-bar-graph-fill"></i></span>
                <span class="nav-text">Reports</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── POS INTEGRATION (admin / hr) ──────────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">POS Integration</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>integration/pos_upgrade_dashboard.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'integration') !== false ? 'active' : '' ?>">
                <span class="nav-icon" style="color:#6f42c1;"><i class="bi bi-diagram-3-fill"></i></span>
                <span class="nav-text" style="color:#6f42c1;font-weight:600;">
                    POS Link
                    <?php
                    try {
                        $posAppDb  = getDB();
                        $posAppCnt = $posAppDb->query("SELECT COUNT(*) FROM pos_role_applications WHERE status='pending'")->fetchColumn();
                        if ($posAppCnt > 0): ?>
                        <span class="badge rounded-pill ms-1" style="background:#6f42c1;font-size:.6rem;"><?= $posAppCnt ?></span>
                    <?php endif; } catch (Throwable $e) {} ?>
                </span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── SETTINGS ───────────────────────────────────────────── -->
        <?php if (hasRole('admin','hr')): ?>
        <div class="nav-section">Settings</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/settings/branches.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'settings/branches') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-building"></i></span>
                <span class="nav-text">Branches</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/users/list.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'users/list') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
                <span class="nav-text">User Management</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if (hasRole('admin')): ?>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/users/approvals.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'users/approvals') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-person-check-fill"></i></span>
                <span class="nav-text">User Approvals</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/settings/smtp.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'settings/smtp') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-gear-fill"></i></span>
                <span class="nav-text">SMTP Settings</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── EMPLOYEE SELF-SERVICE ──────────────────────────────── -->
        <?php if (hasRole('employee')): ?>
        <div class="nav-section">Self Service</div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>employee/dashboard.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'employee/dashboard') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-house-fill"></i></span>
                <span class="nav-text">My Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>employee/timekeeping.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'employee/timekeeping') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-clock-fill"></i></span>
                <span class="nav-text">My Attendance</span>
            </a>
        </div>
        <?php if ($currentUser['emp_id']): ?>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/employees/profile.php?id=<?= $currentUser['emp_id'] ?>"
               class="<?= strpos($_SERVER['PHP_SELF'],'employees/profile') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-person-circle"></i></span>
                <span class="nav-text">My Profile</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/employees/edit_profile.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'edit_profile') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-pencil-square"></i></span>
                <span class="nav-text">Edit My Profile</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/leave/my_leave.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'my_leave') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-calendar-x"></i></span>
                <span class="nav-text">My Leaves</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/payroll/my_payslips.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'my_payslips') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-receipt"></i></span>
                <span class="nav-text">My Payslips</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>employee/payslip.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'employee/payslip') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-cash-coin"></i></span>
                <span class="nav-text">Latest Payslip</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= SITE_URL ?>modules/resignation/my_resignation.php"
               class="<?= strpos($_SERVER['PHP_SELF'],'my_resignation') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span>
                <span class="nav-text">Resignation</span>
            </a>
        </div>
        <?php endif; ?>
    </div><!-- /.sidebar-nav -->

</nav>

<!-- ── Topbar ────────────────────────────────────────────────────── -->
<div class="topbar">
    <div class="topbar-left">
        <button type="button" class="btn btn-sm btn-light" onclick="Sidebar.toggle()" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a href="<?= SITE_URL ?>dashboard.php" class="topbar-title text-decoration-none d-none d-md-inline">
            <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : SITE_NAME ?>
        </a>
    </div>
    <div class="topbar-right">
        <?php if (hasRole('admin','hr')): ?>
        <!-- Quick link: public job board -->
        <a href="<?= SITE_URL ?>apply.php" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="View Public Job Board">
            <i class="bi bi-briefcase me-1"></i><span class="d-none d-md-inline">Job Board</span>
        </a>
        <!-- Quick link: Email Applicants -->
        <a href="<?= SITE_URL ?>modules/recruitment/send_email.php" class="btn btn-sm btn-outline-success me-1" title="Email Applicants">
            <i class="bi bi-envelope-fill me-1"></i><span class="d-none d-md-inline">Email</span>
        </a>
        <!-- Quick link: POS Integration -->
        <a href="<?= SITE_URL ?>integration/pos_upgrade_dashboard.php" class="btn btn-sm me-1"
           style="background:#6f42c1;color:#fff;border:none;" title="POS Integration Dashboard">
            <i class="bi bi-diagram-3-fill me-1"></i><span class="d-none d-md-inline">POS</span>
        </a>
        <?php endif; ?>

        <!-- Dark mode toggle -->
        <button type="button" class="btn btn-sm btn-light me-1" onclick="ThemeManager.toggle()" title="Toggle dark mode">
            <i class="bi bi-moon-fill" id="themeIcon"></i>
        </button>

        <!-- Notifications -->
        <?php
        $notifCount = getUnreadNotifications($currentUser['id']);
        $notifDb    = getDB();
        $notifStmt  = $notifDb->prepare(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5"
        );
        $notifStmt->execute([$currentUser['id']]);
        $notifs = $notifStmt->fetchAll();
        if ($notifCount > 0) {
            $notifDb->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")
                    ->execute([$currentUser['id']]);
        }
        ?>
        <div class="dropdown me-2">
            <button type="button" class="btn btn-sm btn-light position-relative" data-bs-toggle="dropdown" title="Notifications">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($notifCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                      style="font-size:.6rem;"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:320px;max-height:340px;overflow-y:auto;">
                <li><h6 class="dropdown-header"><i class="bi bi-bell me-1"></i>Notifications</h6></li>
                <?php if (empty($notifs)): ?>
                <li><span class="dropdown-item text-muted small">No notifications yet</span></li>
                <?php endif; ?>
                <?php foreach ($notifs as $n):
                    $notifLink = !empty($n['link']) ? $n['link'] : SITE_URL . 'dashboard.php';
                ?>
                <li>
                    <a class="dropdown-item py-2 <?= $n['is_read'] ? '' : 'fw-semibold bg-primary-subtle' ?>"
                       href="<?= htmlspecialchars($notifLink) ?>">
                        <div class="small text-truncate" style="max-width:280px;"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;white-space:normal;">
                            <?= htmlspecialchars(substr($n['message'], 0, 80)) ?><?= strlen($n['message']) > 80 ? '…' : '' ?>
                        </div>
                        <div class="text-muted" style="font-size:.7rem;"><?= formatDateTime($n['created_at']) ?></div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown ms-1">
            <button type="button" class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" title="My Account">
                <div class="avatar avatar-sm avatar-placeholder rounded-circle" style="font-size:.72rem;line-height:28px;text-align:center;flex-shrink:0;">
                    <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                </div>
                <span class="d-none d-md-inline fw-semibold" style="font-size:.82rem;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($currentUser['username']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:200px;">
                <li><h6 class="dropdown-header small"><?= htmlspecialchars(roleDisplayLabel($currentUser)) ?></h6></li>
                <?php if ($currentUser['emp_id']): ?>
                <li>
                    <a class="dropdown-item" href="<?= SITE_URL ?>modules/employees/profile.php?id=<?= $currentUser['emp_id'] ?>">
                        <i class="bi bi-person me-2"></i>My Profile
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item" href="<?= SITE_URL ?>modules/employees/edit_profile.php">
                        <i class="bi bi-pencil-square me-2"></i>Edit My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= SITE_URL ?>modules/users/change_password.php">
                        <i class="bi bi-key me-2"></i>Change Password
                    </a>
                </li>
                <?php if (hasRole('employee')): ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-warning" href="<?= SITE_URL ?>modules/resignation/my_resignation.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Resignation Letter
                    </a>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#"
                       onclick="return confirmAction('<?= SITE_URL ?>logout.php', 'You will be logged out of HRMS.', {title:'Confirm Logout', icon:'warning', confirmText:'Logout', confirmColor:'#ef233c'})">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>

    </div>
</div>

<!-- ── Main Content ──────────────────────────────────────────────── -->
<div class="main-content">
<div class="content-wrapper">

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-auto-dismiss">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-auto-dismiss">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>
