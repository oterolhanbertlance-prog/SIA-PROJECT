<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/app_url.php';

// Logged-in users skip the landing page and go straight into the app.
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'customer';
    $dest = match ($role) {
        'super_admin' => appUrl('admin/dashboard.php'),
        'inventory_staff' => appUrl('admin/products.php'),
        'cashier' => appUrl('staff/order_queue.php'),
        'customer' => appUrl('customer/index.php'),
        default => appUrl('auth/login.php'),
    };
    header('Location: ' . $dest); exit;
}
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// Pull a handful of real products to advertise on the landing page.
$featured = [];
try {
    $db = getDB();
    $featured = $db->query(
        "SELECT name, price, image_url, category_id FROM products
         WHERE image_url IS NOT NULL ORDER BY RAND() LIMIT 4"
    )->fetchAll();
    if (count($featured) < 4) {
        $featured = $db->query("SELECT name, price, image_url, category_id FROM products ORDER BY RAND() LIMIT 4")->fetchAll();
    }
} catch (Exception $e) {
    $featured = [];
}
$promoTags = ['Best Seller', 'New', 'Chef\'s Pick', 'Limited'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Boba House — Fresh Milk Tea, Delivered</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/custom.css">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body>

<!-- ── NAV ─────────────────────────────────────────────── -->
<div class="landing-nav">
  <div class="brand">&#9749; Boba House</div>
  <div class="nav-actions">
    <a href="<?= htmlspecialchars($base) ?>/auth/login.php" class="text-white">Login</a>
    <a href="<?= htmlspecialchars($base) ?>/auth/register.php" class="btn btn-hero btn-sm">Register</a>
  </div>
</div>

<!-- ── HERO / AD BANNER ────────────────────────────────── -->
<div class="landing-hero">
  <span class="badge-promo"><i class="bi bi-stars me-1"></i>Buy 1 Get 1 — This Week Only</span>
  <h1>Real milk. Real tea. Real fast delivery.</h1>
  <p class="lead">Order your favorite milk tea, fruit tea, or smoothie in a couple of taps — pick it up at the counter or have a rider bring it straight to your door.</p>
  <div class="d-flex gap-2 justify-content-center flex-wrap">
    <a href="<?= htmlspecialchars($base) ?>/auth/register.php" class="btn-hero">
      <i class="bi bi-cup-straw me-1"></i>Order Now
    </a>
    <a href="<?= htmlspecialchars($base) ?>/auth/login.php" class="btn-hero-outline">
       Staff Login
    </a>
  </div>
</div>

<!-- ── FEATURED PROMOS (advertisement cards) ───────────── -->
<div class="landing-section">
  <h2>Today's Featured Drinks</h2>
  <p class="section-sub">Hand-picked favorites from our menu — fresh brewed daily.</p>

  <?php if (!empty($featured)): ?>
  <div class="promo-grid">
    <?php foreach ($featured as $i => $p): ?>
      <div class="promo-card">
        <div class="promo-img">
          <span class="promo-tag"><?= htmlspecialchars($promoTags[$i % count($promoTags)]) ?></span>
          <?php if (!empty($p['image_url'])): ?>
            <img src="<?= htmlspecialchars($base) ?>/assets/uploads/<?= htmlspecialchars($p['image_url']) ?>"
                 alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            &#9749;
          <?php endif; ?>
        </div>
        <div class="promo-body">
          <div class="promo-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="promo-price">&#8369;<?= number_format($p['price'], 2) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <p class="text-center text-muted">Menu coming soon — check back shortly!</p>
  <?php endif; ?>

  <div class="text-center mt-4">
    <a href="<?= htmlspecialchars($base) ?>/auth/register.php" class="btn btn-boba">
      See Full Menu &amp; Order <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>
</div>

<!-- ── FEATURES ─────────────────────────────────────────── -->
<div class="landing-section pt-0">
  <div class="feature-strip">
    <div class="feature">
      <i class="bi bi-lightning-charge-fill"></i>
      <h6>Fast Prep</h6>
      <p>Most orders are ready to pick up in under 10 minutes.</p>
    </div>
    <div class="feature">
      <i class="bi bi-truck"></i>
      <h6>Live Delivery Tracking</h6>
      <p>Riders update your order status until it's in your hands.</p>
    </div>
    <div class="feature">
      <i class="bi bi-sliders"></i>
      <h6>Customize Every Cup</h6>
      <p>Pick your sugar level and ice level exactly how you like it.</p>
    </div>
    <div class="feature">
      <i class="bi bi-shield-check"></i>
      <h6>Secure Checkout</h6>
      <p>Pay with cash on pickup/delivery or GCash — your choice.</p>
    </div>
  </div>
</div>

<!-- ── CTA ──────────────────────────────────────────────── -->
<div class="landing-cta">
  <h2>Craving a cup right now?</h2>
  <p>Create a free account and place your first order in under a minute.</p>
  <a href="<?= htmlspecialchars($base) ?>/auth/register.php" class="btn-hero">
    <i class="bi bi-person-plus me-1"></i>Get Started
  </a>
</div>

<!-- ── FOOTER ───────────────────────────────────────────── -->
<div class="landing-footer">
  &copy; <?= date('Y') ?> Boba House — Milk Tea POS System &middot;
  <a href="<?= htmlspecialchars($base) ?>/auth/login.php">Staff Login</a> &middot;
  <a href="<?= htmlspecialchars($base) ?>/driver/login.php">Driver Login</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
