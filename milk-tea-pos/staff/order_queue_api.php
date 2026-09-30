<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/order_queue.php';
if(empty($_SESSION['user_id']) || ($_SESSION['role']??'')!=='cashier'){http_response_code(403);echo json_encode(['ok'=>false]);exit;}
$db=getDB();ensureOrderQueueSchema($db);$branchId=cashierBranchId($db,(int)$_SESSION['user_id']);
if(!$branchId){echo json_encode(['ok'=>true,'count'=>0]);exit;}
$q=$db->prepare("SELECT COUNT(*) FROM transactions t LEFT JOIN users u ON u.id=t.user_id WHERE t.branch_id=? AND t.status IN ('pending','preparing') AND (u.role='customer' OR u.role IS NULL)");$q->execute([$branchId]);
echo json_encode(['ok'=>true,'count'=>(int)$q->fetchColumn()]);
