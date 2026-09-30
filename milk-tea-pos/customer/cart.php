<?php
require_once __DIR__ . '/../includes/branch_sync.php';
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/delivery_schema.php';
require_once '../includes/order_queue.php';
require_once '../includes/customer_cart.php';
require_once '../includes/product_customization.php';
requireAnyRole(['customer', 'cashier'], '../auth/login.php');
if (($_SESSION['role'] ?? '') === 'cashier') { require_once __DIR__.'/../includes/app_url.php'; header('Location: '.appUrl('staff/order_queue.php')); exit; }

$db = getDB();
ensureProductCustomizationSchema($db);
if (($_SESSION['role'] ?? '') === 'customer' && !empty($_SESSION['user_id'])) {
    // Restore/revalidate on every cart visit so saved prices and availability stay current.
    $_SESSION['cart'] = loadCustomerCart($db, (int)$_SESSION['user_id']);
} elseif (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['remove'])) {
    $idx = (int)$_GET['remove'];
    array_splice($_SESSION['cart'], $idx, 1);
    if (($_SESSION['role'] ?? '') === 'customer') saveCustomerCart($db, (int)$_SESSION['user_id'], $_SESSION['cart']);
    header('Location: ' . appUrl('customer/cart.php')); exit;
}

if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    if (($_SESSION['role'] ?? '') === 'customer') clearSavedCustomerCart($db, (int)$_SESSION['user_id']);
    header('Location: ' . appUrl('customer/cart.php')); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    foreach ($_POST['qty'] as $i => $q) {
        $q = (int)$q;
        if ($q > 0 && isset($_SESSION['cart'][$i])) $_SESSION['cart'][$i]['quantity'] = $q;
    }
    if (($_SESSION['role'] ?? '') === 'customer') saveCustomerCart($db, (int)$_SESSION['user_id'], $_SESSION['cart']);
    header('Location: ' . appUrl('customer/cart.php')); exit;
}

$branchSyncState = syncAllHrmsBranchesToPos($db);
ensureDeliveryColumns($db);
ensureOrderQueueSchema($db);
$deliveryBranches=$db->query("SELECT id,name,city,delivery_fee,delivery_eta_minutes FROM branches WHERE status='active' ORDER BY name")->fetchAll();

// Cashier counter sales always belong to the cashier's assigned branch.
$cashierBranchId = null;
if (($_SESSION['role'] ?? '') === 'cashier') {
    try {
        $u = $db->prepare("SELECT branch_id FROM users WHERE id=? LIMIT 1");
        $u->execute([$_SESSION['user_id']]);
        $cashierBranchId = (int)($u->fetchColumn() ?: 0) ?: null;
    } catch (Throwable $e) { $cashierBranchId = null; }
}

// Current change-fund balance (how much small cash is on hand to give as change)
$fundBalance = (float)($db->query('SELECT balance FROM cash_fund WHERE id=1')->fetchColumn() ?: 0);

$checkoutError = $checkoutSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $paymentMethod  = ($_POST['payment_method'] ?? 'cash') === 'gcash' ? 'gcash' : 'cash';
    $orderType      = ($_POST['order_type'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup';
    $deliveryAddr   = trim($_POST['delivery_address'] ?? '');
    $branchId       = (int)($_POST['branch_id'] ?? 0) ?: null;
    if (($_SESSION['role'] ?? '') === 'cashier' && $orderType === 'pickup' && $cashierBranchId) { $branchId = $cashierBranchId; }
    $branchFee      = 0.0;
    $amountPaid     = (float)str_replace(',', '', $_POST['amount_paid'] ?? 0);

    if (empty($_SESSION['cart'])) {
        $checkoutError = ($_SESSION['role'] === 'cashier' ? 'There are no items in the current sale.' : 'Your cart is empty.');
    } elseif (($_SESSION['role'] ?? '') === 'cashier' && !$cashierBranchId) {
        $checkoutError = 'This cashier account is not assigned to a branch yet. Ask the Super Admin to assign a branch before processing sales.';
    } elseif (!$branchId) {
        $checkoutError = 'Please select the Boba House branch that will prepare your order.';
    } elseif ($orderType === 'delivery' && $deliveryAddr === '') {
        $checkoutError = 'Please enter the delivery location.';
    } else {
        if($orderType==='delivery'){ $bq=$db->prepare("SELECT delivery_fee FROM branches WHERE id=? AND status='active'");$bq->execute([$branchId]);$branchFee=(float)($bq->fetchColumn()?:0); }
        $total   = $branchFee;
        $lines   = [];
        $stockOk = true;
        foreach ($_SESSION['cart'] as $item) {
            $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();
            if (!$product || $product['stock'] < $item['quantity']) {
                $checkoutError = 'Insufficient stock for: ' . htmlspecialchars($item['name']);
                $stockOk = false; break;
            }
            $resolved = resolveCustomization($product, $item);
            $unitPrice = (float)$resolved['unit_price'];
            $subtotal = $unitPrice * $item['quantity'];
            $total += $subtotal;
            $lines[] = array_merge($item, $resolved, ['unit_price'=>$unitPrice,'subtotal'=>$subtotal]);
        }

        if ($stockOk) {
            // Customer submits an order request; cashier confirms payment later.
            $amountPaid = 0.0;
            $change = 0.0;
        }

        if ($stockOk) {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO transactions (user_id,total_amount,payment_method,amount_paid,change_amount,order_type,branch_id,delivery_address,delivery_fee,delivery_status,status) VALUES (?,?,?,?,?,?,?,?,?,'pending','pending')")
                   ->execute([
                       $_SESSION['user_id'], $total, $paymentMethod, $amountPaid, $change,
                       $orderType, $branchId, $orderType === 'delivery' ? $deliveryAddr : null, $orderType==='delivery'?$branchFee:0,
                   ]);
                $txId = $db->lastInsertId();
                foreach ($lines as $line) {
                    $db->prepare('INSERT INTO transaction_details (transaction_id,product_id,quantity,unit_price,subtotal,sugar_level,ice_level,selected_size,toppings,customization_price) VALUES (?,?,?,?,?,?,?,?,?,?)')
                       ->execute([$txId,$line['product_id'],$line['quantity'],$line['unit_price'],$line['subtotal'],$line['sugar_level'],$line['ice_level'],$line['selected_size']?:null,json_encode(normalizeToppingNames($line['toppings']??[]),JSON_UNESCAPED_UNICODE),$line['customization_price']]);
                    $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')
                       ->execute([$line['quantity'], $line['product_id']]);
                }
                // Cash sales pay change out of the fund; GCash doesn't touch the physical drawer.
                if ($paymentMethod === 'cash' && $change > 0) {
                    $db->prepare('UPDATE cash_fund SET balance = balance - ? WHERE id=1')->execute([$change]);
                }
                $db->commit();
                $_SESSION['cart'] = [];
                if (($_SESSION['role'] ?? '') === 'customer') clearSavedCustomerCart($db, (int)$_SESSION['user_id']);
                $_SESSION['flash_success'] = 'Order #' . $txId . ' sent to the cashier at your selected branch. <a href="receipt.php?id=' . $txId . '">View order</a>.';
                header('Location: ' . appUrl('customer/cart.php')); exit;
            } catch (Exception $e) {
                $db->rollBack();
                $checkoutError = 'Checkout failed: ' . $e->getMessage();
            }
        }
    }
}

$total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $_SESSION['cart']));
$root  = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo $_SESSION['role'] === 'cashier' ? 'Checkout' : 'Cart'; ?> — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title"><i class="bi <?php echo $_SESSION['role'] === 'cashier' ? 'bi-cash-register' : 'bi-cart3'; ?> me-2" style="color:var(--boba-amber)"></i><?php echo $_SESSION['role'] === 'cashier' ? 'Checkout / Current Sale' : 'Cart'; ?></h1>
      <a href="index.php" class="btn btn-boba-outline"><i class="bi bi-arrow-left me-1"></i><?php echo $_SESSION['role'] === 'cashier' ? 'Back to Counter Order' : 'Back to Menu'; ?></a>
    </div>
    <div class="content-area">
      <?php if (($_SESSION['role'] ?? '') === 'cashier'): ?><div class="workspace-banner mb-4"><div><strong>Cashier Checkout</strong><span>Review the current sale, take payment, and complete the transaction.</span></div><i class="bi bi-credit-card-2-front"></i></div><?php endif; ?>

      <?php if ($checkoutError): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?php echo htmlspecialchars($checkoutError); ?></div>
      <?php endif; ?>
      <?php if ($checkoutSuccess): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($checkoutSuccess); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['flash_success']; ?></div>
        <?php unset($_SESSION['flash_success']); ?>
      <?php endif; ?>

      <?php if (empty($_SESSION['cart'])): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-cart-x" style="font-size:3rem"></i>
          <p class="mt-3"><?php echo $_SESSION['role'] === 'cashier' ? 'No items in the current sale.' : 'Your cart is empty.'; ?> <a href="index.php"><?php echo $_SESSION['role'] === 'cashier' ? 'Start a counter order' : 'Browse the menu'; ?></a>.</p>
        </div>
      <?php else: ?>
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><?php echo $_SESSION['role'] === 'cashier' ? 'Current Sale' : 'Cart Items'; ?> (<?php echo count($_SESSION['cart']); ?>)</span>
              <a href="?clear=1" onclick="return confirm('Clear all items from this order?')" class="btn btn-sm btn-outline-danger">Clear All</a>
            </div>
            <div class="card-body">
              <form method="POST" id="qtyForm">
                <?php foreach ($_SESSION['cart'] as $i => $item): ?>
                  <div class="cart-item">
                    <div class="flex-grow-1">
                      <div class="fw-bold" style="font-size:.9rem"><?php echo htmlspecialchars($item['name']); ?></div>
                      <?php $customParts=customizationSummary($item); if($customParts): ?><div style="font-size:.76rem;color:var(--boba-muted)"><?php echo htmlspecialchars(implode(' • ',$customParts)); ?></div><?php endif; ?>
                      <div style="color:var(--boba-amber);font-weight:800;margin-top:.2rem">
                        ₱<?php echo number_format($item['price'],2); ?> each
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <input type="number" name="qty[<?php echo $i; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="form-control form-control-sm text-center" style="width:65px">
                      <span class="fw-bold" style="min-width:70px;text-align:right">₱<?php echo number_format($item['price']*$item['quantity'],2); ?></span>
                      <a href="?remove=<?php echo $i; ?>" class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></a>
                    </div>
                  </div>
                <?php endforeach; ?>
                <button type="submit" name="update_qty" class="btn btn-boba-outline btn-sm mt-2">
                  <i class="bi bi-arrow-repeat me-1"></i>Update Quantities
                </button>
              </form>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header"><i class="bi bi-truck me-2" style="color:var(--boba-amber)"></i>Delivery / Pickup</div>
            <div class="card-body">
              <div class="btn-group w-100 mb-3" role="group">
                <input type="radio" class="btn-check" name="order_type_display" id="ot_pickup" checked onclick="toggleDelivery(false)">
                <label class="btn btn-boba-outline" for="ot_pickup"><i class="bi bi-shop me-1"></i>Pickup at Counter</label>
                <input type="radio" class="btn-check" name="order_type_display" id="ot_delivery" onclick="toggleDelivery(true)">
                <label class="btn btn-boba-outline" for="ot_delivery"><i class="bi bi-truck me-1"></i>Deliver It</label>
              </div>
              <div id="deliveryFields">
                <label class="form-label fw-bold">Order Branch</label><select id="deliveryBranchDisplay" class="form-select mb-3"><option value="">Select branch…</option><?php foreach($deliveryBranches as $b):?><option value="<?=$b['id']?>" data-fee="<?=$b['delivery_fee']?>"><?=htmlspecialchars($b['name'].($b['city']?' — '.$b['city']:''))?> · ₱<?=number_format($b['delivery_fee'],2)?> delivery</option><?php endforeach;?></select><div class="form-text mb-3">Your order will appear automatically on the cashier screen of this branch.</div><div id="deliveryAddressWrap" style="display:none"><label class="form-label fw-bold">Delivery Location</label>
                <input type="text" id="deliveryAddressDisplay" class="form-control" placeholder="e.g. Blk 2 Lot 5, Sampaguita St., Brgy. San Isidro"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="cart-summary">
            <h5 class="fw-bold mb-3" style="color:var(--boba-peach)">Order Summary</h5>
            <?php foreach ($_SESSION['cart'] as $item): ?>
              <div class="d-flex justify-content-between mb-1" style="font-size:.85rem;color:rgba(255,255,255,.8)">
                <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></span>
                <span>₱<?php echo number_format($item['price']*$item['quantity'],2); ?></span>
              </div>
            <?php endforeach; ?>
            <hr style="border-color:rgba(255,255,255,.2);margin:.75rem 0">
            <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
              <span>Total</span><span style="color:var(--boba-peach)">₱<?php echo number_format($total,2); ?></span>
            </div>

            <form method="POST" id="checkoutForm">
              <input type="hidden" name="order_type" id="orderTypeInput" value="pickup">
              <input type="hidden" name="delivery_address" id="deliveryAddressInput" value=""><input type="hidden" name="branch_id" id="branchIdInput" value="">

              <label class="form-label fw-bold mb-2" style="color:rgba(255,255,255,.8);font-size:.85rem">Preferred Payment Method</label>
              <div class="btn-group w-100 mb-3" role="group">
                <input type="radio" class="btn-check" name="payment_method" id="pm_cash" value="cash" checked onclick="togglePayment('cash')">
                <label class="btn btn-boba-outline" for="pm_cash"><i class="bi bi-cash-stack me-1"></i>Cash</label>
                <input type="radio" class="btn-check" name="payment_method" id="pm_gcash" value="gcash" onclick="togglePayment('gcash')">
                <label class="btn btn-boba-outline" for="pm_gcash"><i class="bi bi-phone me-1"></i>GCash</label>
              </div>

              <div id="cashFields" class="mb-3"><div class="alert alert-light border small mb-0"><i class="bi bi-cash-stack me-1"></i>Pay the cashier when your order is ready. The cashier will enter the amount received.</div></div>

              <div id="gcashFields" style="display:none" class="mb-3">
                <div class="qr-box text-center">
                  <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-3" style="width:72px;height:72px;background:rgba(255,255,255,.12);font-size:2rem"><i class="bi bi-phone"></i></div>
                  <div class="mt-2" style="font-size:.8rem;color:rgba(255,255,255,.75)">
                    You can pay by GCash. The cashier will verify the payment before completing your order.
                  </div>
                </div>
              </div>

              <button type="submit" name="checkout" class="btn btn-boba w-100 py-2 fs-6" id="checkoutBtn">
                <i class="bi bi-send-check-fill me-1"></i>Send Order to Cashier
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const baseTotal   = <?php echo $total; ?>;
let total = baseTotal;
const fundBalance = <?php echo $fundBalance; ?>;

function setAmount(v) {
  const el=document.getElementById('amountPaid'); if(el){el.value=v;calcChange();}
}

function calcChange() {
  const a=document.getElementById('amountPaid'); if(!a)return; const paid = parseFloat(a.value) || 0;
  const ch   = paid - total;
  const el   = document.getElementById('changeDisplay');
  if (ch < 0) {
    el.textContent = 'Insufficient';
    el.style.color = '#ff8080';
  } else if (ch > fundBalance) {
    el.textContent = '₱' + ch.toFixed(2) + ' — not enough change in drawer!';
    el.style.color = '#ff8080';
  } else {
    el.textContent = '₱' + ch.toFixed(2);
    el.style.color = 'var(--boba-peach)';
  }
}

function togglePayment(method) {
  const cash  = document.getElementById('cashFields');
  const gcash = document.getElementById('gcashFields');
  const amt   = document.getElementById('amountPaid');
  if (method === 'gcash') {
    cash.style.display  = 'none';
    gcash.style.display = 'block';
    if(amt) amt.required = false;
  } else {
    cash.style.display  = 'block';
    gcash.style.display = 'none';
    if(amt) amt.required = false;
  }
}

function refreshDeliveryTotal(){const sel=document.getElementById('deliveryBranchDisplay');const fee=sel&&sel.value?parseFloat(sel.selectedOptions[0].dataset.fee||0):0;total=baseTotal+(document.getElementById('ot_delivery').checked?fee:0);document.querySelector('.cart-summary .fs-5 span:last-child').textContent='₱'+total.toFixed(2);const ap=document.getElementById('amountPaid');if(ap)ap.min=total.toFixed(2);calcChange();}
function toggleDelivery(isDelivery) {
  document.getElementById('deliveryFields').style.display = 'block';
  const aw=document.getElementById('deliveryAddressWrap'); if(aw) aw.style.display=isDelivery?'block':'none';
  document.getElementById('orderTypeInput').value = isDelivery ? 'delivery' : 'pickup';
  refreshDeliveryTotal();
}
document.getElementById('deliveryBranchDisplay')?.addEventListener('change',refreshDeliveryTotal);

document.getElementById('checkoutForm').addEventListener('submit', function (e) {
  const isDelivery = document.getElementById('ot_delivery').checked;
  const addr = document.getElementById('deliveryAddressDisplay').value.trim();
  const branch = document.getElementById('deliveryBranchDisplay').value;
  if (isDelivery && (!addr || !branch)) {
    e.preventDefault();
    alert('Please select a fulfillment branch and enter a delivery location.');
    return;
  }
  if (!branch) { e.preventDefault(); alert('Please select the branch that will prepare your order.'); return; }
  document.getElementById('deliveryAddressInput').value = isDelivery ? addr : '';
  document.getElementById('branchIdInput').value = branch;
});

<?php if (!empty($_SESSION['cart'])): ?>
calcChange();
<?php endif; ?>
</script>
</body></html>
