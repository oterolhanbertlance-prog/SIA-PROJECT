<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireSuperAdmin();
header('Content-Type: application/json');
$db = getDB();
$after = trim($_GET['after'] ?? '');
if ($after === '') $after = date('Y-m-d H:i:s', time() - 15);
$stmt = $db->prepare("SELECT id, delivery_name, delivery_address, rider_name, delivered_at, total_amount
                      FROM transactions
                      WHERE order_type='delivery' AND delivery_status='delivered'
                        AND delivered_at IS NOT NULL AND delivered_at > ?
                      ORDER BY delivered_at ASC LIMIT 10");
$stmt->execute([$after]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['ok'=>true,'server_time'=>date('Y-m-d H:i:s'),'deliveries'=>$rows]);
