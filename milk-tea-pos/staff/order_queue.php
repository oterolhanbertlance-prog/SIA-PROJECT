<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/app_url.php';
require_once __DIR__.'/../includes/order_queue.php';
requireRole('cashier', appUrl('auth/login.php'));
$db=getDB(); ensureOrderQueueSchema($db);
$cashierId=(int)$_SESSION['user_id'];
$branchId=cashierBranchId($db,$cashierId);
$branchName='Unassigned';
if($branchId){$q=$db->prepare("SELECT name FROM branches WHERE id=?");$q->execute([$branchId]);$branchName=$q->fetchColumn() ?: 'Branch #'.$branchId;}
$stmt=null;$orders=[];
if($branchId){
 $stmt=$db->prepare("SELECT t.*,u.username AS customer_name,u.email AS customer_email,
   (SELECT COUNT(*) FROM transaction_details td WHERE td.transaction_id=t.id) AS line_count
   FROM transactions t LEFT JOIN users u ON u.id=t.user_id
   WHERE t.branch_id=? AND t.status IN ('pending','preparing') AND (u.role='customer' OR u.role IS NULL)
   ORDER BY CASE t.status WHEN 'preparing' THEN 0 ELSE 1 END,t.date_created ASC");
 $stmt->execute([$branchId]);$orders=$stmt->fetchAll();
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cashier Order Queue — Boba House POS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= appUrl('assets/css/custom.css') ?>"></head>
<body class="pos-role-cashier"><div class="d-flex" style="min-height:100vh"><?php include __DIR__.'/../includes/sidebar.php'; ?>
<div id="main-content"><div class="topbar"><h1 class="page-title"><i class="bi bi-bell-fill me-2" style="color:var(--primary)"></i>Customer Order Queue</h1><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?=htmlspecialchars($branchName)?></span></div>
<div class="content-area">
<div class="workspace-banner mb-4"><div><strong>Cashier Order Queue</strong><span>Customer orders for your assigned branch appear here automatically. You do not place or pay for the customer's order.</span></div><i class="bi bi-inboxes"></i></div>
<?php if(!$branchId): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>This cashier account has no branch assignment. Ask the Super Admin to assign a branch first.</div>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><strong id="queueCount"><?=count($orders)?></strong> active order(s)</div><div class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i>Auto-refreshes every 8 seconds</div></div>
<div id="queueGrid" class="row g-3">
<?php if(!$orders): ?><div class="col-12" id="emptyQueue"><div class="card"><div class="card-body text-center py-5 text-muted"><i class="bi bi-cup-straw" style="font-size:2.5rem"></i><div class="mt-2 fw-semibold">No customer orders waiting right now.</div><div class="small">New orders for <?=htmlspecialchars($branchName)?> will pop up here.</div></div></div></div><?php endif; ?>
<?php foreach($orders as $o): ?>
<div class="col-12 col-xl-6 queue-order" data-order-id="<?=(int)$o['id']?>"><div class="card h-100"><div class="card-body">
<div class="d-flex justify-content-between gap-3"><div><div class="d-flex align-items-center gap-2"><h5 class="mb-0 fw-bold">Order #<?=(int)$o['id']?></h5><span class="badge <?= $o['status']==='preparing'?'bg-info text-dark':'bg-warning text-dark' ?>"><?=ucfirst($o['status'])?></span></div><div class="small text-muted mt-1"><?=htmlspecialchars($o['customer_name'] ?: 'Customer')?> · <?=date('g:i A',strtotime($o['date_created']))?></div></div><div class="text-end"><div class="fw-bold fs-5">₱<?=number_format((float)$o['total_amount'],2)?></div><div class="small text-muted"><?=ucfirst($o['payment_method'])?> · <?=ucfirst($o['order_type'])?></div></div></div>
<hr><div class="d-flex justify-content-between small mb-3"><span><i class="bi bi-bag me-1"></i><?=$o['line_count']?> item line(s)</span><?php if($o['order_type']==='delivery'):?><span><i class="bi bi-truck me-1"></i>Delivery</span><?php else:?><span><i class="bi bi-shop me-1"></i>Pickup</span><?php endif;?></div>
<a class="btn btn-primary w-100" href="<?=appUrl('staff/process_order.php?id='.(int)$o['id'])?>"><i class="bi bi-box-arrow-in-right me-1"></i><?= $o['status']==='preparing'?'Continue Order':'Open & Accept Order' ?></a>
</div></div></div>
<?php endforeach; ?>
</div><?php endif; ?>
</div></div></div>
<div id="newOrderToast" class="position-fixed top-0 end-0 m-4 p-3 rounded-3 shadow bg-primary text-white" style="display:none;z-index:1085;min-width:290px"><div class="d-flex gap-2"><i class="bi bi-bell-fill fs-4"></i><div><strong>New customer order</strong><div id="newOrderToastText" class="small">A new order is waiting.</div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if($branchId): ?><script>
let lastQueueCount=<?=count($orders)?>;let toastTimer=null;
async function pollQueue(){try{const r=await fetch('<?=appUrl('staff/order_queue_api.php?action=count')?>',{cache:'no-store'});if(!r.ok)return;const d=await r.json();const n=Number(d.count||0);const countEl=document.getElementById('queueCount');if(countEl)countEl.textContent=n;const badge=document.getElementById('cashierQueueBadge');if(badge){badge.textContent=n;badge.style.display=n?'inline-flex':'none';}if(n>lastQueueCount){const t=document.getElementById('newOrderToast');document.getElementById('newOrderToastText').textContent=(n-lastQueueCount)+' new order(s) for your branch.';t.style.display='block';clearTimeout(toastTimer);toastTimer=setTimeout(()=>t.style.display='none',5000);setTimeout(()=>location.reload(),1200);}else if(n!==lastQueueCount){location.reload();}lastQueueCount=n;}catch(e){}}
setInterval(pollQueue,8000);
</script><?php endif; ?></body></html>