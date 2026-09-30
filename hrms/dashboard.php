<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
requireLogin();

// Employees have their own richer dashboard
if (hasRole('employee')) {
    header('Location: ' . SITE_URL . 'employee/dashboard.php');
    exit;
}

$pageTitle = 'Dashboard';
$db    = getDB();
$cu    = currentUser();
$isEmployee = hasRole('employee');

// Handle inline leave approve/reject (POST + CSRF) — admin/hr only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_action'])) {
    if (verifyCSRF($_POST['csrf_token'] ?? '') && hasRole('admin', 'hr')) {
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $wantsApprove = $_POST['leave_action'] === 'approved';
        if ($leaveId) {
            $lr = $db->prepare("SELECT * FROM leave_requests WHERE id = ? AND status IN ('pending','admin_approved')");
            $lr->execute([$leaveId]);
            $leave = $lr->fetch();
            if ($leave) {
                if (!$wantsApprove) {
                    $db->prepare("UPDATE leave_requests SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")
                       ->execute([$cu['id'], $leaveId]);
                    $db->prepare(
                        "UPDATE leave_balance SET used_days = used_days - ?, remaining_days = remaining_days + ?
                         WHERE employee_id = ? AND leave_type_id = ? AND year = ?"
                    )->execute([$leave['total_days'], $leave['total_days'], $leave['employee_id'], $leave['leave_type_id'], date('Y')]);
                    auditLog('UPDATE', 'Leave', "Leave #{$leaveId} rejected from dashboard");
                } elseif (hasRole('hr')) {
                    $db->prepare("UPDATE leave_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
                       ->execute([$cu['id'], $leaveId]);
                    auditLog('UPDATE', 'Leave', "Leave #{$leaveId} officially approved by HR from dashboard");
                } elseif (hasRole('admin') && $leave['status'] === 'pending') {
                    $db->prepare("UPDATE leave_requests SET status='admin_approved', admin_approved_by=?, admin_approved_at=NOW() WHERE id=?")
                       ->execute([$cu['id'], $leaveId]);
                    auditLog('UPDATE', 'Leave', "Leave #{$leaveId} approved by Admin from dashboard, forwarded to HR");
                }
            }
        }
    }
    header('Location: dashboard.php');
    exit;
}

if ($isEmployee) {
    // ── EMPLOYEE DASHBOARD DATA ───────────────────────────────
    $empId = $cu['emp_id'] ?? 0;

    // My leave balance summary
    $myLeaveBalance = $db->prepare(
        "SELECT lb.*, lt.name FROM leave_balance lb
         JOIN leave_types lt ON lb.leave_type_id = lt.id
         WHERE lb.employee_id = ? AND lb.year = ?
         ORDER BY lt.name"
    );
    $myLeaveBalance->execute([$empId, date('Y')]);
    $myLeaveBalance = $myLeaveBalance->fetchAll();

    // My pending/recent leave requests
    $myLeaves = $db->prepare(
        "SELECT lr.*, lt.name as leave_type_name
         FROM leave_requests lr
         JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE lr.employee_id = ?
         ORDER BY lr.created_at DESC LIMIT 5"
    );
    $myLeaves->execute([$empId]);
    $myLeaves = $myLeaves->fetchAll();

    // My latest payslip
    $myLatestPayslip = $db->prepare(
        "SELECT * FROM payroll WHERE employee_id = ? ORDER BY payroll_year DESC, payroll_month DESC LIMIT 1"
    );
    $myLatestPayslip->execute([$empId]);
    $myLatestPayslip = $myLatestPayslip->fetch(); // Returns array or false

    // My recent payslips for mini-chart (last 6)
    $myPayrollChart = $db->prepare(
        "SELECT payroll_month, payroll_year, net_pay
         FROM payroll WHERE employee_id = ? ORDER BY payroll_year DESC, payroll_month DESC LIMIT 6"
    );
    $myPayrollChart->execute([$empId]);
    $myPayrollChart = array_reverse($myPayrollChart->fetchAll());

    // My pending leaves count
    $myPendingLeaves = $db->prepare(
        "SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'"
    );
    $myPendingLeaves->execute([$empId]);
    $myPendingLeaves = $myPendingLeaves->fetchColumn();

    // My employee info
    $myEmpInfo = $db->prepare(
        "SELECT e.*, d.name as dept_name, p.title as pos_title
         FROM employees e
         LEFT JOIN departments d ON e.department_id = d.id
         LEFT JOIN positions p ON e.position_id = p.id
         WHERE e.id = ?"
    );
    $myEmpInfo->execute([$empId]);
    $myEmpInfo = $myEmpInfo->fetch(); // Returns array or false

    // My resignation (if any pending)
    $myResignation = null;
    $resStmt = $db->prepare(
        "SELECT * FROM resignation_letters WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1"
    );
    $resStmt->execute([$empId]);
    $myResignation = $resStmt->fetch(); // Returns array or false

    $myPayrollJson = json_encode(array_values($myPayrollChart ?: []));

    include 'includes/header.php';
    ?>
    <!-- Employee Dashboard -->
    <div class="row g-3 mb-4">
        <!-- My Profile Card -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body text-center py-4">
                    <?php
                    $pic = (is_array($myEmpInfo) && !empty($myEmpInfo['profile_picture']) && $myEmpInfo['profile_picture'] !== 'default.png')
                        ? SITE_URL . 'uploads/profiles/' . $myEmpInfo['profile_picture']
                        : SITE_URL . 'assets/images/default.png';
                    ?>
                    <img src="<?= $pic ?>" class="rounded-circle mb-3" style="width:80px;height:80px;object-fit:cover;" onerror="this.src='<?=SITE_URL?>assets/images/default.png'">
                    <h6 class="fw-bold mb-1">
                        <?= is_array($myEmpInfo) ? htmlspecialchars(($myEmpInfo['firstname'] ?? '') . ' ' . ($myEmpInfo['lastname'] ?? '')) : 'New Employee' ?>
                    </h6>
                    <p class="text-muted small mb-1"><?= is_array($myEmpInfo) ? htmlspecialchars($myEmpInfo['pos_title'] ?? '—') : '—' ?></p>
                    <p class="text-muted small mb-2"><?= is_array($myEmpInfo) ? htmlspecialchars($myEmpInfo['dept_name'] ?? '—') : '—' ?></p>
                    <?= statusBadge(is_array($myEmpInfo) ? ($myEmpInfo['employment_status'] ?? 'active') : 'active') ?>
                    <div class="mt-3">
                        <a href="<?=SITE_URL?>modules/employees/profile.php?id=<?=$empId?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-person me-1"></i>View Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Latest Payslip -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-2 me-3">
                            <i class="bi bi-receipt fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Latest Payslip</div>
                            <div class="fw-bold">
                                <?= (is_array($myLatestPayslip) && isset($myLatestPayslip['payroll_month'])) ? monthName($myLatestPayslip['payroll_month']).' '.$myLatestPayslip['payroll_year'] : '—' ?>
                            </div>
                        </div>
                    </div>
                    <?php if (is_array($myLatestPayslip)): ?>
                    <div class="row g-2 small">
                        <div class="col-6 text-muted">Gross Pay</div>
                        <div class="col-6 text-end fw-semibold"><?= money($myLatestPayslip['gross_pay'] ?? 0) ?></div>
                        <div class="col-6 text-muted">Deductions</div>
                        <div class="col-6 text-end text-danger fw-semibold"><?= money($myLatestPayslip['total_deductions'] ?? 0) ?></div>
                        <div class="col-6 text-muted fw-semibold">Net Pay</div>
                        <div class="col-6 text-end fw-bold text-success"><?= money($myLatestPayslip['net_pay'] ?? 0) ?></div>
                    </div>
                    <div class="mt-3">
                        <a href="<?=SITE_URL?>modules/payroll/my_payslips.php" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download me-1"></i>All Payslips
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="text-muted small">No payslips yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- My Leave Balance -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-2 me-3">
                            <i class="bi bi-calendar-week fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Leave Balance <?= date('Y') ?></div>
                            <div class="fw-bold"><?= $myPendingLeaves ?: 0 ?> Pending</div>
                        </div>
                    </div>
                    <?php if (!empty($myLeaveBalance) && is_array($myLeaveBalance)): ?>
                        <?php foreach ($myLeaveBalance as $lb): ?>
                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span class="text-muted"><?= htmlspecialchars($lb['name']) ?></span>
                            <span class="fw-semibold <?= ($lb['remaining_days'] ?? 0) <= 0 ? 'text-danger' : 'text-success' ?>">
                                <?= max(0, $lb['remaining_days'] ?? 0) ?> / <?= $lb['total_days'] ?? 0 ?> days
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small">No leave balance found.</div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="<?=SITE_URL?>modules/leave/add.php" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-plus me-1"></i>File Leave
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</div>
                    <div class="d-grid gap-2">
                        <a href="<?=SITE_URL?>modules/leave/my_leave.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-calendar-check me-2"></i>My Leave Requests
                        </a>
                        <a href="<?=SITE_URL?>modules/payroll/my_payslips.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-receipt me-2"></i>My Payslips
                        </a>
                        <a href="<?=SITE_URL?>modules/employees/profile.php?id=<?=$empId?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-person me-2"></i>My Profile
                        </a>
                        <a href="<?=SITE_URL?>modules/resignation/my_resignation.php" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-box-arrow-right me-2"></i>Resignation
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Net Pay Chart + My Recent Leaves -->
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-graph-up me-2 text-success"></i>My Net Pay — Last 6 Months</div>
                <div class="card-body">
                    <div class="chart-container" style="height:220px;">
                        <canvas id="myPayrollChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-calendar me-2 text-primary"></i>My Recent Leave Requests</span>
                    <a href="<?=SITE_URL?>modules/leave/add.php" class="btn btn-sm btn-primary py-0 px-2"><i class="bi bi-plus-lg"></i> File</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!empty($myLeaves) && is_array($myLeaves)): ?>
                            <?php foreach ($myLeaves as $lv): ?>
                            <tr>
                                <td><?= htmlspecialchars($lv['leave_type_name']) ?></td>
                                <td><?= formatDate($lv['start_date']) ?></td>
                                <td><?= formatDate($lv['end_date']) ?></td>
                                <td><?= $lv['total_days'] ?></td>
                                <td><?= statusBadge($lv['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No leave requests yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (is_array($myResignation)): ?>
    <div class="alert alert-<?= $myResignation['status'] === 'pending' ? 'warning' : ($myResignation['status'] === 'approved' ? 'success' : 'danger') ?> mt-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Resignation Letter</strong> — Status: <strong><?= ucfirst($myResignation['status']) ?></strong>
        (Submitted <?= formatDate($myResignation['created_at']) ?>)
        <a href="<?=SITE_URL?>modules/resignation/my_resignation.php" class="alert-link ms-2">View Details →</a>
    </div>
    <?php endif; ?>

    <?php
    $extraJs = <<<JS
    <script>
    const myPayrollData = {$myPayrollJson};
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    if (myPayrollData && myPayrollData.length > 0) {
        new Chart(document.getElementById('myPayrollChart'), {
            type: 'bar',
            data: {
                labels: myPayrollData.map(r => months[r.payroll_month-1]+' '+r.payroll_year),
                datasets: [{
                    label: 'Net Pay',
                    data: myPayrollData.map(r => r.net_pay),
                    backgroundColor: '#06d6a0',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { ticks: { callback: v => '₱'+Number(v).toLocaleString() }, grid: { color: 'rgba(0,0,0,.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    </script>
    JS;
    include 'includes/footer.php';

} else {
    // ── ADMIN / HR DASHBOARD DATA ──────────────────────────────
    $stats = getDashboardStats();

    // Recent activities
    $activities = $db->query(
        "SELECT al.*, u.username
         FROM audit_logs al
         LEFT JOIN users u ON al.user_id = u.id
         ORDER BY al.created_at DESC LIMIT 8"
    )->fetchAll();

    // Department headcount for chart
    $deptData = $db->query(
        "SELECT d.name, COUNT(e.id) as count
         FROM departments d
         LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
         GROUP BY d.id, d.name
         ORDER BY count DESC LIMIT 7"
    )->fetchAll();

    // Monthly payroll for chart (current year, released)
    $payrollChart = $db->query(
        "SELECT payroll_month, payroll_year, SUM(net_pay) as total
         FROM payroll
         WHERE payroll_year = YEAR(NOW()) AND status = 'released'
         GROUP BY payroll_month, payroll_year
         ORDER BY payroll_month"
    )->fetchAll();

    // Leave requests by type (current year)
    $leaveStats = $db->query(
        "SELECT lt.name, COUNT(lr.id) as cnt
         FROM leave_types lt
         LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id
           AND YEAR(lr.created_at) = YEAR(NOW())
         GROUP BY lt.id, lt.name"
    )->fetchAll();

    // Upcoming interviews
    $upcomingInterviews = $db->query(
        "SELECT i.*, a.firstname, a.lastname, j.title as job_title
         FROM interviews i
         JOIN applicants a ON i.applicant_id = a.id
         LEFT JOIN job_postings j ON a.job_id = j.id
         WHERE i.interview_date >= NOW() AND i.status = 'scheduled'
         ORDER BY i.interview_date LIMIT 5"
    )->fetchAll();

    // Pending leave requests (awaiting Admin's first review, or awaiting HR's final sign-off)
    $pendingLeaves = $db->query(
        "SELECT lr.*, e.firstname, e.lastname, e.employee_id as emp_code, lt.name as leave_type_name
         FROM leave_requests lr
         JOIN employees e ON lr.employee_id = e.id
         JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE lr.status IN ('pending','admin_approved')
         ORDER BY lr.created_at DESC LIMIT 5"
    )->fetchAll();

    // Pending resignations (awaiting Admin's first review, or awaiting HR's final sign-off)
    $pendingResignations = $db->query(
        "SELECT rl.*, e.firstname, e.lastname, e.employee_id as emp_code
         FROM resignation_letters rl
         JOIN employees e ON rl.employee_id = e.id
         WHERE rl.status IN ('pending','admin_approved')
         ORDER BY rl.created_at DESC LIMIT 5"
    )->fetchAll();

    // Pre-encode chart data
    $payrollJson = json_encode(array_values($payrollChart ?: []));
    $deptJson    = json_encode(array_values($deptData ?: []));
    $leaveJson   = json_encode(array_values($leaveStats ?: []));

    include 'includes/header.php';
    ?>

    <!-- Stat Cards -->
    <?php
    $emailUnread = 0;
    try { $emailUnread = (int)getDB()->query("SELECT COUNT(*) FROM applicant_replies WHERE is_read_hr=0")->fetchColumn(); } catch(Exception $e){}
    ?>
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/employees/list.php" class="text-decoration-none">
            <div class="stat-card bg-gradient-blue" style="cursor:pointer;">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= $stats['total_employees'] ?? 0 ?></div>
                <div class="stat-label">Total Employees</div>
            </div></a>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/employees/list.php?status=Regular" class="text-decoration-none">
            <div class="stat-card bg-gradient-green" style="cursor:pointer;">
                <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
                <div class="stat-value"><?= $stats['active_employees'] ?? 0 ?></div>
                <div class="stat-label">Regular Staff</div>
            </div></a>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/recruitment/applicants.php" class="text-decoration-none">
            <div class="stat-card bg-gradient-orange" style="cursor:pointer;">
                <div class="stat-icon"><i class="bi bi-person-plus-fill"></i></div>
                <div class="stat-value"><?= $stats['new_applicants'] ?? 0 ?></div>
                <div class="stat-label">New Applicants</div>
            </div></a>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/interviews/list.php" class="text-decoration-none">
            <div class="stat-card bg-gradient-purple" style="cursor:pointer;">
                <div class="stat-icon"><i class="bi bi-calendar-event-fill"></i></div>
                <div class="stat-value"><?= $stats['interviews_today'] ?? 0 ?></div>
                <div class="stat-label">Interviews Today</div>
            </div></a>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/leave/list.php" class="text-decoration-none">
            <div class="stat-card bg-gradient-teal" style="cursor:pointer;">
                <div class="stat-icon"><i class="bi bi-calendar-x-fill"></i></div>
                <div class="stat-value"><?= $stats['on_leave'] ?? 0 ?></div>
                <div class="stat-label">On Leave Today</div>
            </div></a>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/payroll/list.php" class="text-decoration-none">
            <div class="stat-card bg-gradient-pink" style="cursor:pointer;">
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value">₱<?= number_format((($stats['payroll_this_month'] ?? 0) / 1000), 0) ?>K</div>
                <div class="stat-label">Payroll This Month</div>
            </div></a>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <a href="<?= SITE_URL ?>modules/recruitment/send_email.php" class="text-decoration-none">
            <div class="stat-card bg-gradient-blue" style="cursor:pointer;background:linear-gradient(135deg,#0d6efd,#0dcaf0);">
                <div class="stat-icon"><i class="bi bi-envelope-fill"></i></div>
                <div class="stat-value"><?= $emailUnread ?></div>
                <div class="stat-label">Email <?= $emailUnread > 0 ? '<span style="font-size:.65rem;background:rgba(255,255,255,.3);padding:1px 5px;border-radius:8px;">unread replies</span>' : 'Applicants' ?></div>
            </div></a>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Monthly Net Payroll — <?= date('Y') ?></div>
                <div class="card-body"><div class="chart-container"><canvas id="payrollChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-building me-2 text-info"></i>Headcount by Dept</div>
                <div class="card-body"><div class="chart-container"><canvas id="deptChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-calendar-week me-2 text-success"></i>Leave by Type</div>
                <div class="card-body"><div class="chart-container"><canvas id="leaveChart"></canvas></div></div>
            </div>
        </div>
    </div>

    <!-- Pending Resignations (if any) -->
    <?php if (!empty($pendingResignations)): ?>
    <div class="card mb-4 border-danger">
        <div class="card-header fw-semibold text-danger d-flex justify-content-between align-items-center">
            <span><i class="bi bi-door-open me-2"></i>Pending Resignations</span>
            <a href="<?=SITE_URL?>modules/resignation/list.php" class="btn btn-sm btn-outline-danger py-0 px-2">View All</a>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>Employee</th><th>ID</th><th>Last Working Day</th><th>Submitted</th><th>Stage</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pendingResignations as $rr): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($rr['firstname'].' '.$rr['lastname']) ?></td>
                    <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($rr['emp_code']) ?></span></td>
                    <td><?= formatDate($rr['last_working_day']) ?></td>
                    <td class="text-muted"><?= formatDate($rr['created_at']) ?></td>
                    <td><?= statusBadge($rr['status']) ?></td>
                    <td><a href="<?=SITE_URL?>modules/resignation/list.php" class="btn btn-sm btn-warning py-0 px-2">Review</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bottom Row: Pending Leaves + Interviews + Activity -->
    <div class="row g-4">
        <!-- Pending Leaves -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-calendar-check me-2 text-warning"></i>Pending Leave Requests</span>
                    <a href="<?=SITE_URL?>modules/leave/list.php" class="btn btn-sm btn-outline-secondary py-0 px-2">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingLeaves)): ?>
                    <div class="text-center text-muted py-4 small">No pending leave requests.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead><tr><th>Employee</th><th>Type</th><th>Days</th><th>Stage</th><th class="text-center">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingLeaves as $lv): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($lv['firstname'].' '.$lv['lastname']) ?></div>
                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($lv['emp_code']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($lv['leave_type_name']) ?></td>
                            <td><?= $lv['total_days'] ?></td>
                            <td><?= statusBadge($lv['status']) ?></td>
                            <td class="text-center">
                                <?php if ($lv['status'] === 'pending' && hasRole('admin')): ?>
                                <form method="POST" class="d-inline-flex gap-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                    <button name="leave_action" value="approved" class="btn btn-xs btn-success py-0 px-2" style="font-size:.7rem;" title="Approve &amp; forward to HR">✓</button>
                                    <button name="leave_action" value="rejected" class="btn btn-xs btn-danger py-0 px-2" style="font-size:.7rem;" title="Reject">✗</button>
                                </form>
                                <?php elseif (in_array($lv['status'], ['pending','admin_approved']) && hasRole('hr')): ?>
                                <form method="POST" class="d-inline-flex gap-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                    <button name="leave_action" value="approved" class="btn btn-xs btn-success py-0 px-2" style="font-size:.7rem;" title="Final approve">✓✓</button>
                                    <button name="leave_action" value="rejected" class="btn btn-xs btn-danger py-0 px-2" style="font-size:.7rem;" title="Reject">✗</button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted" style="font-size:.7rem;">Awaiting HR</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Interviews -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-camera-video me-2 text-primary"></i>Upcoming Interviews</span>
                    <a href="<?=SITE_URL?>modules/interviews/list.php" class="btn btn-sm btn-outline-secondary py-0 px-2">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingInterviews)): ?>
                    <div class="text-center text-muted py-4 small">No upcoming interviews.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($upcomingInterviews as $iv): ?>
                        <li class="list-group-item py-2">
                            <div class="fw-semibold"><?= htmlspecialchars($iv['firstname'].' '.$iv['lastname']) ?></div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= htmlspecialchars($iv['job_title'] ?? '—') ?> &middot;
                                <?= formatDateTime($iv['interview_date']) ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Activity</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($activities as $act): ?>
                        <li class="list-group-item py-2">
                            <div class="fw-semibold text-truncate" style="max-width:200px;"><?= htmlspecialchars($act['description']) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">
                                <?= htmlspecialchars($act['username'] ?? 'System') ?> &middot; <?= formatDateTime($act['created_at']) ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($activities)): ?>
                        <li class="list-group-item text-center text-muted py-3">No recent activity.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php
    $extraJs = <<<JS
    <script>
    const payrollData = {$payrollJson};
    const deptData    = {$deptJson};
    const leaveData   = {$leaveJson};
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Payroll bar
    if(payrollData && payrollData.length > 0) {
        new Chart(document.getElementById('payrollChart'), {
            type: 'bar',
            data: {
                labels: payrollData.map(r => months[r.payroll_month-1]+' '+r.payroll_year),
                datasets: [{
                    label: 'Net Pay',
                    data: payrollData.map(r => r.total),
                    backgroundColor: '#4361ee',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { ticks: { callback: v => '₱'+Number(v/1000).toFixed(0)+'K' }, grid: { color: 'rgba(0,0,0,.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Dept Doughnut
    if(deptData && deptData.length > 0) {
        new Chart(document.getElementById('deptChart'), {
            type: 'doughnut',
            data: {
                labels: deptData.map(d => d.name),
                datasets: [{
                    data: deptData.map(d => d.count),
                    backgroundColor: ['#4361ee','#4895ef','#4cc9f0','#06d6a0','#ffbe0b','#ef233c','#7209b7'],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
            }
        });
    }

    // Leave by Type Chart
    if(leaveData && leaveData.length > 0) {
        new Chart(document.getElementById('leaveChart'), {
            type: 'pie',
            data: {
                labels: leaveData.map(l => l.name),
                datasets: [{
                    data: leaveData.map(l => l.cnt),
                    backgroundColor: ['#ffbe0b','#ff006e','#8338ec','#3a86c8','#06d6a0'],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
            }
        });
    }
    </script>
    JS;
    include 'includes/footer.php';
}
?>