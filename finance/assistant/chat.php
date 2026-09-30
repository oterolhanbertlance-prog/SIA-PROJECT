<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || !current_user()) {
    http_response_code(401);
    echo json_encode(['answer'=>'Please log in to Finance first.']);
    exit;
}
$payload=json_decode(file_get_contents('php://input'),true) ?: [];
$q=trim((string)($payload['message']??''));
if($q===''){echo json_encode(['answer'=>'Type a question about the Boba House Finance system.']);exit;}
if(strlen($q)>500)$q=substr($q,0,500);

function fq_norm(string $s): string {$s=strtolower($s);$s=preg_replace('/[^a-z0-9\s]/',' ',$s);return preg_replace('/\s+/',' ',trim($s));}
function fq_has(string $q,array $words): bool {foreach($words as $w)if(strpos($q,$w)!==false)return true;return false;}
function fq_scalar(PDO $db,string $sql,array $params=[],mixed $default=0): mixed {try{$s=$db->prepare($sql);$s->execute($params);$v=$s->fetchColumn();return $v===false?$default:$v;}catch(Throwable){return $default;}}
function fq_money(mixed $n): string {return '₱'.number_format((float)$n,2);}

$db=db_finance(); $u=current_user(); $role=(string)($u['role_name']??'');
$n=fq_norm($q); $ans=''; $quick=[];

if(fq_has($n,['what can i do','finance function','finance functions','modules','features','what does finance'])){
    $ans="Finance modules include Dashboard, HRMS/POS Integration, Branch Mappings, Sales, Payroll, Attendance (when permitted), Journals, Reports, Chart of Accounts, Settings, and administrative tools based on your role. Finance receives operational data from POS and HRMS, then keeps finance-side snapshots and accounting records.";
    $quick=['How does Finance connect to HRMS and POS?',"How much are today's sales?",'What are journals?'];
}
elseif(fq_has($n,['integration','connect to hrms','connect to pos','hrms and pos','sync','synchronization'])){
    $emp=(int)fq_scalar($db,"SELECT COUNT(*) FROM employee_snapshots");
    $sales=(int)fq_scalar($db,"SELECT COUNT(*) FROM sales_snapshots");
    $pay=(int)fq_scalar($db,"SELECT COUNT(*) FROM payroll_snapshots");
    $ans="Finance is connected to HRMS and POS through the Integration service. HRMS supplies employee/payroll-related records and POS supplies sales/transaction records. Branches and authorized staff identities are also mapped into Finance. Finance currently has {$emp} employee snapshot(s), {$pay} payroll snapshot(s), and {$sales} sales snapshot(s).";
    $quick=['How many active branches are there?','What is a snapshot?','What can I do in Finance?'];
}
elseif(fq_has($n,['today sales',"today's sales",'sales today','revenue today','income today'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM sales_snapshots WHERE DATE(transaction_date)=CURDATE() AND status='completed'");
    $total=(float)fq_scalar($db,"SELECT COALESCE(SUM(total_amount),0) FROM sales_snapshots WHERE DATE(transaction_date)=CURDATE() AND status='completed'");
    $ans="Today Finance has {$count} completed POS sale(s) totaling ".fq_money($total).". These figures come from the Finance sales snapshots synchronized from POS.";
    $quick=['How does sales synchronization work?','What reports are available?','How many active branches are there?'];
}
elseif(fq_has($n,['sales','revenue','transactions'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM sales_snapshots WHERE status='completed'");
    $total=(float)fq_scalar($db,"SELECT COALESCE(SUM(total_amount),0) FROM sales_snapshots WHERE status='completed'");
    $ans="Finance currently stores {$count} completed sales snapshot(s), totaling ".fq_money($total).". Use the Sales page for detailed synchronized POS transactions and the Reports page for summaries.";
    $quick=["How much are today's sales?",'What reports are available?','How does Finance connect to HRMS and POS?'];
}
elseif(fq_has($n,['payroll','salary','salaries','deduction','deductions'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM payroll_snapshots");
    $gross=(float)fq_scalar($db,"SELECT COALESCE(SUM(gross_pay),0) FROM payroll_snapshots");
    $ded=(float)fq_scalar($db,"SELECT COALESCE(SUM(total_deductions),0) FROM payroll_snapshots");
    $net=(float)fq_scalar($db,"SELECT COALESCE(SUM(net_pay),0) FROM payroll_snapshots");
    $ans="Finance currently has {$count} payroll snapshot(s): gross ".fq_money($gross).", deductions ".fq_money($ded).", and net pay ".fq_money($net).". Payroll originates from HRMS and is synchronized into Finance for financial reporting/accounting.";
    $quick=['How does attendance affect payroll?','How does Finance connect to HRMS and POS?','What are journals?'];
}
elseif(fq_has($n,['attendance','time in','time out','late','gps'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM attendance_logs WHERE DATE(recorded_at)=CURDATE()");
    $ans="Finance can use synchronized attendance/GPS records for finance and payroll-related review when your role has permission. There are {$count} Finance attendance log record(s) recorded today. HRMS remains the main employee/payroll source while Finance uses the synchronized records for financial workflows.";
    $quick=['How does payroll work?','How many active branches are there?','What can I do in Finance?'];
}
elseif(fq_has($n,['branch','branches','mapping','mappings'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM branches WHERE is_active=1");
    $mapped=(int)fq_scalar($db,"SELECT COUNT(*) FROM branches WHERE hrms_branch_id IS NOT NULL OR pos_branch_id IS NOT NULL");
    $ans="Finance currently has {$count} active branch(es), with {$mapped} branch record(s) linked to HRMS and/or POS IDs. The Branch Mappings page lets Finance keep branch-level sales, employees, payroll and accounting records aligned across the three systems.";
    $quick=['How does Finance connect to HRMS and POS?','What are branch mappings?',"How much are today's sales?"];
}
elseif(fq_has($n,['journal','journals','debit','credit','accounting entry'])){
    $posted=(int)fq_scalar($db,"SELECT COUNT(*) FROM journal_entries WHERE status='posted'");
    $draft=(int)fq_scalar($db,"SELECT COUNT(*) FROM journal_entries WHERE status='draft'");
    $ans="Journals are Finance accounting entries with balanced debit and credit lines. They may be manual, imported, adjusting, reversing or recurring. Finance currently has {$posted} posted journal(s) and {$draft} draft journal(s).";
    $quick=['What is the Chart of Accounts?','What reports are available?','How does POS sales become Finance data?'];
}
elseif(fq_has($n,['chart of accounts','accounts','account code'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM accounts WHERE is_active=1");
    $ans="The Chart of Accounts contains the accounting accounts used by journal entries and financial reporting. There are currently {$count} active account(s). Use Chart of Accounts to review account codes, names, groups and active status.";
    $quick=['What are journals?','What reports are available?','What can I do in Finance?'];
}
elseif(fq_has($n,['report','reports','analytics'])){
    $ans="Finance Reports use synchronized and finance-side records to summarize sales, payroll and accounting activity. For transaction-level details use Sales or Payroll; for accounting entries use Journals; for source-system health and synchronization use Integration.";
    $quick=["How much are today's sales?",'How does Finance connect to HRMS and POS?','What are journals?'];
}
elseif(fq_has($n,['employee','employees','staff'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM employee_snapshots");
    $ans="Finance currently has {$count} synchronized employee snapshot(s) from HRMS. Finance uses these records for payroll/financial context; employee management itself remains in HRMS.";
    $quick=['How does payroll work?','How does Finance connect to HRMS and POS?','How many active branches are there?'];
}
elseif(fq_has($n,['user','users','role','roles','login','account'])){
    $count=(int)fq_scalar($db,"SELECT COUNT(*) FROM users WHERE is_active=1");
    $ans="Finance currently has {$count} active Finance profile(s). Finance can also accept authorized HRMS/POS staff credentials through connected identities. Their Finance permissions are mapped from their source-system roles; customer POS accounts are not granted Finance access.";
    $quick=['How does Finance connect to HRMS and POS?','What can I do in Finance?','How many active branches are there?'];
}
elseif(fq_has($n,['dark mode','theme','color'])){
    $ans="Finance uses the same Boba House blue visual theme as the current HRMS and POS, including the shared light/dark palette. Use the moon button in the top bar to switch themes.";
}
else{
    $ans="I can help with Finance modules, live sales totals, payroll snapshots, employees, branches, journals, Chart of Accounts, reports, attendance, user access, and HRMS/POS integration. Try asking “How much are today’s sales?” or “How does Finance connect to HRMS and POS?”";
    $quick=['What can I do in Finance?',"How much are today's sales?",'How does Finance connect to HRMS and POS?'];
}
echo json_encode(['answer'=>$ans,'quick_replies'=>$quick],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
