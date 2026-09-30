<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/customer_cart.php';
require_once '../includes/product_customization.php';
require_once '../includes/category_filter.php';
requireAnyRole(['customer','cashier'], '../auth/login.php');
if (($_SESSION['role'] ?? '') === 'cashier') { require_once __DIR__.'/../includes/app_url.php'; header('Location: '.appUrl('staff/order_queue.php')); exit; }

$db = getDB();
ensureProductCustomizationSchema($db);
if (($_SESSION['role'] ?? '') === 'customer' && !empty($_SESSION['user_id'])) {
    $_SESSION['cart'] = loadCustomerCart($db, (int)$_SESSION['user_id']);
}

$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$catFilter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$categoryKey = posResolveCategoryKey($categories, $catFilter, $_GET['category'] ?? null);
$sql = 'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1';
$params = [];
// Search is intentionally global. A selected category must never hide a matching product.
if ($search !== '') {
    $sql .= " AND (LOWER(p.name) LIKE LOWER(?) OR LOWER(COALESCE(c.name,'')) LIKE LOWER(?))";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= ' ORDER BY p.name';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
if ($search === '' && $categoryKey !== '') $products = posFilterProductsByCategory($products, $categoryKey);

// Products that need an ingredient which is currently unavailable are
// shown as unavailable to customers, same as being out of stock.
$unavailableIds = $db->query(
    "SELECT DISTINCT pi.product_id FROM product_ingredients pi
     JOIN ingredients i ON i.id = pi.ingredient_id WHERE i.is_available = 0"
)->fetchAll(PDO::FETCH_COLUMN);

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo $_SESSION['role'] === 'cashier' ? 'Counter Order' : 'Menu'; ?> — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title">
        <i class="bi bi-grid-3x3-gap-fill me-2" style="color:var(--boba-amber)"></i><?php echo $_SESSION['role'] === 'cashier' ? 'Counter Order' : 'Menu'; ?>
      </h1>
      <a href="cart.php" class="btn btn-boba position-relative">
        <i class="bi bi-cash-register me-1"></i> <?php echo $_SESSION['role'] === 'cashier' ? 'Checkout' : 'Cart'; ?>
        <?php $cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); ?>
        <?php if ($cartCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark">
            <?php echo $cartCount; ?>
          </span>
        <?php endif; ?>
      </a>
    </div>
    <div class="content-area">
      <?php if (($_SESSION['role'] ?? '') === 'cashier'): ?><div class="workspace-banner mb-4"><div><strong>Cashier Workspace</strong><span>Build a counter order and send it to checkout.</span></div><i class="bi bi-cash-register"></i></div><?php endif; ?>

      <form method="GET" class="mb-3" id="productSearchForm">
        <div class="input-group" style="max-width:380px">
          <span class="input-group-text"
                style="background:var(--boba-cream);border-color:var(--boba-border)">
            <i class="bi bi-search"></i>
          </span>
          <input type="search" name="search" id="productSearchInput" class="form-control" autocomplete="off"
                 placeholder="Search drinks…"
                 value="<?php echo htmlspecialchars($search); ?>">
          <button class="btn btn-boba" type="submit">Go</button>
        </div>
      </form>

      <div class="category-tabs">
        <a href="index.php" class="category-tab <?php if ($catFilter===0 || $search!=='') echo 'active'; ?>">All</a>
        <?php foreach ($categories as $cat): ?>
          <a href="?cat=<?php echo (int)$cat['id']; ?>"
             class="category-tab <?php if ($search==='' && $categoryKey===posCategoryKey($cat['name'] ?? '')) echo 'active'; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-cup-straw" style="font-size:3rem"></i>
          <p class="mt-3">No products found.</p>
        </div>
      <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $p):
          $missingIngredient = in_array($p['id'], $unavailableIds);
          $orderable = !$missingIngredient;
          $cfg = customizationConfig($p);
          $cfgJson = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
          <div class="product-card <?php if (!$orderable) echo 'out-of-stock'; ?><?php if ($orderable) echo ' js-product-card'; ?>"
               <?php if ($orderable): ?> role="button" tabindex="0"
               data-product-id="<?php echo (int)$p['id']; ?>"
               data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>"
               data-product-price="<?php echo htmlspecialchars((string)$p['price'], ENT_QUOTES, 'UTF-8'); ?>"
               data-product-config="<?php echo htmlspecialchars($cfgJson ?: '{}', ENT_QUOTES, 'UTF-8'); ?>"
               <?php endif; ?>>
            <div class="product-img">
              <?php if ($p['image_url']): ?>
                <img src="../assets/uploads/<?php echo htmlspecialchars($p['image_url']); ?>"
                     alt="" style="width:100%;height:130px;object-fit:cover">
              <?php else: ?>
                <span style="font-size:3rem">&#9749;</span>
              <?php endif; ?>
            </div>
            <div class="product-info">
              <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
              <div class="product-price">&#8369;<?php echo number_format($p['price'],2); ?></div>
              <div class="product-stock">
                <?php if ($missingIngredient): ?>
                  <span style="color:var(--boba-danger)"><i class="bi bi-exclamation-triangle-fill me-1"></i>Unavailable</span>
                <?php else: ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Available</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add to Cart Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content"
         style="border-radius:.85rem;border:1px solid var(--boba-border)">
      <div class="modal-header"
           style="border-bottom:1px solid var(--boba-border)">
        <h5 class="modal-title fw-bold" id="modalProductName"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="cart_add.php">
        <input type="hidden" name="product_id" id="modalProductId">
        <div class="modal-body">
          <div class="row g-3">

            <div id="sizeOptionsWrap" class="col-12" style="display:none">
              <label class="form-label fw-bold">Size</label><select name="selected_size" id="sizeSelect" class="form-select"></select>
            </div>
            <div id="sugarWrap" class="col-6" style="display:none"><label class="form-label fw-bold">Sugar Level</label><select name="sugar_level" id="sugarSelect" class="form-select"><option>0%</option><option>25%</option><option selected>50%</option><option>75%</option><option>100%</option></select></div>
            <div id="iceWrap" class="col-6" style="display:none"><label class="form-label fw-bold">Ice Level</label><select name="ice_level" id="iceSelect" class="form-select"><option>No Ice</option><option>Less Ice</option><option selected>Regular Ice</option><option>Extra Ice</option></select></div>
            <div id="toppingsWrap" class="col-12" style="display:none"><label class="form-label fw-bold">Toppings</label><div id="toppingsList" class="border rounded p-2"></div><div class="form-text">Choose any available toppings.</div></div>
            <input type="hidden" name="sugar_level_fallback" value="N/A"><input type="hidden" name="ice_level_fallback" value="N/A">
            <div class="col-12">
              <label class="form-label fw-bold">Quantity</label>
              <div class="input-group" style="max-width:160px">
                <button type="button" class="btn btn-outline-secondary"
                        onclick="changeQty(-1)">&#8722;</button>
                <input type="number" name="quantity" id="qtyInput"
                       class="form-control text-center" value="1" min="1">
                <button type="button" class="btn btn-outline-secondary"
                        onclick="changeQty(1)">+</button>
              </div>
            </div>
            <div class="col-12">
              <span class="text-muted" style="font-size:.85rem">
                Unit price: <strong id="modalPrice"></strong>
              </span>
            </div>
          </div>
        </div>
        <div class="modal-footer"
             style="border-top:1px solid var(--boba-border)">
          <button type="button" class="btn btn-boba-outline"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-boba">
            <i class="bi bi-plus-circle me-1"></i><?php echo $_SESSION['role'] === 'cashier' ? 'Add to Sale' : 'Add to Cart'; ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var modal = new bootstrap.Modal(document.getElementById('productModal'));
var currentBasePrice=0;
function money(v){return '₱'+Number(v||0).toFixed(2);}
function updateDisplayedPrice(){
  var extra=0; var s=document.getElementById('sizeSelect'); if(s && s.offsetParent!==null) extra+=Number(s.selectedOptions[0]?.dataset.price||0);
  document.querySelectorAll('#toppingsList input:checked').forEach(function(cb){extra+=Number(cb.dataset.price||0);});
  document.getElementById('modalPrice').textContent=money(currentBasePrice+extra)+(extra>0?' (includes options)':'');
}
function openModal(id,name,price,cfg){
  currentBasePrice=Number(price||0); document.getElementById('modalProductId').value=id; document.getElementById('modalProductName').textContent=name; document.getElementById('qtyInput').value=1;
  var sizeWrap=document.getElementById('sizeOptionsWrap'), size=document.getElementById('sizeSelect'); size.innerHTML='';
  if(cfg.size && Array.isArray(cfg.sizes) && cfg.sizes.length){sizeWrap.style.display='block';size.disabled=false;cfg.sizes.forEach(function(o){var op=document.createElement('option');op.value=o.name;op.textContent=o.name+(Number(o.price)>0?' (+₱'+Number(o.price).toFixed(2)+')':'');op.dataset.price=o.price;size.appendChild(op);});}else{sizeWrap.style.display='none';size.disabled=true;}
  var sugar=document.getElementById('sugarSelect');document.getElementById('sugarWrap').style.display=cfg.sugar?'block':'none';sugar.disabled=!cfg.sugar;sugar.value='50%';
  var ice=document.getElementById('iceSelect');document.getElementById('iceWrap').style.display=cfg.ice?'block':'none';ice.disabled=!cfg.ice;ice.value='Regular Ice';
  var tw=document.getElementById('toppingsWrap'),tl=document.getElementById('toppingsList');tl.innerHTML='';
  if(cfg.toppings && Array.isArray(cfg.topping_options) && cfg.topping_options.length){tw.style.display='block';cfg.topping_options.forEach(function(o,i){var d=document.createElement('div');d.className='form-check';d.innerHTML='<input class="form-check-input topping-choice" type="checkbox" name="toppings[]" id="top_'+id+'_'+i+'" value="'+String(o.name).replace(/"/g,'&quot;')+'" data-price="'+o.price+'"><label class="form-check-label" for="top_'+id+'_'+i+'">'+o.name+(Number(o.price)>0?' <span class="text-muted">(+₱'+Number(o.price).toFixed(2)+')</span>':'')+'</label>';tl.appendChild(d);});}else{tw.style.display='none';}
  updateDisplayedPrice(); modal.show();
}
function changeQty(d){var i=document.getElementById('qtyInput');i.value=Math.max(1,(parseInt(i.value)||1)+d);}
document.getElementById('sizeSelect').addEventListener('change',updateDisplayedPrice);document.getElementById('toppingsList').addEventListener('change',updateDisplayedPrice);
function openProductCard(card){try{openModal(Number(card.dataset.productId||0),card.dataset.productName||'Product',Number(card.dataset.productPrice||0),JSON.parse(card.dataset.productConfig||'{}'));}catch(e){console.error(e);}}
document.addEventListener('click',function(e){var c=e.target.closest('.js-product-card');if(c)openProductCard(c);});
document.addEventListener('keydown',function(e){if((e.key==='Enter'||e.key===' ')&&e.target.classList.contains('js-product-card')){e.preventDefault();openProductCard(e.target);}});
(function(){
 var form=document.getElementById('productSearchForm'), input=document.getElementById('productSearchInput'); if(!form||!input)return;
 function all(){window.location.replace('index.php');}
 input.addEventListener('input',function(){if(this.value.trim()==='' && new URLSearchParams(location.search).has('search')) all();});
 input.addEventListener('search',function(){if(this.value.trim()==='') all();});
 form.addEventListener('submit',function(e){if(input.value.trim()===''){e.preventDefault();all();}});
})();
</script>
</body></html>