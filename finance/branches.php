<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/services/IntegrationService.php';
ensure_connected_system_schema();
$user=require_login();$title='Branches & Mappings';$activePage='branches';$db=db_finance();
if(is_post()){verify_csrf();$svc=new IntegrationService($db,db_hrms(),db_pos());$r=$svc->syncBranches((int)$user['id']);flash('success',"Branch sync complete: {$r['imported']} added, {$r['updated']} updated, {$r['failed']} failed.");redirect('branches.php');}
$rows=$db->query("SELECT b.*,
 (SELECT COUNT(*) FROM sales_snapshots s WHERE s.branch_id=b.id) sales_count,
 (SELECT COALESCE(SUM(total_amount),0) FROM sales_snapshots s WHERE s.branch_id=b.id) sales_total,
 (SELECT COUNT(*) FROM employee_snapshots e WHERE e.branch_id=b.id) employee_count
 FROM branches b ORDER BY b.is_active DESC,b.name")->fetchAll();
require __DIR__.'/includes/header.php';
?>
<section class="hero-banner compact-banner"><div><div class="eyebrow">THREE-SYSTEM BRANCH DIRECTORY</div><h2>Branches &amp; mappings</h2><p>One Finance branch links the matching HRMS and POS branch, including GPS coordinates, employees, payroll and sales.</p></div>
<form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="btn btn-light"><span class="bi bi-arrow-repeat"></span> Sync branches</button></form></section>
<?php if($m=flash('success')):?><div class="alert alert-success"><?=e($m)?></div><?php endif;?>
<section class="panel-card mt-4"><div class="panel-heading"><div><div class="eyebrow">SOURCE MAPPING</div><h3>Connected branches</h3></div><span class="subtle-label"><?=count($rows)?> mapped</span></div>
<div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Branch</th><th>Location</th><th>HRMS ID</th><th>POS ID</th><th>GPS</th><th>Employees</th><th>Sales</th><th>Status</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="8"><div class="empty-state">No branches yet. Run Sync branches.</div></td></tr><?php endif;?>
<?php foreach($rows as $b):?><tr>
<td><strong><?=e($b['name'])?></strong><br><small><?=e($b['branch_code']?:'—')?></small></td>
<td><?=e($b['address']?:'—')?><?=!empty($b['city'])?'<br><small>'.e($b['city']).'</small>':''?></td>
<td><?=e($b['hrms_branch_id']?:'—')?></td><td><?=e($b['pos_branch_id']?:'—')?></td>
<td><?=($b['latitude']!==null&&$b['longitude']!==null)?e($b['latitude'].', '.$b['longitude']):'—'?></td>
<td><?=number_format((int)$b['employee_count'])?></td><td><?=money($b['sales_total'])?> <small>(<?=number_format((int)$b['sales_count'])?>)</small></td>
<td><span class="status-badge <?=$b['is_active']?'success':''?>"><?=$b['is_active']?'Active':'Inactive'?></span></td>
</tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/includes/footer.php';?>