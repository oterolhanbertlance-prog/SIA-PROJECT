<?php
/**
 * Branches — Admin view: manage POS store branches, each with its own
 * GPS location (used for the map preview here and for HRMS's per-branch
 * attendance geofencing).
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/branch_sync.php';
require_once __DIR__ . '/../config/db_connect.php';
requireSuperAdmin();


/* Philippines land-boundary validation for branch coordinates.
 * Coordinates are [longitude, latitude] polygons based on Natural Earth.
 * The same geometry is used in the browser picker and server-side save check. */
function branchPhilippinesPolygons(): array {
    return [[[120.833896,12.704496],[120.323436,13.466413],[121.180128,13.429697],[121.527394,13.06959],[121.26219,12.20556],[120.833896,12.704496]],[[122.586089,9.981045],[122.837081,10.261157],[122.947411,10.881868],[123.49885,10.940624],[123.337774,10.267384],[124.077936,11.232726],[123.982438,10.278779],[123.623183,9.950091],[123.309921,9.318269],[122.995883,9.022189],[122.380055,9.713361],[122.586089,9.981045]],[[126.376814,8.414706],[126.478513,7.750354],[126.537424,7.189381],[126.196773,6.274294],[125.831421,7.293715],[125.363852,6.786485],[125.683161,6.049657],[125.396512,5.581003],[124.219788,6.161355],[123.93872,6.885136],[124.243662,7.36061],[123.610212,7.833527],[123.296071,7.418876],[122.825506,7.457375],[122.085499,6.899424],[121.919928,7.192119],[122.312359,8.034962],[122.942398,8.316237],[123.487688,8.69301],[123.841154,8.240324],[124.60147,8.514158],[124.764612,8.960409],[125.471391,8.986997],[125.412118,9.760335],[126.222714,9.286074],[126.306637,8.782487],[126.376814,8.414706]],[[118.504581,9.316383],[117.174275,8.3675],[117.664477,9.066889],[118.386914,9.6845],[118.987342,10.376292],[119.511496,11.369668],[119.689677,10.554291],[119.029458,10.003653],[118.504581,9.316383]],[[122.336957,18.224883],[122.174279,17.810283],[122.515654,17.093505],[122.252311,16.262444],[121.662786,15.931018],[121.50507,15.124814],[121.728829,14.328376],[122.258925,14.218202],[122.701276,14.336541],[123.950295,13.782131],[123.855107,13.237771],[124.181289,12.997527],[124.077419,12.536677],[123.298035,13.027526],[122.928652,13.55292],[122.671355,13.185836],[122.03465,13.784482],[121.126385,13.636687],[120.628637,13.857656],[120.679384,14.271016],[120.991819,14.525393],[120.693336,14.756671],[120.564145,14.396279],[120.070429,14.970869],[119.920929,15.406347],[119.883773,16.363704],[120.286488,16.034629],[120.390047,17.599081],[120.715867,18.505227],[121.321308,18.504065],[121.937601,18.218552],[122.246006,18.47895],[122.336957,18.224883]],[[122.03837,11.415841],[121.883548,11.891755],[122.483821,11.582187],[123.120217,11.58366],[123.100838,11.165934],[122.637714,10.741308],[122.00261,10.441017],[121.967367,10.905691],[122.03837,11.415841]],[[125.502552,12.162695],[125.783465,11.046122],[125.011884,11.311455],[125.032761,10.975816],[125.277449,10.358722],[124.801819,10.134679],[124.760168,10.837995],[124.459101,10.88993],[124.302522,11.495371],[124.891013,11.415583],[124.87799,11.79419],[124.266762,12.557761],[125.227116,12.535721],[125.502552,12.162695]]];
}
function pointInBranchPolygon(float $lng, float $lat, array $poly): bool {
    $inside = false;
    $n = count($poly);
    for ($i=0, $j=$n-1; $i<$n; $j=$i++) {
        [$xi,$yi] = $poly[$i]; [$xj,$yj] = $poly[$j];
        $cross = (($yi > $lat) !== ($yj > $lat)) &&
            ($lng < ($xj-$xi) * ($lat-$yi) / (($yj-$yi) ?: 1.0e-12) + $xi);
        if ($cross) $inside = !$inside;
    }
    return $inside;
}
function isValidPhilippinesBranchLocation(?float $lat, ?float $lng): bool {
    if ($lat === null || $lng === null) return false;
    if ($lat < 4.3 || $lat > 21.3 || $lng < 116.8 || $lng > 126.8) return false;
    foreach (branchPhilippinesPolygons() as $poly) {
        if (pointInBranchPolygon($lng, $lat, $poly)) return true;
    }
    return false;
}

$db        = getDB();
$branchSyncState = syncAllHrmsBranchesToPos($db);
$pageTitle = 'Branches';

/* ── Ensure branches table exists / has lat-lng columns ────── */
$db->exec("CREATE TABLE IF NOT EXISTS branches (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    branch_code  VARCHAR(30)  NOT NULL UNIQUE,
    name         VARCHAR(150) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    city         VARCHAR(100) DEFAULT NULL,
    latitude     DECIMAL(10,7) DEFAULT NULL,
    longitude    DECIMAL(10,7) DEFAULT NULL,
    phone        VARCHAR(30)  DEFAULT NULL,
    manager_name VARCHAR(150) DEFAULT NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10,7) NULL AFTER city");
$db->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
$db->exec("ALTER TABLE branches ADD COLUMN IF NOT EXISTS manager_name VARCHAR(150) NULL AFTER phone");
$db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER role");

/* ── Handle POST ─────────────────────────────────────────── */
$success = $error = $warning = '';
if (!empty($branchSyncState['ok']) && !empty($branchSyncState['inserted'])) {
    $success = 'Branch list synchronized from HRMS: ' . (int)$branchSyncState['inserted'] . ' branch(es) added to POS.';
} elseif (isset($branchSyncState) && empty($branchSyncState['ok']) && !empty($branchSyncState['error'])) {
    $warning = 'HRMS branch sync could not run: ' . $branchSyncState['error'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bid    = (int)($_POST['branch_id'] ?? 0);

    try {
        if (in_array($action, ['add', 'edit'], true)) {
            $name    = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city    = trim($_POST['city'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $manager = trim($_POST['manager'] ?? '');
            $status  = in_array(($_POST['status'] ?? 'active'), ['active','inactive'], true) ? $_POST['status'] : 'active';
            $lat     = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
            $lng     = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;

            if ($name === '' || $address === '') {
                $error = 'Name and address are required.';
            } elseif (($lat === null) !== ($lng === null)) {
                $error = 'Set both latitude and longitude, or leave both blank.';
            } elseif ($lat !== null && !isValidPhilippinesBranchLocation($lat, $lng)) {
                $error = 'Branch location must be pinned on Philippine land. Water areas and locations outside the Philippines are not allowed.';
            } else {
                if ($bid > 0) {
                    $exists = $db->prepare('SELECT id FROM branches WHERE id=? LIMIT 1');
                    $exists->execute([$bid]);
                    if (!$exists->fetchColumn()) {
                        throw new RuntimeException('Branch not found. Refresh the page and try again.');
                    }
                    $db->prepare("UPDATE branches SET name=?,address=?,city=?,latitude=?,longitude=?,phone=?,manager_name=?,status=?,updated_at=NOW() WHERE id=?")
                       ->execute([$name,$address,$city ?: null,$lat,$lng,$phone ?: null,$manager ?: null,$status,$bid]);
                    $savedId = $bid;
                    $success = 'Branch updated.';
                } else {
                    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$name),0,3)) ?: 'BRN';
                    do {
                        $code = 'BR-' . $prefix . '-' . date('y') . '-' . random_int(100,999);
                        $codeCheck = $db->prepare('SELECT COUNT(*) FROM branches WHERE branch_code=?');
                        $codeCheck->execute([$code]);
                    } while ((int)$codeCheck->fetchColumn() > 0);
                    $db->prepare("INSERT INTO branches (branch_code,name,address,city,latitude,longitude,phone,manager_name,status) VALUES (?,?,?,?,?,?,?,?,?)")
                       ->execute([$code,$name,$address,$city ?: null,$lat,$lng,$phone ?: null,$manager ?: null,'active']);
                    $savedId = (int)$db->lastInsertId();
                    $success = 'Branch added.';
                }

                if (syncPosBranchToHrms($db, $savedId)) {
                    $success .= ' Synced to HRMS.';
                } else {
                    $warning = 'The POS branch was saved, but HRMS sync failed. Make sure the hrms database exists and MySQL is running.';
                }
            }
        } elseif ($action === 'delete' && $bid > 0) {
            $db->prepare("UPDATE branches SET status='inactive',updated_at=NOW() WHERE id=?")->execute([$bid]);
            $success = 'Branch deactivated.';
            if (syncPosBranchToHrms($db, $bid)) {
                $success .= ' Synced to HRMS.';
            } else {
                $warning = 'The POS branch was deactivated, but HRMS sync failed.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$branches = $db->query("SELECT b.*,
    (SELECT GROUP_CONCAT(username SEPARATOR ', ') FROM users u WHERE u.branch_id=b.id AND u.role='super_admin' AND u.is_active=1) AS pos_admins,
    (SELECT COUNT(*) FROM users u WHERE u.branch_id=b.id AND u.role IN ('cashier','inventory_staff') AND u.is_active=1) AS pos_staff_count
    FROM branches b ORDER BY b.name")->fetchAll(PDO::FETCH_ASSOC);
try {
    $hrms=getHrmsDB();
    foreach($branches as &$bb){
        $q=$hrms->prepare("SELECT GROUP_CONCAT(CONCAT_WS(' ',firstname,lastname) SEPARATOR ', ') FROM users WHERE branch_id=(SELECT id FROM branches WHERE pos_branch_id=? OR branch_code=? LIMIT 1) AND role='hr' AND status='active'");
        $q->execute([(int)$bb['id'],$bb['branch_code']]); $bb['hr_leads']=$q->fetchColumn() ?: 'Head Office HR';
    } unset($bb);
} catch(Throwable $e){ foreach($branches as &$bb){$bb['hr_leads']='HRMS unavailable';} unset($bb); }
$branchesJson = json_encode(array_map(fn($b) => [
    'id' => (int)$b['id'], 'branch_code' => $b['branch_code'], 'name' => $b['name'], 'address' => $b['address'], 'city' => $b['city'],
    'latitude' => $b['latitude'] !== null ? (float)$b['latitude'] : null,
    'longitude' => $b['longitude'] !== null ? (float)$b['longitude'] : null,
    'lat' => $b['latitude'] !== null ? (float)$b['latitude'] : null,
    'lng' => $b['longitude'] !== null ? (float)$b['longitude'] : null,
    'phone' => $b['phone'], 'manager_name' => $b['manager_name'], 'status' => $b['status'],
], $branches), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$callerFile = __FILE__;
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.branch-stats .card{height:100%;display:flex;justify-content:center;min-height:112px}
.branch-table td,.branch-table th{vertical-align:middle!important}
.branch-table .branch-code{display:inline-flex;align-items:center;min-height:26px}
#branchModal .modal-content{border:0;border-radius:18px;overflow:hidden;box-shadow:0 24px 70px rgba(26,31,58,.20)}
#branchModal.show .modal-dialog{animation:branchModalIn .26s ease both}
#overviewMap,#pickerMap{z-index:1}
#overviewMap{height:170px!important;min-height:170px;background:#f8fafc}
.branch-map-card{margin-bottom:1rem!important}
.branch-list-card{scroll-margin-top:12px}
.branch-empty-map{height:120px;display:flex;align-items:center;justify-content:center;flex-direction:column;color:#6c757d;background:#f8fafc}
.branch-summary-card{animation:posRise .45s ease both}
@keyframes branchModalIn{from{opacity:0;transform:translateY(16px) scale(.985)}to{opacity:1;transform:none}}
@keyframes posRise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){#branchModal.show .modal-dialog,.branch-summary-card{animation:none}}
</style>

<div class="d-flex" style="min-height:100vh">
<?php require_once '../includes/sidebar.php'; ?>
<div id="main-content" style="flex:1;overflow-y:auto">
  <div class="topbar">
    <h5 class="page-title"><i class="bi bi-building me-2"></i>Branches</h5>
    <div class="d-flex gap-2">
      <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
      <button class="btn btn-primary btn-sm" onclick="openAddModal()">
        <i class="bi bi-plus-lg me-1"></i>Add Branch
      </button>
    </div>
  </div>
  <div class="content-area">

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($warning): ?><div class="alert alert-warning"><?= htmlspecialchars($warning) ?></div><?php endif; ?>

  <div class="row g-3 mb-4 branch-stats">
    <div class="col-sm-4">
      <div class="card text-center py-3 border-0 shadow-sm branch-summary-card">
        <div class="fs-3 fw-bold text-primary"><?= count($branches) ?></div>
        <div class="small text-muted">Total Branches</div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card text-center py-3 border-0 shadow-sm branch-summary-card">
        <div class="fs-3 fw-bold text-success"><?= count(array_filter($branches, fn($b)=>$b['status']==='active')) ?></div>
        <div class="small text-muted">Active</div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card text-center py-3 border-0 shadow-sm branch-summary-card">
        <div class="fs-3 fw-bold text-secondary"><?= count(array_filter($branches, fn($b)=>$b['status']!=='active')) ?></div>
        <div class="small text-muted">Inactive</div>
      </div>
    </div>
  </div>

  <!-- Overview map — every branch pinned at once -->
  <div class="card border-0 shadow-sm branch-map-card">
    <div class="card-header bg-white fw-semibold py-2 d-flex justify-content-between align-items-center">
      <span><i class="bi bi-map me-2"></i>Branch Locations</span>
      <span class="small text-muted">Click Edit to update a branch pin</span>
    </div>
    <div class="card-body p-0">
      <?php if (count(array_filter($branches, fn($b) => $b['latitude'] !== null && $b['longitude'] !== null)) > 0): ?>
      <div id="overviewMap" style="border-radius:0 0 .5rem .5rem"></div>
      <?php else: ?>
      <div class="branch-empty-map">
        <i class="bi bi-geo-alt fs-3 mb-1"></i>
        <div class="fw-semibold">No branch locations pinned yet</div>
        <div class="small">Use the Edit button below to set latitude and longitude.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card border-0 shadow-sm branch-list-card">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 branch-table">
        <thead class="table-light">
          <tr><th>Code</th><th>Branch Name</th><th>Address / City</th><th>Location</th><th>HR Lead</th><th>POS Admin</th><th>POS Staff</th><th>Status</th><th class="text-center">Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($branches)): ?>
        <tr><td colspan="9" class="text-center py-5 text-muted">
          <i class="bi bi-building fs-2 d-block mb-2"></i>No branches yet. Click "Add Branch" to get started.
        </td></tr>
        <?php else: foreach ($branches as $br): ?>
        <tr>
          <td><span class="badge bg-secondary-subtle text-secondary font-monospace branch-code"><?= htmlspecialchars($br['branch_code']) ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($br['name']) ?></td>
          <td>
            <div><?= htmlspecialchars($br['address']) ?></div>
            <?php if ($br['city']): ?><div class="text-muted small"><?= htmlspecialchars($br['city']) ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ($br['latitude'] !== null && $br['longitude'] !== null): ?>
              <a href="https://www.openstreetmap.org/?mlat=<?= $br['latitude'] ?>&mlon=<?= $br['longitude'] ?>#map=16/<?= $br['latitude'] ?>/<?= $br['longitude'] ?>" target="_blank" class="small">
                <i class="bi bi-geo-alt-fill text-danger"></i> <?= number_format((float)$br['latitude'],5) ?>, <?= number_format((float)$br['longitude'],5) ?>
              </a>
            <?php else: ?>
              <span class="text-muted small">Not set</span>
            <?php endif; ?>
          </td>
          <td><small><?= htmlspecialchars($br['hr_leads'] ?: 'Head Office HR') ?></small></td>
          <td><small><?= htmlspecialchars($br['pos_admins'] ?: 'Head Office Super Admin') ?></small></td>
          <td><span class="badge bg-info-subtle text-info"><?= (int)$br['pos_staff_count'] ?></span></td>
          <td><span class="badge bg-<?= $br['status']==='active'?'success':'secondary' ?>"><?= ucfirst($br['status']) ?></span></td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1"
                    onclick="openEditModalById(<?= (int)$br['id'] ?>)" title="Edit branch and map location">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this branch?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="branch_id" value="<?= $br['id'] ?>">
              <button class="btn btn-sm btn-danger py-0 px-2"><i class="bi bi-x-lg"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  </div>
</div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
    <form method="POST" id="branchForm">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="branch_id" id="formBranchId" value="">
      <div class="modal-header"><h5 class="modal-title" id="branchModalTitle">Add Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">Branch Name *</label>
            <input type="text" name="name" id="f_name" class="form-control form-control-sm" required placeholder="e.g. Cavite Branch">
          </div>
          <div class="col-md-6">
            <label class="form-label small">City</label>
            <input type="text" name="city" id="f_city" class="form-control form-control-sm" placeholder="e.g. Imus, Cavite">
          </div>
          <div class="col-12">
            <label class="form-label small">Address *</label>
            <input type="text" name="address" id="f_address" class="form-control form-control-sm" required placeholder="Street address">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Phone</label>
            <input type="text" name="phone" id="f_phone" class="form-control form-control-sm" placeholder="09XXXXXXXXX">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Branch Manager</label>
            <input type="text" name="manager" id="f_manager" class="form-control form-control-sm" placeholder="Manager name">
          </div>
          <div class="col-12">
            <label class="form-label small d-flex justify-content-between align-items-center">
              <span>Map Location <span class="text-muted">(Philippines land only — water/outside locations are blocked)</span></span>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="useMyLocation()"><i class="bi bi-crosshair"></i> Use My Location</button>
            </label>
            <div id="pickerMap" style="height:260px;border-radius:.4rem;border:1px solid var(--boba-border,#dee2e6)"></div>
          </div>
          <div class="col-12">
            <div id="addressLookupStatus" class="small text-muted" aria-live="polite">
              <i class="bi bi-geo-alt"></i> Pin a Philippine land location to fill the street address and city automatically.
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Latitude</label>
            <input type="number" step="0.0000001" name="latitude" id="f_lat" min="4.3" max="21.3" class="form-control form-control-sm" placeholder="e.g. 14.4297">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Longitude</label>
            <input type="number" step="0.0000001" name="longitude" id="f_lng" min="116.8" max="126.8" class="form-control form-control-sm" placeholder="e.g. 120.9367">
          </div>
          <div class="col-12" id="editStatusWrap" style="display:none">
            <label class="form-label small">Status</label>
            <select name="status" id="f_status" class="form-select form-select-sm">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" id="branchSubmitBtn">Add Branch</button>
      </div>
    </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const ALL_BRANCHES = <?= $branchesJson ?: '[]' ?>;
const DEFAULT_CENTER = [12.8797, 121.7740];
const PH_BRANCH_POLYGONS = [[[120.833896,12.704496],[120.323436,13.466413],[121.180128,13.429697],[121.527394,13.06959],[121.26219,12.20556],[120.833896,12.704496]],[[122.586089,9.981045],[122.837081,10.261157],[122.947411,10.881868],[123.49885,10.940624],[123.337774,10.267384],[124.077936,11.232726],[123.982438,10.278779],[123.623183,9.950091],[123.309921,9.318269],[122.995883,9.022189],[122.380055,9.713361],[122.586089,9.981045]],[[126.376814,8.414706],[126.478513,7.750354],[126.537424,7.189381],[126.196773,6.274294],[125.831421,7.293715],[125.363852,6.786485],[125.683161,6.049657],[125.396512,5.581003],[124.219788,6.161355],[123.93872,6.885136],[124.243662,7.36061],[123.610212,7.833527],[123.296071,7.418876],[122.825506,7.457375],[122.085499,6.899424],[121.919928,7.192119],[122.312359,8.034962],[122.942398,8.316237],[123.487688,8.69301],[123.841154,8.240324],[124.60147,8.514158],[124.764612,8.960409],[125.471391,8.986997],[125.412118,9.760335],[126.222714,9.286074],[126.306637,8.782487],[126.376814,8.414706]],[[118.504581,9.316383],[117.174275,8.3675],[117.664477,9.066889],[118.386914,9.6845],[118.987342,10.376292],[119.511496,11.369668],[119.689677,10.554291],[119.029458,10.003653],[118.504581,9.316383]],[[122.336957,18.224883],[122.174279,17.810283],[122.515654,17.093505],[122.252311,16.262444],[121.662786,15.931018],[121.50507,15.124814],[121.728829,14.328376],[122.258925,14.218202],[122.701276,14.336541],[123.950295,13.782131],[123.855107,13.237771],[124.181289,12.997527],[124.077419,12.536677],[123.298035,13.027526],[122.928652,13.55292],[122.671355,13.185836],[122.03465,13.784482],[121.126385,13.636687],[120.628637,13.857656],[120.679384,14.271016],[120.991819,14.525393],[120.693336,14.756671],[120.564145,14.396279],[120.070429,14.970869],[119.920929,15.406347],[119.883773,16.363704],[120.286488,16.034629],[120.390047,17.599081],[120.715867,18.505227],[121.321308,18.504065],[121.937601,18.218552],[122.246006,18.47895],[122.336957,18.224883]],[[122.03837,11.415841],[121.883548,11.891755],[122.483821,11.582187],[123.120217,11.58366],[123.100838,11.165934],[122.637714,10.741308],[122.00261,10.441017],[121.967367,10.905691],[122.03837,11.415841]],[[125.502552,12.162695],[125.783465,11.046122],[125.011884,11.311455],[125.032761,10.975816],[125.277449,10.358722],[124.801819,10.134679],[124.760168,10.837995],[124.459101,10.88993],[124.302522,11.495371],[124.891013,11.415583],[124.87799,11.79419],[124.266762,12.557761],[125.227116,12.535721],[125.502552,12.162695]]];
const PH_MAP_BOUNDS = L.latLngBounds([4.3, 116.8], [21.3, 126.8]);
function pointInPhPolygon(lat, lng, poly) {
    let inside = false;
    for (let i=0, j=poly.length-1; i<poly.length; j=i++) {
        const [xi, yi] = poly[i], [xj, yj] = poly[j];
        const intersect = ((yi > lat) !== (yj > lat)) &&
            (lng < (xj-xi) * (lat-yi) / ((yj-yi) || 1e-12) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}
function isPhilippinesLand(lat, lng) {
    lat = Number(lat); lng = Number(lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false;
    if (lat < 4.3 || lat > 21.3 || lng < 116.8 || lng > 126.8) return false;
    return PH_BRANCH_POLYGONS.some(poly => pointInPhPolygon(lat, lng, poly));
}
function rejectInvalidBranchLocation(lat, lng) {
    if (isPhilippinesLand(lat, lng)) return false;
    alert('Branch pins must be placed on Philippine land. Water areas and locations outside the Philippines are not allowed.');
    return true;
}

const branchModalEl = document.getElementById('branchModal');
const branchModal = new bootstrap.Modal(branchModalEl);

const overviewEl = document.getElementById('overviewMap');
if (overviewEl) {
    const overview = L.map(overviewEl, {maxBounds:PH_MAP_BOUNDS, maxBoundsViscosity:1.0, minZoom:5}).setView(DEFAULT_CENTER, 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    }).addTo(overview);
    const withCoords = ALL_BRANCHES.filter(b => b.lat !== null && b.lng !== null);
    if (withCoords.length) {
        const bounds = [];
        withCoords.forEach(b => {
            L.marker([b.lat, b.lng]).addTo(overview)
                .bindPopup(`<strong>${escapeHtml(b.name)}</strong><br>${escapeHtml(b.address || '')}${b.city ? ', ' + escapeHtml(b.city) : ''}`);
            bounds.push([b.lat, b.lng]);
        });
        overview.fitBounds(bounds, { padding: [20, 20], maxZoom: 14 });
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

let pickerMap, pickerMarker;
function initPickerMap(lat, lng) {
    if (pickerMap) { pickerMap.remove(); }
    const startLat = lat ?? 12.8797, startLng = lng ?? 121.7740, startZoom = lat ? 15 : 6;
    pickerMap = L.map('pickerMap', {maxBounds:PH_MAP_BOUNDS, maxBoundsViscosity:1.0, minZoom:5}).setView([startLat, startLng], startZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    }).addTo(pickerMap);

    pickerMarker = L.marker([startLat, startLng], { draggable: true }).addTo(pickerMap);
    if (lat) pickerMarker.setOpacity(1); else pickerMarker.setOpacity(0.4);

    pickerMarker.on('dragend', () => {
        const p = pickerMarker.getLatLng();
        if (rejectInvalidBranchLocation(p.lat, p.lng)) { initPickerMap(lat, lng); return; }
        setBranchLocation(p.lat, p.lng, true);
    });
    pickerMap.on('click', (e) => {
        if (rejectInvalidBranchLocation(e.latlng.lat, e.latlng.lng)) return;
        pickerMarker.setLatLng(e.latlng).setOpacity(1);
        setBranchLocation(e.latlng.lat, e.latlng.lng, true);
    });
}
function setLatLng(lat, lng) {
    document.getElementById('f_lat').value = lat.toFixed(7);
    document.getElementById('f_lng').value = lng.toFixed(7);
}

let branchAddressLookupController = null;
const branchAddressCache = new Map();

function setAddressLookupStatus(message, state = 'muted') {
    const el = document.getElementById('addressLookupStatus');
    if (!el) return;
    const icon = state === 'success' ? 'bi-check-circle-fill' : state === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-geo-alt';
    const cls = state === 'success' ? 'text-success' : state === 'error' ? 'text-warning' : 'text-muted';
    el.className = `small ${cls}`;
    el.innerHTML = `<i class="bi ${icon}"></i> ${escapeHtml(message)}`;
}

function buildPhilippineStreetAddress(a, displayName = '') {
    const road = a.road || a.pedestrian || a.footway || a.path || a.residential || a.cycleway || '';
    const house = a.house_number || '';
    const place = a.neighbourhood || a.suburb || a.quarter || a.village || a.hamlet || a.borough || '';
    const barangay = a.city_district || a.district || '';
    const municipality = a.city || a.municipality || a.town || '';
    const province = a.state || a.region || '';
    const postcode = a.postcode || '';

    const first = [house, road].filter(Boolean).join(' ').trim();
    const pieces = [];
    [first, place, barangay, municipality, province, postcode].forEach(v => {
        v = String(v || '').trim();
        if (v && !pieces.some(x => x.toLowerCase() === v.toLowerCase())) pieces.push(v);
    });

    if (pieces.length) return pieces.join(', ');
    // Last fallback: Nominatim's display name, minus the country at the end.
    return String(displayName || '').replace(/,\s*Philippines\s*$/i, '').trim();
}

function buildPhilippineCity(a) {
    const locality = a.city || a.municipality || a.town || a.village || a.city_district || '';
    const province = a.state || a.region || '';
    if (locality && province && locality.toLowerCase() !== province.toLowerCase()) return `${locality}, ${province}`;
    return locality || province || '';
}

async function reverseGeocodeBranchLocation(lat, lng) {
    lat = Number(lat); lng = Number(lng);
    if (!isPhilippinesLand(lat, lng)) return;

    const cacheKey = `${lat.toFixed(5)},${lng.toFixed(5)}`;
    if (branchAddressCache.has(cacheKey)) {
        applyReverseGeocodeResult(branchAddressCache.get(cacheKey));
        return;
    }

    if (branchAddressLookupController) branchAddressLookupController.abort();
    branchAddressLookupController = new AbortController();
    setAddressLookupStatus('Looking up the street address…');

    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=18&addressdetails=1&accept-language=en`;
        const response = await fetch(url, {
            signal: branchAddressLookupController.signal,
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error(`Address lookup returned ${response.status}`);
        const data = await response.json();
        if (!data || !data.address) throw new Error('No address was found for this point.');

        const countryCode = String(data.address.country_code || '').toLowerCase();
        if (countryCode && countryCode !== 'ph') {
            throw new Error('The selected point did not resolve to a Philippine address.');
        }

        branchAddressCache.set(cacheKey, data);
        applyReverseGeocodeResult(data);
    } catch (err) {
        if (err && err.name === 'AbortError') return;
        console.warn('Branch reverse geocoding failed:', err);
        setAddressLookupStatus('Pin saved, but the address lookup is unavailable. You can type the street address and city manually.', 'error');
    }
}

function applyReverseGeocodeResult(data) {
    const a = data.address || {};
    const street = buildPhilippineStreetAddress(a, data.display_name || '');
    const city = buildPhilippineCity(a);
    const addressInput = document.getElementById('f_address');
    const cityInput = document.getElementById('f_city');

    if (addressInput && street) addressInput.value = street;
    if (cityInput && city) cityInput.value = city;

    if (street || city) {
        setAddressLookupStatus('Address filled from the selected map pin. You can still edit it before saving.', 'success');
    } else {
        setAddressLookupStatus('Pin selected, but no street name was returned. You can type the address manually.', 'error');
    }
}

function setBranchLocation(lat, lng, lookupAddress = true) {
    setLatLng(Number(lat), Number(lng));
    if (lookupAddress) reverseGeocodeBranchLocation(Number(lat), Number(lng));
}

function useMyLocation() {
    if (!navigator.geolocation) { alert('Geolocation is not supported by this browser.'); return; }
    navigator.geolocation.getCurrentPosition(pos => {
        const { latitude, longitude } = pos.coords;
        if (rejectInvalidBranchLocation(latitude, longitude)) return;
        pickerMarker.setLatLng([latitude, longitude]).setOpacity(1);
        pickerMap.setView([latitude, longitude], 16);
        setBranchLocation(latitude, longitude, true);
    }, () => alert('Could not get your location.'));
}

['f_lat','f_lng'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        const lat = parseFloat(document.getElementById('f_lat').value);
        const lng = parseFloat(document.getElementById('f_lng').value);
        if (!isNaN(lat) && !isNaN(lng) && pickerMap) {
            if (rejectInvalidBranchLocation(lat, lng)) return;
            pickerMarker.setLatLng([lat, lng]).setOpacity(1);
            pickerMap.setView([lat, lng], 15);
                if (isPhilippinesLand(lat, lng)) reverseGeocodeBranchLocation(lat, lng);
        }
    });
});


const branchForm = document.getElementById('branchForm');
if (branchForm) {
    branchForm.addEventListener('submit', (e) => {
        const latRaw = document.getElementById('f_lat').value.trim();
        const lngRaw = document.getElementById('f_lng').value.trim();
        if ((latRaw === '') !== (lngRaw === '')) {
            e.preventDefault();
            alert('Set both latitude and longitude, or leave both blank.');
            return;
        }
        if (latRaw !== '' && rejectInvalidBranchLocation(Number(latRaw), Number(lngRaw))) {
            e.preventDefault();
        }
    });
}

function openAddModal() {
    document.getElementById('branchModalTitle').textContent = 'Add Branch';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formBranchId').value = '';
    document.getElementById('branchSubmitBtn').textContent = 'Add Branch';
    document.getElementById('editStatusWrap').style.display = 'none';
    ['f_name','f_city','f_address','f_phone','f_manager','f_lat','f_lng'].forEach(id => document.getElementById(id).value = '');
    branchModal.show();
    setTimeout(() => { initPickerMap(null, null); }, 200);
}

function openEditModalById(id) {
    const b = ALL_BRANCHES.find(branch => Number(branch.id) === Number(id));
    if (!b) {
        alert('Could not load this branch. Please refresh the page and try again.');
        return;
    }
    openEditModal(b);
}

function openEditModal(b) {
    document.getElementById('branchModalTitle').textContent = 'Edit Branch — ' + b.name;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formBranchId').value = b.id;
    document.getElementById('branchSubmitBtn').textContent = 'Save Changes';
    document.getElementById('editStatusWrap').style.display = '';
    document.getElementById('f_name').value = b.name || '';
    document.getElementById('f_city').value = b.city || '';
    document.getElementById('f_address').value = b.address || '';
    document.getElementById('f_phone').value = b.phone || '';
    document.getElementById('f_manager').value = b.manager_name || '';
    document.getElementById('f_lat').value = b.latitude || '';
    document.getElementById('f_lng').value = b.longitude || '';
    document.getElementById('f_status').value = b.status || 'active';
    branchModal.show();
    setTimeout(() => {
        initPickerMap(b.latitude ? parseFloat(b.latitude) : null, b.longitude ? parseFloat(b.longitude) : null);
    }, 200);
}

branchModalEl.addEventListener('shown.bs.modal', () => { if (pickerMap) pickerMap.invalidateSize(); });
</script>
</body></html>
