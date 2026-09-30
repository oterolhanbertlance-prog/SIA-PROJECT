<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$hasUsers = finance_user_count() > 0;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Offline finance control center for local HRMS and POS accounting.">
    <title>Finance · Offline control center</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=20260819">
    <link rel="stylesheet" href="assets/icons/bootstrap-icons.css?v=20260819">
    <link rel="stylesheet" href="assets/css/finance.css?v=20260819">
    <script>
        (function () {
            try {
                if (localStorage.getItem('finance-theme') === 'dark') document.documentElement.dataset.theme = 'dark';
            } catch (error) {}
        }());
    </script>
</head>
<body class="landing-page">
    <nav class="landing-nav">
        <a class="landing-brand" href="index.php">
            <span class="brand-mark">₱</span>
            <span><strong>Finance</strong><small>LOCAL CONTROL CENTER</small></span>
        </a>
        <div class="landing-actions">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><span class="bi bi-moon-stars"></span></button>
            <a class="btn btn-outline-primary" href="<?= $hasUsers ? 'login.php' : 'install.php' ?>">
                <?= $hasUsers ? 'Sign in' : 'Set up Finance' ?>
            </a>
        </div>
    </nav>

    <main>
        <section class="landing-hero">
            <div class="landing-hero-copy">
                <div class="eyebrow">PRIVATE · LOCAL · ACCOUNTABLE</div>
                <h1>Your finance team’s <span>control center.</span></h1>
                <p>Connect your HRMS and Milk Tea POS, keep the books balanced, and see the numbers that matter — entirely on your own Windows computer.</p>
                <div class="landing-cta">
                    <a class="btn btn-primary btn-lg" href="<?= $hasUsers ? 'login.php' : 'install.php' ?>">
                        <?= $hasUsers ? 'Open Finance' : 'Create administrator' ?> <span class="bi bi-arrow-right"></span>
                    </a>
                    <a class="landing-text-link" href="#features">Explore capabilities <span class="bi bi-arrow-down"></span></a>
                </div>
                <div class="landing-proof"><span class="bi bi-shield-check"></span> No cloud services · No tracking · No external APIs</div>
            </div>
            <div class="landing-visual" aria-label="Finance dashboard preview">
                <div class="landing-window">
                    <div class="landing-window-bar"><span></span><span></span><span></span><small>Finance / Dashboard</small></div>
                    <div class="landing-window-body">
                        <div class="landing-mini-sidebar"><b>₱</b><i></i><i></i><i></i><i></i></div>
                        <div class="landing-mini-main">
                            <div class="landing-mini-heading"><div><small>FINANCE CONTROL CENTER</small><strong>Good morning, team.</strong></div><em>LOCALHOST</em></div>
                            <div class="landing-mini-metrics"><div><small>NET REVENUE</small><strong>₱482,640</strong><i></i></div><div><small>PAYROLL</small><strong>₱126,900</strong><i></i></div></div>
                            <div class="landing-mini-chart"><small>SALES TREND</small><div class="landing-bars"><b></b><b></b><b></b><b></b><b></b><b></b><b></b></div></div>
                        </div>
                    </div>
                </div>
                <div class="landing-float-card"><span class="bi bi-check2-circle"></span><div><strong>Books balanced</strong><small>Double-entry controls active</small></div></div>
            </div>
        </section>

        <section class="landing-section" id="features">
            <div class="section-intro"><div class="eyebrow">ONE LOCAL SOURCE OF TRUTH</div><h2>Built for the way your business actually works.</h2><p>Finance brings your operational data together without taking ownership away from your HRMS or POS.</p></div>
            <div class="feature-grid">
                <article class="feature-card"><div class="feature-icon blue"><span class="bi bi-arrow-repeat"></span></div><h3>Sync without duplication</h3><p>Import completed sales and payroll with protected source IDs, so repeated syncs stay safe.</p></article>
                <article class="feature-card"><div class="feature-icon green"><span class="bi bi-journal-check"></span></div><h3>Balanced by design</h3><p>Every imported or manual journal is checked for equal debit and credit totals before posting.</p></article>
                <article class="feature-card"><div class="feature-icon purple"><span class="bi bi-people"></span></div><h3>Clear access control</h3><p>Create administrators, finance staff, accountants, auditors, and viewers with the right scope.</p></article>
                <article class="feature-card"><div class="feature-icon amber"><span class="bi bi-lock"></span></div><h3>Offline and private</h3><p>Runs on XAMPP with local PHP, MySQL, Bootstrap, JavaScript, and short-lived sessions.</p></article>
            </div>
        </section>

        <section class="landing-section landing-section-muted">
            <div class="landing-split">
                <div><div class="eyebrow">READY WHEN YOU ARE</div><h2>Start with a safer financial foundation.</h2><p>Install the schema, create your first administrator, connect your source databases, and take control of the books from one calm workspace.</p></div>
                <div class="landing-checks"><div><span class="bi bi-check-circle-fill"></span> HRMS payroll register</div><div><span class="bi bi-check-circle-fill"></span> POS sales register</div><div><span class="bi bi-check-circle-fill"></span> Reports and chart of accounts</div><div><span class="bi bi-check-circle-fill"></span> Audit log and session protection</div></div>
            </div>
        </section>
    </main>
    <footer class="landing-footer"><span>Finance · self-contained local accounting</span><span>PHP / MySQL / XAMPP</span></footer>
    <script src="assets/js/finance.js?v=20260819"></script>
</body>
</html>