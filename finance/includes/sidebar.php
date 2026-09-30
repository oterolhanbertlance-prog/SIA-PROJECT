<aside class="sidebar">
    <div class="brand-lockup">
        <div class="brand-mark">₱</div>
        <div>
            <div class="brand-name">Finance</div>
            <div class="brand-caption">LOCAL CONTROL CENTER</div>
        </div>
    </div>
    <nav class="nav-stack">
        <div class="nav-label">Overview</div>
        <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><span class="bi bi-grid"></span> Dashboard</a>
        <a class="nav-link <?= $activePage === 'integration' ? 'active' : '' ?>" href="integration.php"><span class="bi bi-arrow-repeat"></span> Integration</a>
        <a class="nav-link <?= $activePage === 'branches' ? 'active' : '' ?>" href="branches.php"><span class="bi bi-diagram-3"></span> Branch Mappings</a>
        <div class="nav-label">Finance</div>
        <a class="nav-link <?= $activePage === 'sales' ? 'active' : '' ?>" href="sales.php"><span class="bi bi-receipt"></span> Sales</a>
        <a class="nav-link <?= $activePage === 'payroll' ? 'active' : '' ?>" href="payroll.php"><span class="bi bi-people"></span> Payroll</a>
        <?php if (has_permission('attendance.manage')): ?><a class="nav-link <?= $activePage === 'attendance' ? 'active' : '' ?>" href="attendance.php"><span class="bi bi-calendar2-check"></span> Attendance</a><?php endif; ?>
        <a class="nav-link <?= $activePage === 'journal' ? 'active' : '' ?>" href="journal.php"><span class="bi bi-journal"></span> Journals</a>
        <a class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>" href="reports.php"><span class="bi bi-bar-chart"></span> Reports</a>
        <?php if (has_permission('mailer.manage') || has_permission('gps.manage')): ?>
            <div class="nav-label">Operations</div>
            <?php if (has_permission('mailer.manage')): ?><a class="nav-link <?= $activePage === 'mailer' ? 'active' : '' ?>" href="mailer.php"><span class="bi bi-envelope-paper"></span> Mailer</a><?php endif; ?>
            <?php if (has_permission('gps.manage')): ?><a class="nav-link <?= $activePage === 'gps' ? 'active' : '' ?>" href="gps.php"><span class="bi bi-geo-alt"></span> GPS &amp; Branches</a><?php endif; ?>
        <?php endif; ?>
        <div class="nav-label">Control</div>
        <a class="nav-link <?= $activePage === 'accounts' ? 'active' : '' ?>" href="accounts.php"><span class="bi bi-list-columns"></span> Chart of Accounts</a>
        <?php if (has_permission('settings.manage')): ?>
            <a class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>" href="users.php"><span class="bi bi-person-gear"></span> Admin &amp; Employees</a>
        <?php endif; ?>
        <a class="nav-link <?= $activePage === 'settings' ? 'active' : '' ?>" href="settings.php"><span class="bi bi-gear"></span> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div class="system-link"><span class="bi bi-shield-check"></span><span><strong>Protected session</strong><small>No tracking or cloud services</small></span></div>
        <a class="logout-link" href="logout.php"><span class="bi bi-box-arrow-right"></span> Sign out</a>
    </div>
</aside>
<?php require_once __DIR__ . '/chatbot.php'; ?>
