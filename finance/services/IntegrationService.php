<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

/**
 * Read-only bridge to the supplied HRMS and Milk Tea POS databases.
 * The service only writes to the Finance database.
 */
final class IntegrationService
{
    public function __construct(
        private readonly PDO $finance,
        private readonly ?PDO $hrms = null,
        private readonly ?PDO $pos = null,
    ) {
    }

    public function summary(): array
    {
        $summary = [
            'hrms' => [
                'connection' => $this->connectionState($this->hrms),
                'employees_available' => 0,
                'payroll_available' => 0,
                'employees_imported' => $this->count('employee_snapshots'),
                'payroll_imported' => $this->count('payroll_snapshots'),
                'last_sync' => $this->lastSync('hrms'),
            ],
            'pos' => [
                'connection' => $this->connectionState($this->pos),
                'transactions_available' => 0,
                'sales_total' => 0,
                'transactions_imported' => $this->count('sales_snapshots'),
                'transactions_pending' => 0,
                'last_sync' => $this->lastSync('pos'),
            ],
        ];
        if ($this->hrms) {
            try {
                $summary['hrms']['employees_available'] = (int) $this->hrms->query(
                    "SELECT COUNT(*) FROM employees WHERE status = 'active'"
                )->fetchColumn();
                $summary['hrms']['payroll_available'] = (int) $this->hrms->query(
                    'SELECT COUNT(*) FROM payroll'
                )->fetchColumn();
            } catch (Throwable) {
                // The card still clearly reports the failed connection.
            }
        }
        if ($this->pos) {
            try {
                $summary['pos']['transactions_available'] = (int) $this->pos->query(
                    "SELECT COUNT(*) FROM transactions WHERE status = 'completed'"
                )->fetchColumn();
                $summary['pos']['sales_total'] = (float) $this->pos->query(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE status = 'completed'"
                )->fetchColumn();
                $summary['pos']['transactions_pending'] = max(
                    0,
                    $summary['pos']['transactions_available'] - $summary['pos']['transactions_imported']
                );
            } catch (Throwable) {
                // The card still clearly reports the failed connection.
            }
        }
        return $summary;
    }

    public function syncEverything(int $userId, ?string $from = null, ?string $to = null): array
    {
        $branches = $this->syncBranches($userId);
        $accounts = $this->syncAccounts($userId);
        return [
            'branches' => $branches,
            'accounts' => $accounts,
            'hrms' => $this->syncHrms($userId, $from, $to),
            'pos' => $this->syncPos($userId, $from, $to),
        ];
    }

    public function syncBranches(int $userId): array
    {
        ensure_connected_system_schema();
        $counts=['imported'=>0,'updated'=>0,'failed'=>0];
        $f=$this->finance;
        try{
            // HRMS is the canonical branch directory when available.
            if($this->hrms){
                $rows=$this->hrms->query("SELECT * FROM branches ORDER BY id")->fetchAll();
                foreach($rows as $b){
                    $posId=(int)($b['pos_branch_id']??0);
                    $q=$f->prepare("SELECT id FROM branches WHERE hrms_branch_id=? OR (? > 0 AND pos_branch_id=?) OR (branch_code IS NOT NULL AND branch_code=?) LIMIT 1");
                    $q->execute([(int)$b['id'],$posId,$posId,$b['branch_code']??'']);$id=(int)$q->fetchColumn();
                    $data=[$b['branch_code']??null,$b['name']??('Branch '.$b['id']),$b['address']??null,$b['city']??null,$b['latitude']??null,$b['longitude']??null,$b['phone']??null,$b['manager_name']??null,(($b['status']??'active')==='active'?1:0),(int)$b['id'],$posId?:null];
                    if($id){$f->prepare("UPDATE branches SET branch_code=?,name=?,address=?,city=?,latitude=?,longitude=?,phone=?,manager_name=?,is_active=?,hrms_branch_id=?,pos_branch_id=COALESCE(?,pos_branch_id) WHERE id=?")->execute([...$data,$id]);$counts['updated']++;}
                    else{$f->prepare("INSERT INTO branches(branch_code,name,address,city,latitude,longitude,phone,manager_name,is_active,hrms_branch_id,pos_branch_id,source_system,source_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,'hrms',?)")->execute([...$data,'branch:'.$b['id']]);$counts['imported']++;}
                }
            }
            if($this->pos){
                $rows=$this->pos->query("SELECT * FROM branches ORDER BY id")->fetchAll();
                foreach($rows as $b){
                    $q=$f->prepare("SELECT id FROM branches WHERE pos_branch_id=? OR (branch_code IS NOT NULL AND branch_code=?) OR LOWER(name)=LOWER(?) LIMIT 1");
                    $q->execute([(int)$b['id'],$b['branch_code']??'',$b['name']??'']);$id=(int)$q->fetchColumn();
                    if($id){$f->prepare("UPDATE branches SET pos_branch_id=?,branch_code=COALESCE(NULLIF(?,''),branch_code),name=?,address=COALESCE(NULLIF(?,''),address),city=COALESCE(NULLIF(?,''),city),latitude=COALESCE(?,latitude),longitude=COALESCE(?,longitude),phone=COALESCE(NULLIF(?,''),phone),manager_name=COALESCE(NULLIF(?,''),manager_name),is_active=? WHERE id=?")
                      ->execute([(int)$b['id'],$b['branch_code']??'',$b['name']??('Branch '.$b['id']),$b['address']??'',$b['city']??'',$b['latitude']??null,$b['longitude']??null,$b['phone']??'',$b['manager_name']??'',(($b['status']??'active')==='active'?1:0),$id]);$counts['updated']++;}
                    else{$f->prepare("INSERT INTO branches(branch_code,name,address,city,latitude,longitude,phone,manager_name,is_active,pos_branch_id,source_system,source_id) VALUES(?,?,?,?,?,?,?,?,?,?,'pos',?)")
                      ->execute([$b['branch_code']??null,$b['name']??('Branch '.$b['id']),$b['address']??null,$b['city']??null,$b['latitude']??null,$b['longitude']??null,$b['phone']??null,$b['manager_name']??null,(($b['status']??'active')==='active'?1:0),(int)$b['id'],'branch:'.$b['id']]);$counts['imported']++;}
                }
            }
        }catch(Throwable $e){$counts['failed']++;$counts['message']=$e->getMessage();}
        return $counts;
    }

    public function syncAccounts(int $userId): array
    {
        ensure_connected_system_schema();$counts=['imported'=>0,'skipped'=>0,'failed'=>0];
        foreach(['hrms'=>$this->hrms,'pos'=>$this->pos] as $system=>$db){
            if(!$db) continue;
            try{
                $sql=$system==='hrms'
                  ?"SELECT * FROM users WHERE status='active' AND role IN ('admin','hr','employee')"
                  :"SELECT * FROM users WHERE is_active=1 AND role IN ('super_admin','inventory_staff','cashier')";
                foreach($db->query($sql)->fetchAll() as $u){
                    $before=$this->finance->prepare("SELECT finance_user_id FROM external_identities WHERE source_system=? AND source_user_id=?");
                    $before->execute([$system,(int)$u['id']]);$had=(bool)$before->fetchColumn();
                    upsert_external_finance_user($system,$u);$had?$counts['skipped']++:$counts['imported']++;
                }
            }catch(Throwable){$counts['failed']++;}
        }
        return $counts;
    }

    public function syncHrms(int $userId, ?string $from = null, ?string $to = null): array
    {
        $result = [
            'employees' => $this->syncEmployees($userId),
            'payroll' => $this->syncPayroll($userId, $from, $to),
        ];
        return $result;
    }

    public function syncPos(int $userId, ?string $from = null, ?string $to = null): array
    {
        return ['sales' => $this->syncSales($userId, $from, $to)];
    }

    private function syncEmployees(int $userId): array
    {
        $logId = $this->startLog('hrms', 'employees', $userId);
        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        try {
            if (!$this->hrms) {
                throw new RuntimeException('HRMS connection is not available.');
            }
            $stmt = $this->hrms->query(
                'SELECT e.id, e.employee_id, e.firstname, e.middlename, e.lastname,
                        e.employment_status, e.basic_salary, e.updated_at,
                        d.name AS department_name, p.title AS position_title
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 LEFT JOIN positions p ON p.id = e.position_id
                 ORDER BY e.lastname, e.firstname'
            );
            $upsert = $this->finance->prepare(
                'INSERT INTO employee_snapshots
                    (source_id, employee_code, full_name, department_name, position_title,
                     employment_status, basic_salary, source_updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    employee_code = VALUES(employee_code), full_name = VALUES(full_name),
                    department_name = VALUES(department_name), position_title = VALUES(position_title),
                    employment_status = VALUES(employment_status), basic_salary = VALUES(basic_salary),
                    source_updated_at = VALUES(source_updated_at), synced_at = CURRENT_TIMESTAMP'
            );
            while ($row = $stmt->fetch()) {
                try {
                    $upsert->execute([
                        'employee:' . $row['id'],
                        $row['employee_id'],
                        trim($row['firstname'] . ' ' . ($row['middlename'] ?? '') . ' ' . $row['lastname']),
                        $row['department_name'],
                        $row['position_title'],
                        $row['employment_status'],
                        $row['basic_salary'] ?? 0,
                        $row['updated_at'],
                    ]);
                    $mapping = $this->mapping('hrms', 'employee:' . $row['id'], 'employee');
                    if ($mapping && $mapping['status'] === 'imported') {
                        $counts['skipped']++;
                    } else {
                        $counts['imported']++;
                    }
                    $this->upsertMapping('hrms', 'employee:' . $row['id'], 'employee', null, 'imported');
                } catch (Throwable) {
                    $counts['failed']++;
                }
            }
        } catch (Throwable $error) {
            $counts['failed']++;
            $this->finishLog($logId, $counts, format_error($error));
            return $counts;
        }
        $this->finishLog($logId, $counts, 'Employee snapshot synchronized.');
        return $counts;
    }

    private function syncPayroll(int $userId, ?string $from, ?string $to): array
    {
        $logId = $this->startLog('hrms', 'payroll', $userId);
        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        try {
            if (!$this->hrms) {
                throw new RuntimeException('HRMS connection is not available.');
            }
            $where = [];
            $params = [];
            if ($from) {
                $where[] = "DATE(CONCAT(payroll_year, '-', LPAD(payroll_month, 2, '0'), '-01')) >= ?";
                $params[] = $from;
            }
            if ($to) {
                $where[] = "DATE(CONCAT(payroll_year, '-', LPAD(payroll_month, 2, '0'), '-01')) <= ?";
                $params[] = $to;
            }
            $sql = 'SELECT p.*, e.employee_id AS employee_code,
                           CONCAT(e.firstname, " ", e.lastname) AS employee_name
                    FROM payroll p JOIN employees e ON e.id = p.employee_id';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY p.payroll_year DESC, p.payroll_month DESC, p.id DESC';
            $stmt = $this->hrms->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                $sourceId = 'payroll:' . $row['id'];
                $mapping = $this->mapping('hrms', $sourceId, 'payroll');
                if ($mapping && $mapping['status'] === 'imported') {
                    $counts['skipped']++;
                    continue;
                }
                try {
                    $this->finance->beginTransaction();
                    $gross = (float) $row['gross_pay'];
                    $deductions = [
                        '2200' => (float) $row['sss_deduction'],
                        '2210' => (float) $row['philhealth_deduction'],
                        '2220' => (float) $row['pagibig_deduction'],
                        '2230' => (float) $row['tax_deduction'],
                        '2300' => (float) $row['other_deductions'] + (float) $row['gsis_deduction'],
                    ];
                    $lines = [['code' => '5100', 'debit' => $gross, 'credit' => 0, 'label' => 'Payroll expense']];
                    foreach ($deductions as $code => $amount) {
                        if ($amount > 0) {
                            $lines[] = ['code' => $code, 'debit' => 0, 'credit' => $amount, 'label' => 'Payroll deduction'];
                        }
                    }
                    $lines[] = ['code' => '2100', 'debit' => 0, 'credit' => (float) $row['net_pay'], 'label' => 'Net payroll payable'];
                    $journalId = $this->writeJournal(
                        date('Y-m-d', strtotime(sprintf('%04d-%02d-01', $row['payroll_year'], $row['payroll_month']))),
                        'HRMS payroll · ' . ($row['payroll_code'] ?: $sourceId) . ' · ' . $row['employee_name'],
                        'hrms',
                        $sourceId,
                        $lines,
                        $userId
                    );
                    $insert = $this->finance->prepare(
                        'INSERT INTO payroll_snapshots
                         (source_id, employee_source_id, payroll_code, payroll_month, payroll_year,
                          gross_pay, total_deductions, net_pay, status, journal_entry_id, source_updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $sourceId,
                        'employee:' . $row['employee_id'],
                        $row['payroll_code'],
                        $row['payroll_month'],
                        $row['payroll_year'],
                        $row['gross_pay'],
                        $row['total_deductions'],
                        $row['net_pay'],
                        $row['status'],
                        $journalId,
                        $row['updated_at'],
                    ]);
                    $this->upsertMapping('hrms', $sourceId, 'payroll', $journalId, 'imported');
                    $this->finance->commit();
                    $counts['imported']++;
                } catch (Throwable) {
                    if ($this->finance->inTransaction()) {
                        $this->finance->rollBack();
                    }
                    $this->upsertMapping('hrms', $sourceId, 'payroll', null, 'failed', 'Payroll could not be balanced or imported.');
                    $counts['failed']++;
                }
            }
        } catch (Throwable $error) {
            $counts['failed']++;
            $this->finishLog($logId, $counts, format_error($error));
            return $counts;
        }
        $this->finishLog($logId, $counts, 'Payroll snapshot and balanced journals synchronized.');
        return $counts;
    }

    private function syncSales(int $userId, ?string $from, ?string $to): array
    {
        $logId = $this->startLog('pos', 'sales', $userId);
        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        try {
            if (!$this->pos) {
                throw new RuntimeException('Milk Tea POS connection is not available.');
            }
            $conditions = ["t.status = 'completed'"];
            $params = [];
            if ($from) {
                $conditions[] = 'DATE(t.date_created) >= ?';
                $params[] = $from;
            }
            if ($to) {
                $conditions[] = 'DATE(t.date_created) <= ?';
                $params[] = $to;
            }
            $stmt = $this->pos->prepare(
                'SELECT t.* FROM transactions t WHERE ' . implode(' AND ', $conditions) . ' ORDER BY t.date_created DESC, t.id DESC'
            );
            $stmt->execute($params);
            while ($transaction = $stmt->fetch()) {
                $sourceId = 'transaction:' . $transaction['id'];
                $mapping = $this->mapping('pos', $sourceId, 'sale');
                if ($mapping && $mapping['status'] === 'imported') {
                    $counts['skipped']++;
                    continue;
                }
                try {
                    $this->finance->beginTransaction();
                    $payment = strtolower((string) $transaction['payment_method']);
                    $debitCode = str_contains($payment, 'cash') ? '1000' : (str_contains($payment, 'gcash') || str_contains($payment, 'wallet') ? '1020' : '1010');
                    $journalId = $this->writeJournal(
                        date('Y-m-d', strtotime((string) $transaction['date_created'])),
                        'Milk Tea POS sale · #' . $transaction['id'],
                        'pos',
                        $sourceId,
                        [
                            ['code' => $debitCode, 'debit' => (float) $transaction['total_amount'], 'credit' => 0, 'label' => ucfirst($payment) . ' receipt'],
                            ['code' => '4000', 'debit' => 0, 'credit' => (float) $transaction['total_amount'], 'label' => 'Sales revenue'],
                        ],
                        $userId
                    );
                    $insert = $this->finance->prepare(
                        'INSERT INTO sales_snapshots
                         (source_id, transaction_date, total_amount, payment_method, status, order_type, journal_entry_id, source_updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $sourceId,
                        $transaction['date_created'],
                        $transaction['total_amount'],
                        $transaction['payment_method'],
                        $transaction['status'],
                        $transaction['order_type'],
                        $journalId,
                        $transaction['date_created'],
                    ]);
                    $saleId = (int) $this->finance->lastInsertId();
                    $details = $this->pos->prepare(
                        'SELECT td.*, p.name AS product_name, c.name AS category_name
                         FROM transaction_details td
                         LEFT JOIN products p ON p.id = td.product_id
                         LEFT JOIN categories c ON c.id = p.category_id
                         WHERE td.transaction_id = ?'
                    );
                    $details->execute([(int) $transaction['id']]);
                    $item = $this->finance->prepare(
                        'INSERT INTO sales_items
                         (sale_snapshot_id, source_detail_id, product_name, category_name, quantity, unit_price, subtotal)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    while ($detail = $details->fetch()) {
                        $item->execute([
                            $saleId,
                            'transaction_detail:' . $detail['id'],
                            $detail['product_name'],
                            $detail['category_name'],
                            $detail['quantity'],
                            $detail['unit_price'],
                            $detail['subtotal'],
                        ]);
                    }
                    $this->upsertMapping('pos', $sourceId, 'sale', $journalId, 'imported');
                    $this->finance->commit();
                    $counts['imported']++;
                } catch (Throwable) {
                    if ($this->finance->inTransaction()) {
                        $this->finance->rollBack();
                    }
                    $this->upsertMapping('pos', $sourceId, 'sale', null, 'failed', 'Sale could not be imported.');
                    $counts['failed']++;
                }
            }
        } catch (Throwable $error) {
            $counts['failed']++;
            $this->finishLog($logId, $counts, format_error($error));
            return $counts;
        }
        $this->finishLog($logId, $counts, 'POS sales and balanced journals synchronized.');
        return $counts;
    }

    private function writeJournal(string $date, string $description, string $sourceSystem, string $sourceId, array $lines, int $userId): int
    {
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if ($debit <= 0 || abs($debit - $credit) > 0.005) {
            throw new RuntimeException('Unbalanced journal rejected. Debit: ' . $debit . ', credit: ' . $credit);
        }
        $number = strtoupper(substr($sourceSystem, 0, 3)) . '-' . date('YmdHis') . '-' . random_int(100, 999);
        $entry = $this->finance->prepare(
            'INSERT INTO journal_entries
             (journal_number, transaction_date, description, source_system, source_id, entry_type, status,
              total_debit, total_credit, created_by, posted_at)
             VALUES (?, ?, ?, ?, ?, "imported", "posted", ?, ?, ?, NOW())'
        );
        $entry->execute([$number, $date, $description, $sourceSystem, $sourceId, $debit, $credit, $userId]);
        $entryId = (int) $this->finance->lastInsertId();
        $line = $this->finance->prepare(
            'INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($lines as $value) {
            $line->execute([
                $entryId,
                account_id((string) $value['code']),
                $value['label'] ?? null,
                $value['debit'],
                $value['credit'],
            ]);
        }
        audit('import', 'integration', 'journal_entry', (string) $entryId, null, ['source_system' => $sourceSystem, 'source_id' => $sourceId]);
        return $entryId;
    }

    private function mapping(string $system, string $sourceId, string $entity): ?array
    {
        $stmt = $this->finance->prepare(
            'SELECT * FROM integration_mappings WHERE source_system = ? AND source_id = ? AND entity_type = ? LIMIT 1'
        );
        $stmt->execute([$system, $sourceId, $entity]);
        return $stmt->fetch() ?: null;
    }

    private function upsertMapping(string $system, string $sourceId, string $entity, ?int $localId, string $status, ?string $error = null): void
    {
        $stmt = $this->finance->prepare(
            'INSERT INTO integration_mappings (source_system, source_id, entity_type, local_id, status, last_error)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE local_id = VALUES(local_id), status = VALUES(status),
             last_error = VALUES(last_error), last_synced_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$system, $sourceId, $entity, $localId, $status, $error]);
    }

    private function startLog(string $system, string $action, int $userId): int
    {
        $stmt = $this->finance->prepare(
            'INSERT INTO integration_logs (source_system, action, started_at, created_by) VALUES (?, ?, NOW(), ?)'
        );
        $stmt->execute([$system, $action, $userId]);
        return (int) $this->finance->lastInsertId();
    }

    private function finishLog(int $id, array $counts, string $message): void
    {
        $stmt = $this->finance->prepare(
            'UPDATE integration_logs SET finished_at = NOW(), imported_count = ?, skipped_count = ?, failed_count = ?, message = ?
             WHERE id = ?'
        );
        $stmt->execute([$counts['imported'], $counts['skipped'], $counts['failed'], $message, $id]);
    }

    private function count(string $table): int
    {
        try {
            return (int) $this->finance->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function lastSync(string $system): ?string
    {
        try {
            $stmt = $this->finance->prepare(
                'SELECT MAX(finished_at) FROM integration_logs WHERE source_system = ? AND failed_count = 0'
            );
            $stmt->execute([$system]);
            return $stmt->fetchColumn() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function connectionState(?PDO $pdo): string
    {
        return $pdo ? 'Connected' : 'Not configured';
    }
}