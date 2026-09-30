<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/app_url.php';
require_once __DIR__.'/../includes/order_queue.php';
require_once __DIR__.'/../includes/product_customization.php';
requireRole('cashier',appUrl('auth/login.php'));
$db=getDB();ensureProductCustomizationSchema($db);ensureOrderQueueSchema($db);$cashierId=(int)$_SESSION['user_id'];$branchId=cashierBranchId($db,$cashierId);
$id=(int)($_GET['id']??$_POST['id']??0);$error='';$success='';
function loadOrder(PDO $db,int $id,?int $branchId){if(!$branchId)return false;$q=$db->prepare("SELECT t.*,u.username AS customer_name,u.email AS customer_email,b.name AS branch_name FROM transactions t LEFT JOIN users u ON u.id=t.user_id LEFT JOIN branches b ON b.id=t.branch_id WHERE t.id=? AND t.branch_id=? AND (u.role='customer' OR u.role IS NULL) LIMIT 1");$q->execute([$id,$branchId]);return $q->fetch();}
$order=loadOrder($db,$id,$branchId);
if(!$order){http_response_code(404);$error='Order not found for your assigned branch.';}
if($order && $_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';
 try{
  if($action==='accept' && $order['status']==='pending'){$q=$db->prepare("UPDATE transactions SET status='preparing',processed_by=?,accepted_at=NOW() WHERE id=? AND branch_id=? AND status='pending'");$q->execute([$cashierId,$id,$branchId]);$success='Order accepted.';}
  elseif($action==='complete' && in_array($order['status'],['pending','preparing'],true)){
   $total=(float)$order['total_amount'];$method=$order['payment_method']==='gcash'?'gcash':'cash';$paid=$method==='cash'?(float)str_replace(',','',$_POST['amount_paid']??0):$total;$change=round($paid-$total,2);
   if($paid<$total) throw new RuntimeException('Cash received is less than the order total.');
   $fund=(float)($db->query("SELECT balance FROM cash_fund WHERE id=1")->fetchColumn()?:0);
   if($method==='cash' && $change>$fund) throw new RuntimeException('Not enough change in the drawer. Need ₱'.number_format($change,2).', available ₱'.number_format($fund,2).'.');
   $db->beginTransaction();
   $deliverySql = (($order['order_type'] ?? '') === 'delivery') ? ", delivery_status='preparing'" : '';
   $q=$db->prepare("UPDATE transactions SET status='completed',processed_by=?,amount_paid=?,change_amount=?,accepted_at=COALESCE(accepted_at,NOW()),completed_at=NOW()".$deliverySql." WHERE id=? AND branch_id=? AND status IN ('pending','preparing')");$q->execute([$cashierId,$paid,$change,$id,$branchId]);
   if($method==='cash' && $change>0)$db->prepare("UPDATE cash_fund SET balance=balance-? WHERE id=1")->execute([$change]);
   $db->commit();$_SESSION['flash_success']='Order #'.$id.' completed successfully.';header('Location: '.appUrl('customer/receipt.php?id='.$id));exit;
  }
  elseif($action==='cancel' && in_array($order['status'],['pending','preparing'],true)){
   $db->beginTransaction();$lines=$db->prepare("SELECT product_id,quantity FROM transaction_details WHERE transaction_id=?");$lines->execute([$id]);foreach($lines as $ln){$db->prepare("UPDATE products SET stock=stock+? WHERE id=?")->execute([(int)$ln['quantity'],(int)$ln['product_id']]);}$db->prepare("UPDATE transactions SET status='cancelled',processed_by=? WHERE id=? AND branch_id=?")->execute([$cashierId,$id,$branchId]);$db->commit();$_SESSION['flash_success']='Order #'.$id.' cancelled and stock restored.';header('Location: '.appUrl('staff/order_queue.php'));exit;
  }
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e->getMessage();}
 $order=loadOrder($db,$id,$branchId);
}
$items=[];if($order){$q=$db->prepare("SELECT td.*,p.name FROM transaction_details td LEFT JOIN products p ON p.id=td.product_id WHERE td.transaction_id=?");$q->execute([$id]);$items=$q->fetchAll();}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Process Order — Boba House POS</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"><link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?=appUrl('assets/css/custom.css')?>"></head><body class="pos-role-cashier"><div class="d-flex" style="min-height:100vh"><?php include __DIR__.'/../includes/sidebar.php';?><div id="main-content"><div class="topbar"><h1 class="page-title">Process Customer Order</h1><a class="btn btn-boba-outline" href="<?=appUrl('staff/order_queue.php')?>"><i class="bi bi-arrow-left"></i> Queue</a></div><div class="content-area">
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>
<?php if($order):?><div class="row g-4"><div class="col-lg-7"><div class="card"><div class="card-header d-flex justify-content-between"><span>Order #<?=(int)$order['id']?></span><span class="badge <?= $order['status']==='preparing'?'bg-info text-dark':'bg-warning text-dark' ?>"><?=ucfirst($order['status'])?></span></div><div class="card-body"><div class="row g-3 small mb-3"><div class="col-sm-6"><span class="text-muted">Customer</span><div class="fw-semibold"><?=htmlspecialchars($order['customer_name']?:'Customer')?></div></div><div class="col-sm-6"><span class="text-muted">Branch</span><div class="fw-semibold"><?=htmlspecialchars($order['branch_name']?:'—')?></div></div><div class="col-sm-6"><span class="text-muted">Order Type</span><div class="fw-semibold"><?=ucfirst($order['order_type'])?></div></div><div class="col-sm-6"><span class="text-muted">Payment Preference</span><div class="fw-semibold"><?=ucfirst($order['payment_method'])?></div></div></div>
<?php foreach($items as $it): $it['toppings']=json_decode($it['toppings']??'[]',true)?:[];$parts=customizationSummary($it);?><div class="d-flex justify-content-between border-top py-3"><div><strong><?=htmlspecialchars($it['name']?:'Product')?></strong><?php if($parts):?><div class="small text-muted"><?=htmlspecialchars(implode(' • ',$parts))?></div><?php endif;?></div><div class="text-end"><div><?=$it['quantity']?> × ₱<?=number_format((float)$it['unit_price'],2)?></div><strong>₱<?=number_format((float)$it['subtotal'],2)?></strong></div></div><?php endforeach;?>
<?php if($order['order_type']==='delivery'):?><div class="alert alert-info mt-3 mb-0"><i class="bi bi-truck me-2"></i><?=htmlspecialchars($order['delivery_address']?:'Delivery address not provided')?></div><?php endif;?></div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-end mb-4"><span class="text-muted">Order Total</span><strong class="fs-3">₱<?=number_format((float)$order['total_amount'],2)?></strong></div>
<?php if($order['status']==='pending'):?><form method="post" class="mb-3"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="accept"><button class="btn btn-outline-primary w-100"><i class="bi bi-hand-index-thumb me-1"></i>Accept & Prepare Order</button></form><?php endif;?>
<?php if(in_array($order['status'],['pending','preparing'],true)):?><form method="post" id="completeForm"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="complete">
<?php if($order['payment_method']==='cash'):?><label class="form-label fw-bold">Cash received from customer</label><div class="input-group mb-3"><span class="input-group-text">₱</span><input class="form-control" type="number" step="0.01" min="<?=htmlspecialchars($order['total_amount'])?>" name="amount_paid" required placeholder="0.00"></div><?php else:?><div class="alert alert-info"><i class="bi bi-phone me-2"></i>Confirm the customer's GCash payment before completing.</div><?php endif;?>
<button class="btn btn-success w-100 py-2"><i class="bi bi-check-circle me-1"></i>Payment Received — Complete Order</button></form><form method="post" class="mt-2" onsubmit="return confirm('Cancel this order and restore its stock?')"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger w-100">Cancel Order</button></form><?php else:?><div class="alert alert-secondary mb-0">This order is <?=htmlspecialchars($order['status'])?>.</div><?php endif;?></div></div></div></div><?php endif;?></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>