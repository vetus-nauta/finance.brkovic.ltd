<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/v2/Repository.php';

function e2eAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function e2eAssertAmount(float|int|string|null $actual, float $expected, string $message): void
{
    e2eAssert($actual !== null, "{$message}: got null, expected {$expected}");
    $actualFloat = (float)$actual;
    if (abs($actualFloat - $expected) > 0.004) {
        throw new RuntimeException("{$message}: got {$actualFloat}, expected {$expected}");
    }
}

function e2eEnsureUser(PDO $db, string $email, string $displayName): int
{
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        $db->prepare("
            UPDATE users
            SET display_name = ?, status = 'active', updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$displayName, $existing]);

        return (int)$existing;
    }

    $db->prepare("
        INSERT INTO users (email, display_name, preferred_language, timezone, status, last_login_at)
        VALUES (?, ?, 'ru', 'Europe/Podgorica', 'active', NOW())
    ")->execute([$email, $displayName]);

    return (int)$db->lastInsertId();
}

function e2eFlowByType(array $flows, string $type): array
{
    foreach ($flows as $flow) {
        if (($flow['type'] ?? null) === $type) {
            return $flow;
        }
    }

    throw new RuntimeException("Missing flow type {$type}");
}

function e2eCreatePhysicalEntry(
    FinDeskV2Repository $repo,
    string $workspaceId,
    string $flowId,
    int $ownerId,
    string $rawText,
    string $date,
): array {
    return $repo->createEntry($workspaceId, [
        'flow_id' => $flowId,
        'date' => $date,
        'raw_text' => $rawText,
        'status' => 'recognized',
        'notes' => 'E2E accountable cash alignment. Balance movement only; no category on purpose.',
    ], $ownerId);
}

$db = FinDeskV2Database::pdo();
$repo = new FinDeskV2Repository($db);

$ownerId = 1;
$owner = $db->prepare("SELECT id, email FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1");
$owner->execute([$ownerId]);
e2eAssert((bool)$owner->fetch(), 'Owner user #1 is missing or inactive');

foreach ($repo->listWorkspaces($ownerId) as $existingWorkspace) {
    if (($existingWorkspace['name'] ?? null) === 'тест 12.08.26') {
        $repo->deleteWorkspace($existingWorkspace['id'], $ownerId);
    }
}

$employees = [
    [
        'name' => 'Тест сотрудник 1',
        'email' => 'vetus.nauta+findesk-e2e-emp1-20260812@gmail.com',
        'issued' => 400.0,
        'spent' => 200.0,
        'expected_status' => 'return_due',
        'expected_return' => 200.0,
        'expected_reimburse' => 0.0,
    ],
    [
        'name' => 'Тест сотрудник 2',
        'email' => 'vetus.nauta+findesk-e2e-emp2-20260812@gmail.com',
        'issued' => 200.0,
        'spent' => 500.0,
        'expected_status' => 'reimburse_due',
        'expected_return' => 0.0,
        'expected_reimburse' => 300.0,
    ],
    [
        'name' => 'Тест сотрудник 3',
        'email' => 'vetus.nauta+findesk-e2e-emp3-20260812@gmail.com',
        'issued' => 100.0,
        'spent' => 100.0,
        'expected_status' => 'closed',
        'expected_return' => 0.0,
        'expected_reimburse' => 0.0,
    ],
];

$workspace = $repo->createWorkspace([
    'name' => 'тест 12.08.26',
    'type' => 'custom',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '10000.00',
], $ownerId);
$workspaceId = $workspace['id'];
$flows = $repo->listFlows($workspaceId, $ownerId);
$cashFlow = e2eFlowByType($flows, 'cash');

$physicalEntries = [];
$auditEmployees = [];

foreach ($employees as $index => $employee) {
    $employeeNumber = $index + 1;
    $employeeId = e2eEnsureUser($db, $employee['email'], $employee['name']);
    $invite = $repo->createWorkspaceInvite($workspaceId, [
        'email' => $employee['email'],
        'name' => $employee['name'],
        'role' => 'employee',
        'access_scope' => 'own_entries',
        'expires_days' => 7,
    ], $ownerId);
    $repo->acceptWorkspaceInvite($invite['token'], $employeeId);

    $offer = $repo->createAccountableOffer($workspaceId, [
        'employee_user_id' => $employeeId,
        'amount' => number_format($employee['issued'], 2, '.', ''),
        'currency' => 'EUR',
        'purpose' => "E2E выдача под отчет сотруднику {$employeeNumber}",
    ], $ownerId);
    $acceptedOffer = $repo->acceptAccountableOffer($offer['id'], $employeeId);
    e2eAssert(($acceptedOffer['status'] ?? null) === 'accepted_by_employee', "Offer {$employeeNumber} was not accepted by employee");

    $report = $repo->createAccountableReport($workspaceId, [
        'offer_id' => $offer['id'],
        'title' => "E2E отчет сотрудника {$employeeNumber}",
        'rows' => [[
            'date' => '2026-08-12',
            'description' => "E2E подтвержденные расходы сотрудника {$employeeNumber}",
            'amount' => number_format($employee['spent'], 2, '.', ''),
            'currency' => 'EUR',
            'category_code' => 'current_boat_expenses',
            'notes' => 'Synthetic row for accountable workflow E2E audit.',
        ]],
    ], $employeeId);
    $submittedReport = $repo->submitAccountableReport($report['id'], $employeeId);
    e2eAssert(($submittedReport['status'] ?? null) === 'submitted', "Report {$employeeNumber} was not submitted");

    $review = $repo->acceptAccountableReportByAdmin($report['id'], [
        'payment_method' => 'cash',
        'review_note' => "E2E admin acceptance for employee {$employeeNumber}",
    ], $ownerId);
    $settlement = $review['settlement'] ?? [];
    e2eAssert(($settlement['status'] ?? null) === $employee['expected_status'], "Wrong settlement status for employee {$employeeNumber}");
    e2eAssertAmount($settlement['issued_amount'] ?? null, $employee['issued'], "Wrong issued amount for employee {$employeeNumber}");
    e2eAssertAmount($settlement['accepted_cash_expenses'] ?? null, $employee['spent'], "Wrong accepted cash amount for employee {$employeeNumber}");
    e2eAssertAmount($settlement['return_due_amount'] ?? null, $employee['expected_return'], "Wrong return due for employee {$employeeNumber}");
    e2eAssertAmount($settlement['reimburse_due_amount'] ?? null, $employee['expected_reimburse'], "Wrong reimburse due for employee {$employeeNumber}");

    $materialization = $repo->materializeAccountableReport($report['id'], $ownerId);
    e2eAssert(count($materialization['created_entries'] ?? []) === 1, "Report {$employeeNumber} did not create exactly one projection entry");
    $createdProjection = $materialization['created_entries'][0];
    e2eAssert(($createdProjection['entry_type'] ?? null) === 'accountable_expense', "Projection entry type mismatch for employee {$employeeNumber}");
    e2eAssert(($createdProjection['source_type'] ?? null) === 'accountable_report', "Projection source type mismatch for employee {$employeeNumber}");
    e2eAssert(($createdProjection['flow']['type'] ?? null) === 'accountable', "Projection flow type mismatch for employee {$employeeNumber}");
    e2eAssert(($createdProjection['balance_after'] ?? null) === null, "Projection must not mutate live cash balance for employee {$employeeNumber}");

    $physicalEntries[] = e2eCreatePhysicalEntry(
        $repo,
        $workspaceId,
        $cashFlow['id'],
        $ownerId,
        '-' . number_format($employee['issued'], 2, '.', '') . " физическая выдача сотруднику {$employeeNumber}",
        '2026-08-12'
    );

    $auditEmployees[] = [
        'employee_number' => $employeeNumber,
        'user_id' => $employeeId,
        'email' => $employee['email'],
        'offer_id' => $offer['id'],
        'report_id' => $report['id'],
        'issued' => $employee['issued'],
        'spent' => $employee['spent'],
        'settlement' => $settlement,
        'projection_entry_id' => $createdProjection['id'],
    ];
}

$preSettlementLayer1 = $repo->getLayer1SummaryReport($workspaceId, [
    'year' => 2026,
    'month' => 8,
], $ownerId);
e2eAssertAmount($preSettlementLayer1['money_position']['admin_cash'] ?? null, 9300.0, 'Pre-settlement admin cash mismatch');
e2eAssertAmount($preSettlementLayer1['money_position']['employee_held_cash'] ?? null, 200.0, 'Pre-settlement employee-held cash mismatch');
e2eAssertAmount($preSettlementLayer1['money_position']['physical_available_total'] ?? null, 9500.0, 'Pre-settlement physical pool mismatch');
e2eAssertAmount($preSettlementLayer1['money_position']['return_due_from_employees'] ?? null, 200.0, 'Pre-settlement return due mismatch');
e2eAssertAmount($preSettlementLayer1['money_position']['reimburse_due_to_employees'] ?? null, 300.0, 'Pre-settlement reimburse due mismatch');

$returnResolution = $repo->resolveAccountableSettlementWithCashMovement($auditEmployees[0]['settlement']['id'], [
    'date' => '2026-08-12',
    'raw_text' => '+200.00 возврат подотчетного остатка сотрудника 1',
    'note' => 'E2E physical return of underspend.',
], $ownerId);
$returnEntry = $returnResolution['entry'];
$physicalEntries[] = $returnEntry;
$auditEmployees[0]['resolved_settlement'] = $returnResolution['settlement'];

$reimbursementResolution = $repo->resolveAccountableSettlementWithCashMovement($auditEmployees[1]['settlement']['id'], [
    'date' => '2026-08-12',
    'raw_text' => '-300.00 физическое возмещение перерасхода сотруднику 2',
    'note' => 'E2E physical reimbursement of overspend.',
], $ownerId);
$reimbursementEntry = $reimbursementResolution['entry'];
$physicalEntries[] = $reimbursementEntry;
$auditEmployees[1]['resolved_settlement'] = $reimbursementResolution['settlement'];

$summary = $repo->getWorkspaceSummary($workspaceId, $ownerId);
$dashboard = $repo->getAccountableDashboard($workspaceId, $ownerId);
$layer1 = $repo->getLayer1SummaryReport($workspaceId, [
    'year' => 2026,
    'month' => 8,
], $ownerId);
$entries = $repo->listEntries($workspaceId, ['year' => 2026, 'month' => 8], $ownerId);

$expected = [
    'opening_cash' => 10000.0,
    'issued_total' => 700.0,
    'employee_spent_total' => 800.0,
    'return_due_gross_total' => 200.0,
    'reimburse_due_gross_total' => 300.0,
    'return_due_total' => 0.0,
    'reimburse_due_total' => 0.0,
    'settled_return_total' => 200.0,
    'settled_reimburse_total' => 300.0,
    'physical_return_total' => 200.0,
    'physical_reimburse_total' => 300.0,
    'ending_cash' => 9200.0,
];

e2eAssertAmount($summary['opening_cash'] ?? null, $expected['opening_cash'], 'Opening cash mismatch');
e2eAssertAmount($summary['cash_now'] ?? null, $expected['ending_cash'], 'Final cash mismatch');
e2eAssertAmount($dashboard['summary']['issued_total'] ?? null, $expected['issued_total'], 'Dashboard issued total mismatch');
e2eAssertAmount($dashboard['summary']['accepted_cash_expenses_total'] ?? null, $expected['employee_spent_total'], 'Dashboard accepted cash total mismatch');
e2eAssertAmount($dashboard['summary']['return_due_total'] ?? null, $expected['return_due_total'], 'Dashboard return due mismatch');
e2eAssertAmount($dashboard['summary']['reimburse_due_total'] ?? null, $expected['reimburse_due_total'], 'Dashboard reimburse due mismatch');
e2eAssertAmount($dashboard['summary']['return_due_gross_total'] ?? null, $expected['return_due_gross_total'], 'Dashboard gross return due mismatch');
e2eAssertAmount($dashboard['summary']['reimburse_due_gross_total'] ?? null, $expected['reimburse_due_gross_total'], 'Dashboard gross reimburse due mismatch');
e2eAssertAmount($dashboard['summary']['settled_return_total'] ?? null, $expected['settled_return_total'], 'Dashboard settled return mismatch');
e2eAssertAmount($dashboard['summary']['settled_reimburse_total'] ?? null, $expected['settled_reimburse_total'], 'Dashboard settled reimburse mismatch');
e2eAssertAmount($dashboard['summary']['open_position_total'] ?? null, 0.0, 'Dashboard net employee-held position mismatch');
e2eAssertAmount($layer1['money_position']['admin_cash'] ?? null, $expected['ending_cash'], 'Layer 1 admin cash mismatch');
e2eAssertAmount($layer1['money_position']['employee_held_cash'] ?? null, 0.0, 'Layer 1 employee-held cash mismatch');
e2eAssertAmount($layer1['money_position']['physical_available_total'] ?? null, $expected['ending_cash'], 'Layer 1 physical pool mismatch');
e2eAssertAmount($layer1['money_position']['reimburse_due_to_employees'] ?? null, 0.0, 'Layer 1 reimburse due mismatch');

$entryCounts = [
    'total' => count($entries),
    'physical_cash_entries' => 0,
    'accountable_projection_entries' => 0,
    'projection_cash_effect_none' => 0,
    'categorized_physical_cash_entries' => 0,
];

$physicalEntryIds = array_fill_keys(array_map(static fn (array $entry): string => (string)$entry['id'], $physicalEntries), true);
foreach ($entries as $entry) {
    if (isset($physicalEntryIds[(string)$entry['id']])) {
        e2eAssert(($entry['flow']['type'] ?? null) === 'cash', "Physical entry {$entry['id']} is not in cash flow");
        $entryCounts['physical_cash_entries']++;
        if (($entry['category_code'] ?? null) !== null) {
            $entryCounts['categorized_physical_cash_entries']++;
        }
    }
    if (($entry['entry_type'] ?? null) === 'accountable_expense') {
        $entryCounts['accountable_projection_entries']++;
        $rules = $entry['matched_rules'] ?? [];
        foreach ($rules as $rule) {
            if (is_array($rule) && ($rule['cash_effect'] ?? null) === 'none') {
                $entryCounts['projection_cash_effect_none']++;
                break;
            }
        }
    }
}

e2eAssert($entryCounts['physical_cash_entries'] === 5, 'Expected five physical cash balancing entries');
e2eAssert($entryCounts['categorized_physical_cash_entries'] === 0, 'Physical cash balancing entries must not be categorized as expenses');
e2eAssert($entryCounts['accountable_projection_entries'] === 3, 'Expected three accountable projection entries');
e2eAssert($entryCounts['projection_cash_effect_none'] === 3, 'Every accountable projection must declare cash_effect none');

$auditCountStmt = $db->prepare('SELECT COUNT(*) FROM v2_audit_log WHERE workspace_id = ?');
$auditCountStmt->execute([$workspaceId]);
$auditLogCount = (int)$auditCountStmt->fetchColumn();
e2eAssert($auditLogCount >= 20, 'Audit log is unexpectedly small');

$payload = [
    'ok' => true,
    'scenario' => 'accountable_three_employee_cash_alignment',
    'workspace' => [
        'id' => $workspaceId,
        'name' => $workspace['name'],
        'owner_user_id' => $ownerId,
    ],
    'expected' => $expected,
    'actual' => [
        'summary' => $summary,
        'accountable_dashboard_summary' => $dashboard['summary'],
        'pre_settlement_money_position' => $preSettlementLayer1['money_position'],
        'layer1_money_position' => $layer1['money_position'],
        'entry_counts' => $entryCounts,
        'audit_log_count' => $auditLogCount,
    ],
    'employees' => $auditEmployees,
    'physical_entries' => array_map(static fn (array $entry): array => [
        'id' => $entry['id'],
        'raw_text' => $entry['raw_text'],
        'amount' => $entry['amount'],
        'direction' => $entry['direction'],
        'entry_type' => $entry['entry_type'],
        'flow_type' => $entry['flow']['type'] ?? null,
        'category_code' => $entry['category_code'] ?? null,
        'balance_after' => $entry['balance_after'],
    ], $physicalEntries),
    'algorithm' => [
        'offer_acceptance' => 'Employee confirmation creates accountable obligation, not cash/card mutation.',
        'admin_acceptance' => 'Settlement compares issued cash with accepted cash expenses.',
        'ledger_projection' => 'Accepted reports create accountable_expense rows on accountable flow with cash_effect none.',
        'physical_alignment' => 'Real cash movement is recorded separately: issues, return of underspend, reimbursement of overspend.',
        'physical_pool' => 'Physically available means admin cash plus employee-held cash. Reimbursement due is an obligation and is shown separately.',
        'cash_formula' => '10000 - 400 - 200 - 100 + 200 - 300 = 9200.',
    ],
    'created_at' => date(DATE_ATOM),
];

$auditDir = __DIR__ . '/../storage/production-audits';
if (!is_dir($auditDir) && !mkdir($auditDir, 0775, true) && !is_dir($auditDir)) {
    throw new RuntimeException("Unable to create audit directory {$auditDir}");
}
$auditPath = $auditDir . '/v2-accountable-three-employee-audit-' . date('Ymd-His') . '.json';
file_put_contents($auditPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'ok' => true,
    'workspace_id' => $workspaceId,
    'workspace_name' => $workspace['name'],
    'cash_now' => $summary['cash_now'],
    'expected_cash_now' => $expected['ending_cash'],
    'dashboard_summary' => $dashboard['summary'],
    'pre_settlement_money_position' => $preSettlementLayer1['money_position'],
    'layer1_money_position' => $layer1['money_position'],
    'entry_counts' => $entryCounts,
    'audit_log_count' => $auditLogCount,
    'audit_path' => $auditPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
