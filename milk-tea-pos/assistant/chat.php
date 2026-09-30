<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/app_url.php';
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['answer'=>'Please log in first.']); exit; }
$payload=json_decode(file_get_contents('php://input'),true) ?: [];
$q=trim((string)($payload['message']??'')); if($q===''){echo json_encode(['answer'=>'Type a question about the Boba House system.']);exit;}
if(strlen($q)>500)$q=substr($q,0,500);
$role=$_SESSION['role']??''; $db=getDB();
function normq($s){$s=strtolower($s);$s=preg_replace('/[^a-z0-9\s]/',' ',$s);return preg_replace('/\s+/',' ',trim($s));}
function hasAny($q,$words){foreach($words as $w)if(strpos($q,$w)!==false)return true;return false;}
function scalarQ($db,$sql,$params=[],$default=0){try{$s=$db->prepare($sql);$s->execute($params);$v=$s->fetchColumn();return $v===false?$default:$v;}catch(Throwable $e){return $default;}}
function connectDb($name){try{return new PDO('mysql:host='.DB_HOST.';dbname='.$name.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}catch(Throwable $e){return null;}}
function peso($n){return '₱'.number_format((float)$n,2);}
$n=normq($q);$full=in_array($role,['super_admin'],true);$inventory=in_array($role,['super_admin','inventory_staff'],true);$cashier=in_array($role,['super_admin','cashier'],true);
$ans='';$quick=[];
if(hasAny($n,['report','reports','analytics'])){
 if($role==='super_admin'){$ans="POS reports available to Super Admin include sales/revenue summaries, transaction history, payment-method performance, product/category performance, branch/order activity, delivery status, and cash-fund monitoring.\n\nHRMS also has Employee, Applicant, Payroll, Leave, Department, Audit Log, Inventory, and Finance reports. The Reports pages use the live databases, so the figures change as records change.";$quick=['How many transactions today?','What HRMS reports are available?','What does payroll report show?'];}
 elseif($role==='inventory_staff'){$ans="Your inventory workspace focuses on Products, Categories, Ingredients, stock quantities and low-stock monitoring. Full sales/financial reports are restricted to Super Admin.";}
 elseif($role==='cashier'){$ans="Cashier can see Customer Orders and Sales Receipts for transactions handled by the cashier. Full business reports are restricted to Super Admin.";}
 else{$ans="Customers can view their own order history/receipts. Administrative reports are not available to customer accounts.";}
}
elseif(hasAny($n,['what can i do','system function','functions','modules','features','what does the system'])){
 $map=['super_admin'=>"Super Admin modules: Dashboard, Transactions, Reports, Deliveries, Branch Drivers, Branches, Cash Fund, Users & Roles, HR Applications, Time In/Out and the HRMS link. You manage the business but inventory editing stays with Inventory Staff.",'inventory_staff'=>"Inventory Staff modules: Products, Categories, Ingredients and Time In/Out. Your role maintains menu stock and ingredient availability.",'cashier'=>"Cashier modules: Customer Order Queue, Sales Receipts and Time In/Out. Customer orders for your assigned branch appear in your queue; you confirm/complete payment and hand off delivery orders.",'customer'=>"Customer modules: Menu, persistent Cart and My Orders. You choose a branch, send the order to that branch's cashier, and can review receipts/order status."];
 $ans=$map[$role]??'Your account has role-based access to the Boba House POS.';$quick=['How do branches work?','How does customer ordering work?','How does POS connect to HRMS?'];
}
elseif(hasAny($n,['branch','branches','location'])){
 $cnt=(int)scalarQ($db,"SELECT COUNT(*) FROM branches WHERE status='active'");$ans="There are currently {$cnt} active POS branch(es). Branches are shared with HRMS. Each branch can store a Philippine land-only GPS pin, auto-filled street/city address, staff assignment, drivers, and delivery settings. Orders, cashiers and drivers are branch-aware.";$quick=['How are drivers assigned?','How are branches synced with HRMS?','How many users are assigned?'];
}
elseif(hasAny($n,['transaction','sales','revenue','income'])){
 if(!$full&&$role!=='cashier'){$ans='Sales and transaction totals are restricted for your role.';}
 else{$count=(int)scalarQ($db,"SELECT COUNT(*) FROM transactions WHERE DATE(date_created)=CURDATE()");$sales=(float)scalarQ($db,"SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE DATE(date_created)=CURDATE() AND status='completed'");$ans="Today's POS has {$count} transaction/order record(s), with ".peso($sales)." in completed sales. Super Admin can inspect all transactions, filter by date/status, open details and process refunds. Cashiers see receipts for sales they handled.";$quick=['What reports are available?','How does refund work?','How does cashier order queue work?'];}
}
elseif(hasAny($n,['inventory','ingredient','ingredients','stock','product','products','category','categories'])){
 if(!$inventory){$ans='Inventory details are restricted to Inventory Staff and Super Admin.';}else{$p=(int)scalarQ($db,"SELECT COUNT(*) FROM products");$i=(int)scalarQ($db,"SELECT COUNT(*) FROM ingredients");$low=(int)scalarQ($db,"SELECT COUNT(*) FROM ingredients WHERE stock_quantity<=low_stock_threshold");$ans="Inventory currently contains {$p} product(s) and {$i} ingredient(s). {$low} ingredient(s) are at or below their low-stock threshold. Inventory Staff maintains Products, Categories and Ingredients; product availability/stock affects what customers can order.";$quick=['Which ingredients are low stock?','What can Inventory Staff do?','How does stock affect orders?'];}
}
elseif(hasAny($n,['delivery','deliveries','driver','drivers','logistics'])){
 $drivers=(int)scalarQ($db,"SELECT COUNT(*) FROM users WHERE role='driver' OR role='delivery_driver'");$ans="Deliveries are branch-based, not zone-based. The customer selects the fulfilling branch; the cashier confirms the order/payment first; then eligible drivers assigned to that same branch can handle the delivery. The system currently detects {$drivers} driver account(s) where supported by the user table.";$quick=['How do branches work?','What happens after cashier confirms an order?','How does delivery status work?'];
}
elseif(hasAny($n,['cashier','order queue','customer order','order process'])){
 $pending=(int)scalarQ($db,"SELECT COUNT(*) FROM transactions WHERE status IN ('pending','preparing')");$ans="Cashiers do not shop or pay as customers. Customer orders are sent to the selected branch and appear in that branch cashier's Customer Order Queue. The cashier opens the order, confirms payment, completes/cancels it, and delivery orders then move to the branch delivery workflow. There are {$pending} pending/preparing order(s) system-wide right now.";$quick=['How does customer ordering work?','How are cashiers assigned to a branch?','What are Sales Receipts?'];
}
elseif(hasAny($n,['cart','customer','ordering','order from customer'])){
 $ans="Customer flow: browse Menu → add products to the account-specific persistent Cart → choose branch and pickup/delivery → send the order to the cashier queue → track it in My Orders. The cart survives logout for the same customer and is revalidated against current product price/availability when restored.";$quick=['How does cashier order queue work?','Does cart remain after logout?','How do branches affect orders?'];
}
elseif(hasAny($n,['time in','time out','attendance','late','geofence','ncst','deduction'])){
 $ans="POS staff Time In/Out is shared with HRMS attendance. A time-in on either system should reflect on the other. Attendance uses the employee's assigned branch GPS/geofence; late minutes are based on the configured work start and outside-geofence records can contribute to payroll deductions in HRMS.";$quick=['How does payroll deduction work?','How is branch GPS used?','Does HRMS see POS time-in?'];
}
elseif(hasAny($n,['user','users','role','roles','hierarchy','admin'])){
 $ans="POS role hierarchy is separated by responsibility: Super Admin manages business/branches/users/reports/logistics; Inventory Staff manages products/categories/ingredients; Cashier handles branch customer orders and receipts; Customer places orders; drivers handle branch deliveries. Accounts can be assigned to branches so permissions and operational data stay branch-aware.";$quick=['What can Super Admin do?','What can Cashier do?','What can Inventory Staff do?'];
}
elseif(hasAny($n,['hrms','human resource','applicant','job','payroll','leave','employee','interview','contract','recruitment'])){
 if(!$full){$ans='POS staff can use the HRMS-integrated attendance and branch/role setup, while detailed HR records are restricted to HRMS-authorized users.';}
 else{$h=connectDb('hrms');$emp=$h?(int)scalarQ($h,"SELECT COUNT(*) FROM employees WHERE status='active'"):0;$apps=$h?(int)scalarQ($h,"SELECT COUNT(*) FROM applicants"):0;$jobs=$h?(int)scalarQ($h,"SELECT COUNT(*) FROM job_postings WHERE status='open'"):0;$ans="HRMS manages recruitment, applicants, interviews, deployment/contracts, employees, attendance, payroll, leave, reports, branches and user access. Current linked HRMS data: {$emp} active employee(s), {$apps} applicant(s), and {$jobs} open job posting(s). POS branches and shared attendance are integrated with HRMS.";$quick=['What reports are available in HRMS?','How does recruitment flow work?','How does attendance affect payroll?'];}
}
elseif(hasAny($n,['dark mode','theme'])){$ans='The POS includes a saved light/dark theme toggle. The preference is stored in the browser and applies to authenticated staff/customer workspaces.';}
else{$ans="I can answer questions about POS modules, reports, branches, transactions, customer orders, cashier workflow, inventory, deliveries, users/roles, attendance, and the HRMS integration. Try asking in a full sentence, for example: “What reports are available in HRMS and POS?”";$quick=['What reports are available?','What can I do in this system?','How does POS connect to HRMS?'];}
echo json_encode(['answer'=>$ans,'quick_replies'=>$quick],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
