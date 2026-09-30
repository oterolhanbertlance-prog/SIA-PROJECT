<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/app_url.php';
$role     = $_SESSION['role']     ?? '';
$username = $_SESSION['username'] ?? 'Guest';
$root     = appBaseUrl();
$self     = $_SERVER['PHP_SELF'] ?? '';

$nav = [
    'menu'         => strpos($self, 'customer/index')  !== false,
    'cart'         => strpos($self, 'customer/cart')   !== false,
    'receipts'     => strpos($self, 'receipt')          !== false,
    'categories'   => strpos($self, 'categories')      !== false,
    'products'     => strpos($self, 'products')        !== false,
    'ingredients'  => strpos($self, 'ingredients')     !== false,
    'fund'         => strpos($self, 'fund')             !== false,
    'dashboard'    => strpos($self, 'dashboard')       !== false,
    'transactions' => strpos($self, 'transactions')    !== false,
    'reports'      => strpos($self, 'reports')         !== false,
    'users'        => strpos($self, 'users')           !== false,
    'settings'     => strpos($self, 'settings')        !== false,
    'deliveries'   => strpos($self, 'deliveries')      !== false,
    'logistics'    => strpos($self, 'logistics')       !== false,
    'branches'     => strpos($self, 'branches')        !== false,
    'hrms'         => strpos($self, 'integration')     !== false,
    'timekeeping'  => strpos($self, 'timekeeping')     !== false,
    'orderqueue'   => strpos($self, 'order_queue')      !== false || strpos($self, 'process_order') !== false,
];

$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)$item['quantity'];
    }
}

$roleLabels = [
     'super_admin'     => 'Super Admin',
     'customer'        => 'Customer',
    'cashier'         => 'Cashier',
     'inventory_staff' => 'Inventory Staff',
];
$roleBadgeClass = [
    'super_admin'     => 'bg-danger',
    'customer'        => 'bg-info text-dark',
    'cashier'         => 'bg-primary',
    'inventory_staff' => 'bg-warning text-dark',
];
$initial = strtoupper(substr($username, 0, 1)) ?: '?';
?>

<!-- Global POS theme fallback. Some cashier/inventory pages do not include
     includes/header.php, so the sidebar must be able to initialize theme too. -->
<script>
(function () {
  if (window.BobaPosTheme && typeof window.BobaPosTheme.toggle === 'function') {
    window.BobaPosTheme.refreshButton && window.BobaPosTheme.refreshButton();
    return;
  }
  var KEY = 'boba-pos-theme';
  var html = document.documentElement;
  function readTheme() {
    try {
      var saved = localStorage.getItem(KEY);
      if (saved === 'dark' || saved === 'light') return saved;
    } catch (e) {}
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  }
  function applyTheme(theme, persist) {
    theme = theme === 'dark' ? 'dark' : 'light';
    html.setAttribute('data-pos-theme', theme);
    if (document.body) document.body.setAttribute('data-pos-theme', theme);
    if (persist) { try { localStorage.setItem(KEY, theme); } catch (e) {} }
    var btn = document.getElementById('posThemeToggle');
    if (btn) {
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }
    return theme;
  }
  window.BobaPosTheme = {
    current: function () { return html.getAttribute('data-pos-theme') || readTheme(); },
    set: function (t) { return applyTheme(t, true); },
    toggle: function () { return applyTheme((html.getAttribute('data-pos-theme') === 'dark') ? 'light' : 'dark', true); },
    refreshButton: function () { return applyTheme(html.getAttribute('data-pos-theme') || readTheme(), false); }
  };
  applyTheme(readTheme(), false);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ applyTheme(html.getAttribute('data-pos-theme') || readTheme(), false); });
  }
})();
</script>

<!-- ─── TOP-RIGHT PROFILE (website-style account menu) ───────── -->
<?php if ($role): ?>
<div class="topbar-profile-fixed">
  <button type="button" id="posThemeToggle" class="pos-theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode" onclick="window.BobaPosTheme && window.BobaPosTheme.toggle(); return false;">
    <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
    <i class="bi bi-sun-fill theme-icon-light"></i>
  </button>
  <div class="dropdown">
    <button class="topbar-profile-btn dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-expanded="false">
      <span class="tp-avatar"><?= htmlspecialchars($initial) ?></span>
      <span class="tp-info d-none d-sm-flex">
        <span class="tp-name"><?= htmlspecialchars($username) ?></span>
        <span class="tp-role"><?= htmlspecialchars($roleLabels[$role] ?? ucfirst($role)) ?></span>
      </span>
      <i class="bi bi-chevron-down tp-chevron"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end tp-menu">
      <li class="px-3 py-2 d-flex align-items-center gap-2">
        <span class="tp-avatar tp-avatar-lg"><?= htmlspecialchars($initial) ?></span>
        <div>
          <div class="fw-bold" style="font-size:.85rem"><?= htmlspecialchars($username) ?></div>
          <span class="badge <?= $roleBadgeClass[$role] ?? 'bg-secondary' ?>" style="font-size:.65rem">
            <?= htmlspecialchars($roleLabels[$role] ?? ucfirst($role)) ?>
          </span>
        </div>
      </li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <a class="dropdown-item" href="<?= $root ?>profile/settings.php">
          <i class="bi bi-person-gear me-2"></i>Profile Settings
        </a>
      </li>
      <?php if ($role === 'customer'): ?>
      <li>
        <a class="dropdown-item" href="<?= $root ?>customer/receipts.php">
          <i class="bi bi-receipt me-2"></i>My Orders
        </a>
      </li>
      <?php endif; ?>
      <?php if (in_array($role, ['super_admin','cashier','inventory_staff'], true)): ?>
      <li>
        <a class="dropdown-item" href="<?= $root ?>staff/timekeeping.php">
          <i class="bi bi-clock me-2"></i>Time In / Out
        </a>
      </li>
      <?php endif; ?>
      <li><hr class="dropdown-divider"></li>
      <li>
        <a class="dropdown-item text-danger" href="<?= $root ?>auth/logout.php">
          <i class="bi bi-box-arrow-left me-2"></i>Logout
        </a>
      </li>
    </ul>
  </div>
</div>
<?php endif; ?>

<!-- Theme state is controlled globally by includes/header.php. -->
<?php if ($role): ?>
<script>
if (window.BobaPosTheme) window.BobaPosTheme.refreshButton();
</script>
<?php endif; ?>

<nav id="sidebar" class="d-flex flex-column py-3">
  <div class="brand">&#9749; Boba House</div>
  <?php if ($role): ?><div class="sidebar-workspace"><?= htmlspecialchars($roleLabels[$role] ?? ucfirst($role)) ?> Workspace</div><?php endif; ?>
  <?php if (in_array($role, ['super_admin','cashier','inventory_staff'], true)): ?>
    <a href="<?= $root ?>staff/timekeeping.php"
       class="nav-link <?= $nav['timekeeping'] ? 'active' : '' ?>">
      <i class="bi bi-clock-fill"></i> Time In / Out
    </a>
  <?php endif; ?>

  <!-- ─── CUSTOMER ───────────────────────────────── -->
  <?php if ($role === 'customer'): ?>
    <span class="nav-section">Ordering</span>
    <a href="<?= $root ?>customer/index.php" class="nav-link <?= $nav['menu'] ? 'active' : '' ?>">
      <i class="bi bi-grid-3x3-gap-fill"></i> Menu
    </a>
    <a href="<?= $root ?>customer/cart.php" class="nav-link <?= $nav['cart'] ? 'active' : '' ?>">
      <i class="bi bi-cart3"></i> Cart
      <?php if ($cartCount > 0): ?><span class="badge bg-warning text-dark ms-auto"><?= $cartCount ?></span><?php endif; ?>
    </a>
    <a href="<?= $root ?>customer/receipts.php" class="nav-link <?= $nav['receipts'] ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i> My Orders
    </a>

  <!-- ─── CASHIER: sales only ───────────────────── -->
  <?php elseif ($role === 'cashier'): ?>
    <span class="nav-section">Cashier Workspace</span>
    <a href="<?= $root ?>staff/order_queue.php" class="nav-link <?= $nav['orderqueue'] ? 'active' : '' ?>">
      <i class="bi bi-inboxes-fill"></i> Customer Orders
      <span id="cashierQueueBadge" class="badge bg-danger ms-auto" style="display:none">0</span>
    </a>
    <a href="<?= $root ?>customer/receipts.php" class="nav-link <?= $nav['receipts'] ? 'active' : '' ?>">
      <i class="bi bi-receipt-cutoff"></i> Sales Receipts
    </a>

  <!-- ─── SUPER ADMIN: management only ──────────── -->
  <?php elseif ($role === 'super_admin'): ?>
    <span class="nav-section">Analytics</span>
    <a href="<?= $root ?>admin/dashboard.php" class="nav-link <?= $nav['dashboard'] ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= $root ?>admin/transactions.php" class="nav-link <?= $nav['transactions'] ? 'active' : '' ?>">
      <i class="bi bi-receipt-cutoff"></i> Transactions
    </a>
    <a href="<?= $root ?>admin/reports.php" class="nav-link <?= $nav['reports'] ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-line-fill"></i> Reports
    </a>

    <span class="nav-section">Delivery & Logistics</span>
    <a href="<?= $root ?>admin/deliveries.php" class="nav-link <?= $nav['deliveries'] ? 'active' : '' ?>">
      <i class="bi bi-truck"></i> Deliveries
    </a>
    <a href="<?= $root ?>admin/logistics.php" class="nav-link <?= $nav['logistics'] ? 'active' : '' ?>">
      <i class="bi bi-geo-alt-fill"></i> Branch Drivers
    </a>

    <span class="nav-section">Management</span>
    <a href="<?= $root ?>admin/branches.php" class="nav-link <?= $nav['branches'] ? 'active' : '' ?>">
      <i class="bi bi-building"></i> Branches
    </a>
    <a href="<?= $root ?>admin/fund.php" class="nav-link <?= $nav['fund'] ? 'active' : '' ?>">
      <i class="bi bi-cash-coin"></i> Cash Fund
    </a>
    <a href="<?= $root ?>admin/users.php" class="nav-link <?= $nav['users'] ? 'active' : '' ?>">
      <i class="bi bi-people-fill"></i> Users & Roles
    </a>

    <span class="nav-section">HRMS Integration</span>
    <a href="<?= $root ?>integration/pos_apply.php" class="nav-link <?= $nav['hrms'] ? 'active' : '' ?>">
      <i class="bi bi-link-45deg"></i> HR Applications
    </a>

  <!-- ─── INVENTORY STAFF: stock only ───────────── -->
  <?php elseif ($role === 'inventory_staff'): ?>
    <span class="nav-section">Inventory Workspace</span>
    <a href="<?= $root ?>admin/products.php" class="nav-link <?= $nav['products'] ? 'active' : '' ?>">
      <i class="bi bi-box-seam-fill"></i> Products
    </a>
    <a href="<?= $root ?>admin/categories.php" class="nav-link <?= $nav['categories'] ? 'active' : '' ?>">
      <i class="bi bi-tags-fill"></i> Categories
    </a>
    <a href="<?= $root ?>admin/ingredients.php" class="nav-link <?= $nav['ingredients'] ? 'active' : '' ?>">
      <i class="bi bi-basket-fill"></i> Ingredients / Stock
    </a>
  <?php endif; ?>
  <!-- Account info now lives in the top-right profile menu -->
  <div class="mt-auto"></div>
</nav>

<?php if ($role === 'cashier'): ?>
<div id="cashierGlobalOrderToast" style="display:none;position:fixed;right:24px;top:86px;z-index:1090;min-width:300px;max-width:380px;background:#2563eb;color:white;padding:14px 16px;border-radius:12px;box-shadow:0 12px 34px rgba(0,0,0,.24)">
  <div class="d-flex gap-2"><i class="bi bi-bell-fill fs-5"></i><div><strong>New customer order</strong><div class="small" id="cashierGlobalOrderToastText">An order is waiting for your branch.</div></div></div>
</div>
<script>
(function(){
 let initialized=false,last=0,timer=null;
 async function check(){try{const r=await fetch('<?= $root ?>staff/order_queue_api.php?action=count',{cache:'no-store'});if(!r.ok)return;const d=await r.json();const n=Number(d.count||0);const b=document.getElementById('cashierQueueBadge');if(b){b.textContent=n;b.style.display=n?'inline-flex':'none';}if(initialized && n>last){const t=document.getElementById('cashierGlobalOrderToast');const tx=document.getElementById('cashierGlobalOrderToastText');if(tx)tx.textContent=(n-last)+' new order(s) waiting for your branch.';if(t){t.style.display='block';clearTimeout(timer);timer=setTimeout(()=>t.style.display='none',5500);}}last=n;initialized=true;}catch(e){}}
 check();setInterval(check,8000);
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/chatbot.php'; ?>
