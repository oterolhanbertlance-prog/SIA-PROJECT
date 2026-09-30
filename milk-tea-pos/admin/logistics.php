<?php
/** Logistics — branch-based delivery deployment. No separate delivery zones are required. */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/branch_sync.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/delivery_schema.php';
requireSuperAdmin();
$db=getDB();
$branchSyncState = syncAllHrmsBranchesToPos($db); ensureDeliveryColumns($db); $pageTitle='Logistics';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';
    if ($action==='save_branch_delivery') {
        $bid=(int)($_POST['branch_id']??0); $fee=max(0,(float)($_POST['delivery_fee']??50));
        $radius=max(.5,(float)($_POST['delivery_radius_km']??8)); $eta=max(5,(int)($_POST['delivery_eta_minutes']??30));
        $db->prepare('UPDATE branches SET delivery_fee=?,delivery_radius_km=?,delivery_eta_minutes=?,updated_at=NOW() WHERE id=?')->execute([$fee,$radius,$eta,$bid]);
        header('Location: logistics.php?success='.urlencode('Branch delivery settings saved.')); exit;
    }
    if ($action==='add_rider' || $action==='edit_rider') {
        $name=trim($_POST['rname']??''); $phone=trim($_POST['phone']??''); $vehicle=trim($_POST['vehicle']??''); $plate=trim($_POST['plate']??'');
        $status=in_array($_POST['rstatus']??'', ['available','on_delivery','off_duty'],true)?$_POST['rstatus']:'available';
        $branchId=(int)($_POST['branch_id']??0)?:null; $id=(int)($_POST['rider_id']??0);
        if (!$name || !$phone || !$branchId) { header('Location: logistics.php?error='.urlencode('Rider name, phone, and branch are required.')); exit; }
        if ($id) $db->prepare('UPDATE delivery_riders SET name=?,phone=?,vehicle=?,plate_no=?,status=?,branch_id=?,updated_at=NOW() WHERE id=?')->execute([$name,$phone,$vehicle?:null,$plate?:null,$status,$branchId,$id]);
        else $db->prepare('INSERT INTO delivery_riders (name,phone,vehicle,plate_no,status,branch_id) VALUES (?,?,?,?,?,?)')->execute([$name,$phone,$vehicle?:null,$plate?:null,$status,$branchId]);
        header('Location: logistics.php?success='.urlencode('Driver saved and deployed to branch.')); exit;
    }
    if ($action==='toggle_rider') { $id=(int)($_POST['rider_id']??0); $st=$_POST['new_status']==='off_duty'?'off_duty':'available'; $db->prepare('UPDATE delivery_riders SET status=?,updated_at=NOW() WHERE id=?')->execute([$st,$id]); header('Location: logistics.php'); exit; }
}
$branches=$db->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$riders=$db->query("SELECT r.*,b.name branch_name,b.city branch_city FROM delivery_riders r LEFT JOIN branches b ON b.id=r.branch_id ORDER BY b.name,r.name")->fetchAll();
$callerFile=__FILE__; require_once '../includes/header.php';
?>
<div class="d-flex" style="min-height:100vh"><?php require_once '../includes/sidebar.php'; ?><div id="main-content" style="flex:1;overflow-y:auto">
<div class="topbar"><h5 class="page-title"><i class="bi bi-truck me-2"></i>Branch Delivery & Drivers</h5><a href="dashboard.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a></div>
<div class="content-area">
<?php if(isset($_GET['success'])):?><div class="alert alert-success"><?=htmlspecialchars($_GET['success'])?></div><?php endif;?>
<?php if(isset($_GET['error'])):?><div class="alert alert-danger"><?=htmlspecialchars($_GET['error'])?></div><?php endif;?>
<div class="alert alert-info border-0 shadow-sm"><i class="bi bi-info-circle me-2"></i><strong>No delivery zones needed.</strong> Every driver belongs to a branch, and deliveries from that branch are shown only to its deployed drivers.</div>
<h5 class="mb-3"><i class="bi bi-building me-2"></i>Branch Delivery Settings</h5>
<div class="row g-3 mb-5">
<?php foreach($branches as $b):?><div class="col-lg-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body">
<div class="fw-bold mb-1"><?=htmlspecialchars($b['name'])?></div><div class="small text-muted mb-3"><?=htmlspecialchars($b['city']?:$b['address'])?></div>
<form method="post" class="row g-2"><input type="hidden" name="action" value="save_branch_delivery"><input type="hidden" name="branch_id" value="<?=$b['id']?>">
<div class="col-4"><label class="form-label small">Fee ₱</label><input class="form-control form-control-sm" type="number" step=".01" min="0" name="delivery_fee" value="<?=$b['delivery_fee']?>"></div>
<div class="col-4"><label class="form-label small">Radius km</label><input class="form-control form-control-sm" type="number" step=".1" min=".5" name="delivery_radius_km" value="<?=$b['delivery_radius_km']?>"></div>
<div class="col-4"><label class="form-label small">ETA min</label><input class="form-control form-control-sm" type="number" min="5" name="delivery_eta_minutes" value="<?=$b['delivery_eta_minutes']?>"></div>
<div class="col-12"><button class="btn btn-sm btn-primary w-100 mt-1">Save Delivery Settings</button></div></form>
</div></div></div><?php endforeach;?>
</div>
<div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Drivers by Branch</h5><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#riderModal"><i class="bi bi-plus-lg me-1"></i>Add Driver</button></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Driver</th><th>Branch Deployment</th><th>Phone</th><th>Vehicle</th><th>Status</th><th></th></tr></thead><tbody>
<?php if(!$riders):?><tr><td colspan="6" class="text-center py-4 text-muted">No drivers yet.</td></tr><?php endif;?>
<?php foreach($riders as $r):?><tr><td class="fw-semibold"><?=htmlspecialchars($r['name'])?></td><td><span class="badge bg-primary-subtle text-primary"><?=htmlspecialchars($r['branch_name']??'Unassigned')?></span></td><td><?=htmlspecialchars($r['phone'])?></td><td><?=htmlspecialchars(trim(($r['vehicle']??'').' '.($r['plate_no']??''))?:'—')?></td><td><span class="badge bg-<?=$r['status']==='available'?'success':($r['status']==='on_delivery'?'warning':'secondary')?>"><?=ucwords(str_replace('_',' ',$r['status']))?></span></td><td><button class="btn btn-sm btn-outline-primary" onclick='editRider(<?=json_encode($r,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'><i class="bi bi-pencil"></i></button></td></tr><?php endforeach;?>
</tbody></table></div></div>
</div></div></div>
<div class="modal fade" id="riderModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="action" id="rAction" value="add_rider"><input type="hidden" name="rider_id" id="rId"><div class="modal-header"><h5 class="modal-title">Driver Deployment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-7"><label class="form-label small">Driver Name *</label><input class="form-control" name="rname" id="rName" required></div><div class="col-5"><label class="form-label small">Phone *</label><input class="form-control" name="phone" id="rPhone" required></div>
<div class="col-6"><label class="form-label small">Vehicle</label><input class="form-control" name="vehicle" id="rVehicle"></div><div class="col-6"><label class="form-label small">Plate No.</label><input class="form-control" name="plate" id="rPlate"></div>
<div class="col-8"><label class="form-label small">Deploy to Branch *</label><select class="form-select" name="branch_id" id="rBranch" required><option value="">Select branch…</option><?php foreach($branches as $b):?><option value="<?=$b['id']?>"><?=htmlspecialchars($b['name'])?></option><?php endforeach;?></select></div><div class="col-4"><label class="form-label small">Status</label><select class="form-select" name="rstatus" id="rStatus"><option value="available">Available</option><option value="off_duty">Off Duty</option></select></div>
</div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Driver</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>
function editRider(r){document.getElementById('rAction').value='edit_rider';document.getElementById('rId').value=r.id;document.getElementById('rName').value=r.name||'';document.getElementById('rPhone').value=r.phone||'';document.getElementById('rVehicle').value=r.vehicle||'';document.getElementById('rPlate').value=r.plate_no||'';document.getElementById('rBranch').value=r.branch_id||'';document.getElementById('rStatus').value=r.status==='on_delivery'?'available':(r.status||'available');new bootstrap.Modal(document.getElementById('riderModal')).show();}
</script>
