<?php

declare(strict_types=1);

require getenv('FINDESK_V2_FIXTURE_HARNESS') . '/app/v2/Repository.php';

final class FixtureReport
{
    private array $passes = [];
    private array $blocked = [];
    private array $failures = [];

    public function pass(string $fixture, string $message): void
    {
        $this->passes[] = [$fixture, $message];
    }

    public function blocked(string $fixture, string $message): void
    {
        $this->blocked[] = [$fixture, $message];
    }

    public function fail(string $fixture, string $message): void
    {
        $this->failures[] = [$fixture, $message];
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    public function print(): void
    {
        echo $this->hasFailures()
            ? "FinDesk v2 fixture runner: FAIL\n"
            : "FinDesk v2 fixture runner: PASS\n";

        $this->printGroup('PASS', $this->passes);
        $this->printGroup('BLOCKED / NOT_IMPLEMENTED', $this->blocked);

        if ($this->failures !== []) {
            $this->printGroup('FAIL', $this->failures);
        }
    }

    private function printGroup(string $label, array $rows): void
    {
        echo "\n{$label} (" . count($rows) . ")\n";
        foreach ($rows as [$fixture, $message]) {
            echo "- {$fixture}: {$message}\n";
        }
    }
}

function fixtureAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSameValue($actual, $expected, string $message): void
{
    fixtureAssert($actual === $expected, "{$message}; expected " . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function assertAmount($actual, float $expected, string $message): void
{
    fixtureAssert($actual !== null, "{$message}; expected {$expected}, got null");
    fixtureAssert(abs((float)$actual - $expected) < 0.001, "{$message}; expected {$expected}, got {$actual}");
}

function byFlowType(array $flows, string $type): array
{
    foreach ($flows as $flow) {
        if ($flow['type'] === $type) {
            return $flow;
        }
    }

    throw new RuntimeException("Missing flow type: {$type}");
}

function categoryCodes(array $categories): array
{
    return array_fill_keys(array_map(static fn (array $category): string => $category['code'], $categories), true);
}

function assertCategoryExists(array $codes, string $code): void
{
    fixtureAssert(isset($codes[$code]), "Missing seeded category: {$code}");
}

function fixtureXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function fixtureColumnName(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - $mod, 26);
    }

    return $name;
}

function fixtureCreateXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'findesk-v2-fixture-import-') . '.xlsx';
    $zip = new ZipArchive();
    fixtureAssert($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'could not create xlsx fixture');
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="July" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');

    $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $number = $rowIndex + 1;
        $sheet .= '<row r="' . $number . '">';
        foreach ($row as $columnIndex => $value) {
            if ((string)$value === '') {
                continue;
            }
            $ref = fixtureColumnName($columnIndex) . $number;
            $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . fixtureXml((string)$value) . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    return $path;
}

function entryIsVisible(FinDeskV2Repository $repo, string $workspaceId, int $userId, string $entryId): bool
{
    foreach ($repo->listEntries($workspaceId, [], $userId) as $entry) {
        if ($entry['id'] === $entryId) {
            return true;
        }
    }

    return false;
}

function countEntriesForFlow(FinDeskV2Repository $repo, string $workspaceId, int $userId, string $flowId): int
{
    $count = 0;
    foreach ($repo->listEntries($workspaceId, [], $userId) as $entry) {
        if ($entry['flow']['id'] === $flowId) {
            $count++;
        }
    }

    return $count;
}

function lastBalanceForFlow(FinDeskV2Repository $repo, string $workspaceId, int $userId, string $flowId): ?float
{
    $balance = null;
    foreach ($repo->listEntries($workspaceId, [], $userId) as $entry) {
        if ($entry['flow']['id'] === $flowId && $entry['balance_after'] !== null) {
            $balance = (float)$entry['balance_after'];
        }
    }

    return $balance;
}

function matchedRuleHas(array $entry, string $key, string $value): bool
{
    foreach ($entry['matched_rules'] ?? [] as $rule) {
        if (is_array($rule) && ($rule[$key] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

function semanticMarkerHas(array $entry, string $marker, ?string $key = null, ?string $value = null): bool
{
    foreach ($entry['semantic_markers'] ?? [] as $item) {
        if (!is_array($item) || ($item['marker'] ?? null) !== $marker) {
            continue;
        }
        if ($key === null) {
            return true;
        }
        if (($item[$key] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

function classificationSignalHas(array $entry, string $key, string $value): bool
{
    foreach ($entry['matched_signals'] ?? [] as $signal) {
        if (is_array($signal) && ($signal[$key] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

function classificationBlockerHas(array $entry, string $blocker): bool
{
    return in_array($blocker, $entry['blockers'] ?? [], true);
}

function fixtureAuditCount(PDO $pdo, string $entityType, string $entityId, string $action): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM v2_audit_log
        WHERE entity_type = ? AND entity_id = ? AND action = ?
    ");
    $stmt->execute([$entityType, $entityId, $action]);

    return (int)$stmt->fetchColumn();
}

function fixtureTableCounts(PDO $pdo, array $tables): array
{
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    return $counts;
}

function addFixtureWorkspaceMember(PDO $pdo, string $workspaceId, int $memberUserId, string $role): void
{
    $stmt = $pdo->prepare("
        INSERT INTO v2_workspace_members (id, workspace_id, user_id, role)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([FinDeskV2Support::uuid(), $workspaceId, $memberUserId, $role]);
}

function runFixture(FixtureReport $report, string $fixture, callable $callback): void
{
    try {
        $message = $callback();
        $report->pass($fixture, $message);
    } catch (Throwable $e) {
        $report->fail($fixture, $e->getMessage());
    }
}

function expectClosedMonthDecision(callable $callback): array
{
    try {
        $callback();
    } catch (FinDeskV2HttpError $e) {
        fixtureAssert($e->status === 409, 'closed month error status');
        $payload = json_decode($e->getMessage(), true);
        fixtureAssert(is_array($payload), 'closed month error payload is not JSON');
        assertSameValue($payload['error'] ?? null, 'closed_month_requires_decision', 'closed month error code');
        assertSameValue($payload['choices'] ?? null, ['create_correction', 'recalculate_chain', 'cancel'], 'closed month choices');
        return $payload;
    }

    throw new RuntimeException('closed month mutation was allowed');
}

function expectFixtureHttpError(callable $callback, int $status, string $error): void
{
    try {
        $callback();
    } catch (FinDeskV2HttpError $e) {
        fixtureAssert($e->status === $status, "expected HTTP {$status}, got {$e->status}");
        assertSameValue($e->getMessage(), $error, 'unexpected error code');
        return;
    }

    throw new RuntimeException("expected error {$error}");
}

$pdo = ql_db();
$repo = new FinDeskV2Repository($pdo);
$userId = 15001;
$report = new FixtureReport();

$workspace = $repo->createWorkspace([
    'name' => 'FinDesk v2 Fixture Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '1000.00',
], $userId);

$flows = $repo->listFlows($workspace['id'], $userId);
$cashFlow = byFlowType($flows, 'cash');
$cardFlow = byFlowType($flows, 'card');
$categories = categoryCodes($repo->listCategories($workspace['id'], $userId));

foreach (['commercial_income', 'non_commercial_income', 'media_comms', 'cash_topup_from_card', 'other', 'fuel', 'tech_parts', 'representation_expenses', 'current_boat_expenses', 'guest_trip_support', 'guest_cash_issued'] as $categoryCode) {
    assertCategoryExists($categories, $categoryCode);
}

runFixture($report, 'Fixture 1 - Basic cash', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $income = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+500 пополнение',
    ], $userId);
    $expense = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-250 рыба',
    ], $userId);

    assertSameValue($income['flow']['type'], 'cash', 'income flow');
    assertSameValue($income['sign'], '+', 'income sign');
    assertSameValue($income['direction'], 'in', 'income direction');
    assertSameValue($income['entry_type'], 'cash_income', 'income type');
    assertSameValue($income['status'], 'recognized', 'income status');
    assertAmount($income['amount'], 500.0, 'income amount');
    assertAmount($income['balance_after'], 1500.0, 'income balance_after');

    assertSameValue($expense['flow']['type'], 'cash', 'expense flow');
    assertSameValue($expense['sign'], '-', 'expense sign');
    assertSameValue($expense['direction'], 'out', 'expense direction');
    assertSameValue($expense['entry_type'], 'cash_expense', 'expense type');
    assertSameValue($expense['status'], 'recognized', 'expense status');
    assertAmount($expense['amount'], 250.0, 'expense amount');
    assertAmount($expense['balance_after'], 1250.0, 'expense balance_after');

    return 'cash +500/-250 normalized as income/expense with cash_now 1250 from opening 1000';
});

runFixture($report, 'Fixture 2 - Invalid no-sign row', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '250 рыба',
        'amount' => '250.00',
        'status' => 'recognized',
    ], $userId);

    assertSameValue($entry['sign'], null, 'no-sign sign');
    assertSameValue($entry['amount'], null, 'no-sign amount');
    assertSameValue($entry['status'], 'unrecognized', 'no-sign status');
    assertSameValue($entry['entry_type'], 'unrecognized', 'no-sign type');
    assertSameValue($entry['direction'], 'none', 'no-sign direction');
    fixtureAssert(entryIsVisible($repo, $workspace['id'], $userId, $entry['id']), 'no-sign row is not visible in feed');

    return 'row without +/- stays visible but unrecognized with null sign/amount';
});

runFixture($report, 'Fixture 3 - Card expense', function () use ($repo, $workspace, $cashFlow, $cardFlow, $userId): string {
    $cashEntriesBefore = countEntriesForFlow($repo, $workspace['id'], $userId, $cashFlow['id']);
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-60 Netflix',
    ], $userId);
    $cashEntriesAfter = countEntriesForFlow($repo, $workspace['id'], $userId, $cashFlow['id']);

    assertSameValue($entry['flow']['type'], 'card', 'card flow');
    assertSameValue($entry['direction'], 'out', 'card direction');
    assertSameValue($entry['entry_type'], 'card_expense', 'card entry type');
    assertSameValue($entry['category_code'], 'media_comms', 'card category');
    assertSameValue($entry['status'], 'recognized', 'card status');
    assertAmount($entry['amount'], 60.0, 'card amount');
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['card_expense_total'], 60.0, 'card expense total');
    assertSameValue($cashEntriesAfter, $cashEntriesBefore, 'card entry should not create/touch cash entries');
    $repo->deleteEntry($entry['id'], $userId);
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['card_expense_total'], 0.0, 'archived card expense total');

    return 'card -60 normalizes as media_comms card_expense, rolls up to 60, excludes archived rows, and does not create cash rows';
});

runFixture($report, 'Fixture 4 - Card to cash', function () use ($repo, $workspace, $cashFlow, $cardFlow, $userId): string {
    $cashBalanceBefore = lastBalanceForFlow($repo, $workspace['id'], $userId, $cashFlow['id']);
    $cardSide = $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-1000 снял с карты',
        'category_code' => 'cash_topup_from_card',
    ], $userId);
    $cashSide = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+1000 снял с карты',
        'category_code' => 'cash_topup_from_card',
    ], $userId);

    assertSameValue($cardSide['entry_type'], 'card_expense', 'card side type');
    assertSameValue($cardSide['status'], 'recognized', 'card side status');
    assertSameValue($cardSide['category_code'], 'cash_topup_from_card', 'card side category');
    assertAmount($cardSide['amount'], 1000.0, 'card side amount');
    assertSameValue($cardSide['balance_after'], null, 'card side cash balance_after');
    fixtureAssert($cardSide['status'] !== 'duplicate_suspect', 'card side was marked duplicate');

    assertSameValue($cashSide['entry_type'], 'cash_income', 'cash side type');
    assertSameValue($cashSide['status'], 'recognized', 'cash side status');
    assertSameValue($cashSide['category_code'], 'cash_topup_from_card', 'cash side category');
    assertAmount($cashSide['amount'], 1000.0, 'cash side amount');
    assertAmount($cashSide['balance_after'], ($cashBalanceBefore ?? 1000.0) + 1000.0, 'cash side balance_after');
    fixtureAssert($cashSide['status'] !== 'duplicate_suspect', 'cash side was marked duplicate');
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['card_expense_total'], 1000.0, 'card-to-cash card expense total');

    return 'card -1000 and cash +1000 save with transfer category; only cash side increases cash balance';
});

runFixture($report, 'Fixture 5 - Commercial income', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $charter = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+5000 charter deposit',
        'category_code' => 'commercial_income',
    ], $userId);
    $agency = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+750 агентские',
        'category_code' => 'commercial_income',
    ], $userId);

    foreach ([$charter, $agency] as $entry) {
        assertSameValue($entry['direction'], 'in', 'commercial income direction');
        assertSameValue($entry['entry_type'], 'cash_income', 'commercial income type');
        assertSameValue($entry['category_code'], 'commercial_income', 'commercial income category');
    }
    assertSameValue($cashFlow['opening_balance'], 1000.0, 'commercial income should not alter opening cash seed');

    $total = (float)$charter['amount'] + (float)$agency['amount'];
    assertAmount($total, 5750.0, 'commercial income total');

    return 'commercial_income can be explicitly assigned, totals 5750, and does not alter opening cash seed';
});

runFixture($report, 'Fixture 6 - Other expenses', function () use ($repo, $workspace, $cashFlow, $cardFlow, $userId): string {
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-180 какая-то штука',
    ], $userId);
    $englishOther = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-19 other expense manual queue',
    ], $userId);
    $unknownOther = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-21 unknown_expense manual queue',
    ], $userId);
    $nonOtherCategory = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-42 заправка',
    ], $userId);
    $manualRecognizedOther = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-10 manually recognized other',
        'category_code' => 'other',
        'status' => 'recognized',
    ], $userId);
    $cashIncomeUnknown = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+19 unknown_expense income boundary',
    ], $userId);
    $cardOther = $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-19 other expense card boundary',
    ], $userId);
    $noSignMisc = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '19 misc no sign boundary',
    ], $userId);
    $archivedOther = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-11 какая-то штука',
    ], $userId);
    $repo->deleteEntry($archivedOther['id'], $userId);

    assertSameValue($entry['category_code'], 'other', 'other expense category');
    assertSameValue($entry['status'], 'other_review', 'other expense status');
    assertSameValue($entry['entry_type'], 'cash_expense', 'other expense type');
    assertAmount($entry['amount'], 180.0, 'other expense amount');
    assertSameValue($englishOther['category_code'], 'other', 'english other expense category');
    assertSameValue($englishOther['status'], 'other_review', 'english other expense status');
    assertSameValue($unknownOther['category_code'], 'other', 'unknown_expense category');
    assertSameValue($unknownOther['status'], 'other_review', 'unknown_expense status');
    fixtureAssert($cashIncomeUnknown['status'] !== 'other_review', 'cash + unknown_expense should not enter other_review');
    fixtureAssert($cashIncomeUnknown['category_code'] !== 'other', 'cash + unknown_expense should not become other');
    fixtureAssert($cardOther['status'] !== 'other_review', 'card other expense should not enter cash other_review');
    fixtureAssert($cardOther['category_code'] !== 'other', 'card other expense should not become cash other');
    assertSameValue($noSignMisc['status'], 'unrecognized', 'no-sign misc should remain unrecognized');
    assertSameValue($noSignMisc['category_code'], null, 'no-sign misc should not become other');
    fixtureAssert(entryIsVisible($repo, $workspace['id'], $userId, $entry['id']), 'other expense row is not visible in feed');
    $queue = $repo->listOtherExpenseQueue($workspace['id'], $userId);
    fixtureAssert(count($queue) === 3, 'other expense queue count mismatch');
    assertSameValue($queue[0]['id'], $entry['id'], 'other expense queue first entry mismatch');
    assertSameValue($queue[1]['id'], $englishOther['id'], 'other expense queue english entry mismatch');
    assertSameValue($queue[2]['id'], $unknownOther['id'], 'other expense queue unknown entry mismatch');
    fixtureAssert($nonOtherCategory['category_code'] !== 'other', 'negative fixture should not be other category');
    fixtureAssert($manualRecognizedOther['status'] === 'recognized', 'manual recognized other status mismatch');

    return 'unknown cash expense maps to other/other_review, stays counted, appears in queue, and queue excludes archived/non-review/non-other rows';
});

runFixture($report, 'Fixture 7 - Tender fuel ambiguity', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-42 заправка тузика',
    ], $userId);

    assertSameValue($entry['category_code'], 'fuel', 'tender fuel primary category');
    assertSameValue($entry['status'], 'recognized', 'tender fuel status');
    fixtureAssert(matchedRuleHas($entry, 'marker', 'tender_related'), 'tender fuel secondary marker missing');

    return 'tender fuel keeps primary category fuel and records tender_related secondary marker';
});

runFixture($report, 'Fixture 8 - Person is actor, not category', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $advance = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-500 Вова аванс',
    ], $userId);
    $cable = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-87 Вова купил кабель',
    ], $userId);
    $return = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+120 Вова вернул остаток',
    ], $userId);

    foreach ([$advance, $cable, $return] as $entry) {
        assertSameValue($entry['actor']['name'] ?? null, 'Вова', 'actor name');
    }

    assertSameValue($advance['category_code'], 'crew', 'advance category');
    assertSameValue($cable['category_code'], 'tech_parts', 'cable category');
    assertSameValue($return['entry_type'], 'cash_income', 'return type');
    fixtureAssert($return['category_code'] !== 'crew', 'return was categorized as crew by actor name alone');

    return 'Вова is extracted as actor; category follows transaction context, not person name alone';
});

runFixture($report, 'Fixture 9 - Semantic markers', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $safe = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+6000 из сейфа',
    ], $userId);
    $owner = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+5000 от Александра',
    ], $userId);
    $yachtRental = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+5525 ареда яхты',
    ], $userId);
    $carRental = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+100 аренда авто',
    ], $userId);

    fixtureAssert(semanticMarkerHas($safe, 'cash_location_safe'), 'safe cash marker missing');
    fixtureAssert(semanticMarkerHas($safe, 'owner_funding'), 'safe income owner funding marker missing');
    assertSameValue($safe['category_code'], 'non_commercial_income', 'safe income category');

    fixtureAssert(semanticMarkerHas($owner, 'owner_funding', 'source_actor', 'Александр'), 'owner funding source actor missing');
    assertSameValue($owner['category_code'], 'non_commercial_income', 'owner funding category');
    assertSameValue($owner['review_reason'], null, 'owner funding review reason');
    assertAmount($owner['confidence'], 0.92, 'owner funding confidence');

    assertSameValue($yachtRental['category_code'], 'commercial_income', 'yacht rental category');
    fixtureAssert(semanticMarkerHas($yachtRental, 'commercial_income_allowed'), 'commercial income marker missing');
    fixtureAssert(!semanticMarkerHas($yachtRental, 'owner_funding'), 'commercial income also marked owner funding');
    assertSameValue($yachtRental['review_reason'], null, 'yacht rental review reason');
    assertAmount($yachtRental['confidence'], 0.92, 'yacht rental confidence');

    fixtureAssert($carRental['category_code'] !== 'commercial_income', 'car rental became commercial');
    assertSameValue($carRental['category_code'], 'non_commercial_income', 'car rental private income category');
    fixtureAssert(semanticMarkerHas($carRental, 'owner_funding'), 'non-yacht income owner marker missing');

    return 'semantic markers expose safe cash context, owner funding, and strict yacht commercial-income boundary with non-commercial income labeling';
});

runFixture($report, 'Fixture 10 - Lower accounting block', function () use ($repo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Lower Accounting Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $cashFlow = byFlowType($repo->listFlows($workspace['id'], $userId), 'cash');

    $credit = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-1000 мой кредит',
    ], $userId);
    $debtGarage = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-250 долг за гараж',
    ], $userId);
    $returned = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+120 Вова вернул остаток',
    ], $userId);
    $accountable = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-300 Женя под отчет',
    ], $userId);
    $partialReturn = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+100 Женя вернул',
    ], $userId);
    $guestCash = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 передал ЛВ',
    ], $userId);
    $guestCashReturn = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+100 ЛВ вернул',
    ], $userId);
    $unassignedReturn = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+50 вернул остаток',
    ], $userId);
    $garage = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 гараж',
    ], $userId);
    $gift = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 подарок Алине',
    ], $userId);
    $lawyer = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 адвокат',
    ], $userId);

    foreach ([$credit, $debtGarage, $returned, $accountable, $partialReturn, $guestCashReturn, $unassignedReturn] as $entry) {
        fixtureAssert(semanticMarkerHas($entry, 'debt_or_return'), 'lower accounting debt marker missing for ' . $entry['raw_text']);
    }
    foreach ([$accountable] as $entry) {
        assertSameValue($entry['accounting_section'], 'lower_accounting', 'entry lower accounting section');
    }
    assertSameValue($credit['accounting_section'], 'admin_debt', 'personal credit belongs to administrator debt');
    assertSameValue($guestCash['category_code'], 'guest_cash_issued', 'guest cash category');
    assertSameValue($guestCash['accounting_section'], 'operational', 'guest cash remains operational for manual category correction');
    assertSameValue($debtGarage['accounting_section'], 'operational', 'debt wording with concrete boat expense remains operational');
    assertSameValue($returned['accounting_section'], 'operational', 'return wording without active issue remains operational');
    assertSameValue($partialReturn['accounting_section'], 'operational', 'return row remains operational/non-commercial income');
    assertSameValue($guestCashReturn['accounting_section'], 'operational', 'guest cash return remains operational/non-commercial income');
    assertSameValue($unassignedReturn['accounting_section'], 'operational', 'unassigned return remains operational for review');
    assertSameValue($garage['accounting_section'], 'operational', 'plain garage should remain operational');
    assertSameValue($gift['accounting_section'], 'operational', 'gift should remain operational');
    assertSameValue($lawyer['accounting_section'], 'operational', 'lawyer should remain operational');

    $monthly = $repo->getMonthlyReport($workspace['id'], ['year' => 2026, 'month' => 7], $userId);
    assertAmount($monthly['cash_expense'], 1950.0, 'lower accounting physical cash expense must remain counted');
    assertAmount($monthly['external_cash_income'], 370.0, 'lower accounting return must remain physical income');
    assertAmount($monthly['ending_cash'], -580.0, 'lower accounting ending cash must remain physical balance');

    $layer1 = $repo->getLayer1SummaryReport($workspace['id'], ['year' => 2026, 'month' => 7], $userId);
    assertSameValue($layer1['blocks']['lower_accounting']['count'], 1, 'lower accounting block count');
    assertAmount($layer1['blocks']['lower_accounting']['total'], 300.0, 'lower accounting block total');
    foreach ([$accountable] as $entry) {
        fixtureAssert(in_array($entry['id'], $layer1['blocks']['lower_accounting']['source_entry_ids'], true), 'lower accounting source missing ' . $entry['raw_text']);
    }
    fixtureAssert(in_array($credit['id'], $layer1['blocks']['admin_debt']['source_entry_ids'], true), 'admin debt source missing personal credit');
    $settlements = [];
    foreach ($layer1['blocks']['lower_accounting']['settlements']['by_counterparty'] as $row) {
        $settlements[$row['counterparty']] = $row;
    }
    assertSameValue($settlements['Женя']['status'] ?? null, 'open', 'Женя settlement status');
    assertAmount($settlements['Женя']['open_amount'] ?? null, 300.0, 'Женя open amount');

    $categoryRows = [];
    foreach ($layer1['blocks']['categories']['rows'] as $row) {
        $categoryRows[$row['category_code']] = $row;
    }
    assertAmount($categoryRows['berth']['cash_total'] ?? null, 350.0, 'debt garage should remain editable operational berth category');
    assertAmount($categoryRows['guest_cash_issued']['cash_total'] ?? null, 100.0, 'guest cash issued operational category control');
    assertAmount($categoryRows['representation_expenses']['cash_total'] ?? null, 100.0, 'representation category control');
    assertAmount($categoryRows['admin_legal']['cash_total'] ?? null, 100.0, 'admin/legal category control');

    return 'accountable rows form lower accounting; personal credit forms administrator debt; contextual debt/return/guest-cash rows remain operational and editable';
});
runFixture($report, 'Fixture 10 - Month insertion recalculation', function () use ($repo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Balance Chain Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $cashFlow = byFlowType($repo->listFlows($workspace['id'], $userId), 'cash');

    $first = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-01',
        'raw_text' => '-100 fuel',
    ], $userId);
    $third = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-03',
        'raw_text' => '-100 food',
    ], $userId);
    $second = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-02',
        'raw_text' => '+500 top-up',
    ], $userId);

    $entries = [];
    foreach ($repo->listEntries($workspace['id'], [], $userId) as $entry) {
        $entries[$entry['id']] = $entry;
    }

    assertAmount($entries[$first['id']]['balance_after'], 900.0, '01.07 balance');
    assertAmount($entries[$second['id']]['balance_after'], 1400.0, '02.07 inserted balance');
    assertAmount($entries[$third['id']]['balance_after'], 1300.0, '03.07 recalculated balance');

    $repo->updateEntry($third['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-03',
        'raw_text' => '-200 food corrected',
    ], $userId);
    $afterUpdate = [];
    foreach ($repo->listEntries($workspace['id'], [], $userId) as $entry) {
        $afterUpdate[$entry['id']] = $entry;
    }
    assertAmount($afterUpdate[$first['id']]['balance_after'], 900.0, '01.07 balance after edit');
    assertAmount($afterUpdate[$second['id']]['balance_after'], 1400.0, '02.07 balance after edit');
    assertAmount($afterUpdate[$third['id']]['balance_after'], 1200.0, '03.07 balance after edit');
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['cash_now'], 1200.0, 'cash now after edit');

    $repo->deleteEntry($second['id'], $userId);
    $afterDelete = [];
    foreach ($repo->listEntries($workspace['id'], [], $userId) as $entry) {
        $afterDelete[$entry['id']] = $entry;
    }
    fixtureAssert(!isset($afterDelete[$second['id']]), 'deleted middle entry remains visible');
    assertAmount($afterDelete[$first['id']]['balance_after'], 900.0, '01.07 balance after delete');
    assertAmount($afterDelete[$third['id']]['balance_after'], 700.0, '03.07 balance after delete');
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['cash_now'], 700.0, 'cash now after delete');

    return 'insert/edit/delete recalculate later balance_after values and cash_now';
});

runFixture($report, 'Fixture 10 - Closed month protection', function () use ($repo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Closed Month Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $cashFlow = byFlowType($repo->listFlows($workspace['id'], $userId), 'cash');
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 fuel',
    ], $userId);
    $repo->closeMonthForFixture($workspace['id'], 2026, 7, $userId);
    $openMonthEntry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-08-05',
        'raw_text' => '+50 open month topup',
    ], $userId);

    expectClosedMonthDecision(static fn () => $repo->updateEntry($entry['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-200 fuel',
    ], $userId));
    expectClosedMonthDecision(static fn () => $repo->deleteEntry($entry['id'], $userId));
    expectClosedMonthDecision(static fn () => $repo->updateEntryCategory($entry['id'], [
        'category_code' => 'tech_parts',
    ], $userId));
    expectClosedMonthDecision(static fn () => $repo->updateEntry($openMonthEntry['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-08',
        'raw_text' => '+50 moved into closed month',
    ], $userId));

    $entries = $repo->listEntries($workspace['id'], [], $userId);
    $entriesById = [];
    foreach ($entries as $candidate) {
        $entriesById[$candidate['id']] = $candidate;
    }
    fixtureAssert(count($entries) === 2, 'closed month fixture entry count mismatch');
    assertSameValue($entriesById[$entry['id']]['raw_text'], '-100 fuel', 'closed month entry raw_text should not change');
    assertSameValue($entriesById[$entry['id']]['category_code'], 'fuel', 'closed month category should not change');
    assertAmount($entriesById[$entry['id']]['amount'], 100.0, 'closed month entry amount should not change');
    assertAmount($entriesById[$entry['id']]['balance_after'], 900.0, 'closed month balance should not silently recalculate');
    assertSameValue($entriesById[$openMonthEntry['id']]['date'], '2026-08-05', 'open-month entry should not move into closed month');

    $confirmedUpdate = $repo->updateEntry($entry['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-150 fuel confirmed',
        'closed_month_decision' => 'recalculate_chain',
    ], $userId);
    assertSameValue($confirmedUpdate['raw_text'], '-150 fuel confirmed', 'confirmed closed month update should change raw text');
    assertAmount($confirmedUpdate['balance_after'], 850.0, 'confirmed closed month update should recalculate closed row');
    $afterConfirmedUpdate = [];
    foreach ($repo->listEntries($workspace['id'], [], $userId) as $candidate) {
        $afterConfirmedUpdate[$candidate['id']] = $candidate;
    }
    assertAmount($afterConfirmedUpdate[$openMonthEntry['id']]['balance_after'], 900.0, 'confirmed closed month update should recalculate later row');

    $repo->deleteEntry($entry['id'], $userId, ['closed_month_decision' => 'recalculate_chain']);
    $afterConfirmedDelete = [];
    foreach ($repo->listEntries($workspace['id'], [], $userId) as $candidate) {
        $afterConfirmedDelete[$candidate['id']] = $candidate;
    }
    fixtureAssert(!isset($afterConfirmedDelete[$entry['id']]), 'confirmed closed month delete should archive closed row');
    assertAmount($afterConfirmedDelete[$openMonthEntry['id']]['balance_after'], 1050.0, 'confirmed closed month delete should recalculate later row');

    return 'closed month edit/category/delete and open-to-closed date moves are blocked without decision; confirmed recalculate edits are explicit';
});

runFixture($report, 'Attachments base', function () use ($repo, $pdo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Attachment Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $cashFlow = byFlowType($repo->listFlows($workspace['id'], $userId), 'cash');
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 fuel',
    ], $userId);
    $summaryBefore = $repo->getWorkspaceSummary($workspace['id'], $userId);
    $repo->closeMonthForFixture($workspace['id'], 2026, 7, $userId);

    expectFixtureHttpError(static fn () => $repo->createEntryAttachment($entry['id'], [
        'file_name' => '../receipt.png',
        'content_base64' => base64_encode('not an image'),
    ], $userId), 422, 'invalid_file_name');
    expectFixtureHttpError(static fn () => $repo->createEntryAttachment($entry['id'], [
        'file_name' => 'receipt.png',
        'content_base64' => 'not-base64!',
    ], $userId), 422, 'invalid_content_base64');

    $attachment = $repo->createEntryAttachment($entry['id'], [
        'file_name' => 'receipt.png',
        'content_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'mime_type' => 'text/plain',
        'image_mode' => 'original',
    ], $userId);
    assertSameValue($attachment['entry_id'], $entry['id'], 'attachment entry id');
    assertSameValue($attachment['mime_type'], 'image/png', 'attachment MIME should be detected from bytes');
    fixtureAssert(str_starts_with($attachment['file_url'], 'storage/v2/attachments/'), 'attachment path should be v2 private storage');
    $harnessRoot = rtrim((string)getenv('FINDESK_V2_FIXTURE_HARNESS'), '/');
    fixtureAssert($harnessRoot !== '', 'fixture harness root missing');
    fixtureAssert(is_file($harnessRoot . '/' . $attachment['file_url']), 'attachment stored file missing');
    fixtureAssert(fixtureAuditCount($pdo, 'attachment', $attachment['id'], 'create') === 1, 'attachment create audit missing');

    $listed = $repo->listEntryAttachments($entry['id'], $userId);
    fixtureAssert(count($listed) === 1, 'attachment list count');
    assertSameValue($listed[0]['id'], $attachment['id'], 'listed attachment id');

    $entriesAfterAttach = $repo->listEntries($workspace['id'], [], $userId);
    assertSameValue($entriesAfterAttach[0]['raw_text'], '-100 fuel', 'attachment should not mutate raw text');
    assertSameValue($entriesAfterAttach[0]['category_code'], 'fuel', 'attachment should not mutate category');
    assertAmount($entriesAfterAttach[0]['balance_after'], 900.0, 'attachment should not recalculate balance');
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['cash_now'], (float)$summaryBefore['cash_now'], 'attachment should not alter cash now');

    $storedPath = $harnessRoot . '/' . $attachment['file_url'];
    $deleted = $repo->deleteAttachment($attachment['id'], $userId);
    fixtureAssert($deleted['deleted'] === true, 'attachment delete flag');
    fixtureAssert($deleted['file_deleted'] === true, 'attachment file delete flag');
    clearstatcache(true, $storedPath);
    fixtureAssert(!is_file($storedPath), 'attachment file still exists after delete');
    fixtureAssert(count($repo->listEntryAttachments($entry['id'], $userId)) === 0, 'attachment list should be empty after delete');
    fixtureAssert(fixtureAuditCount($pdo, 'attachment', $attachment['id'], 'delete') === 1, 'attachment delete audit missing');
    assertAmount($repo->getWorkspaceSummary($workspace['id'], $userId)['cash_now'], (float)$summaryBefore['cash_now'], 'attachment delete should not alter cash now');

    return 'attachments create/list/delete use v2 private storage, audit closed-month metadata changes, and do not alter money';
});

runFixture($report, 'Month closure controls', function () use ($repo, $pdo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Month Closure Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $cashFlow = byFlowType($repo->listFlows($workspace['id'], $userId), 'cash');
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 fuel',
    ], $userId);

    $closed = $repo->closeMonth($workspace['id'], 2026, 7, ['comment' => 'July closed'], $userId);
    assertSameValue($closed['closure']['is_closed'], true, 'month should be closed');
    assertSameValue($closed['report']['is_closed'], true, 'closed report flag');
    assertSameValue($closed['report']['comment'], 'July closed', 'closed report comment');
    assertAmount($closed['closure']['opening_balance'], 1000.0, 'closed opening balance');
    assertAmount($closed['closure']['closing_balance'], 900.0, 'closed closing balance');
    fixtureAssert(fixtureAuditCount($pdo, 'month_closure', $closed['closure']['id'], 'month_close') === 1, 'month close audit missing');

    expectClosedMonthDecision(static fn () => $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-06',
        'raw_text' => '+15 should be correction',
    ], $userId));

    $correction = $repo->createMonthCorrection($workspace['id'], 2026, 7, [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-06',
        'raw_text' => '+15 correction for closed month',
        'reason' => 'cash count correction',
        'reference_entry_id' => $entry['id'],
        'source_type' => 'manual',
        'status' => 'recognized',
        'entry_type' => 'cash_income',
    ], $userId);
    assertSameValue($correction['entry_type'], 'correction', 'correction entry type');
    assertSameValue($correction['status'], 'corrected', 'correction status');
    assertSameValue($correction['source_type'], 'correction', 'correction source');
    assertSameValue($correction['direction'], 'in', 'correction direction');
    assertAmount($correction['amount'], 15.0, 'correction amount');
    fixtureAssert(fixtureAuditCount($pdo, 'entry', $correction['id'], 'month_correction_create') === 1, 'month correction audit missing');

    $original = null;
    foreach ($repo->listEntries($workspace['id'], [], $userId) as $candidate) {
        if ($candidate['id'] === $entry['id']) {
            $original = $candidate;
        }
    }
    fixtureAssert(is_array($original), 'original entry missing');
    assertSameValue($original['raw_text'], '-100 fuel', 'correction should not mutate original raw text');
    assertSameValue($original['entry_type'], 'cash_expense', 'correction should not mutate original type');

    $monthly = $repo->getMonthlyReport($workspace['id'], ['year' => 2026, 'month' => 7], $userId);
    assertAmount($monthly['cash_expense'], 100.0, 'correction should not change cash expense');
    assertAmount($monthly['external_cash_income'], 0.0, 'correction should not become external income');
    assertAmount($monthly['corrections'], 15.0, 'correction total');
    assertAmount($monthly['ending_cash'], 915.0, 'correction ending cash');

    $reopened = $repo->reopenMonth($workspace['id'], 2026, 7, ['comment' => ''], $userId);
    assertSameValue($reopened['closure']['is_closed'], false, 'month should reopen');
    assertSameValue($reopened['report']['is_closed'], false, 'reopened report flag');
    assertSameValue($reopened['report']['comment'], null, 'reopened report comment should be clear');
    fixtureAssert(fixtureAuditCount($pdo, 'month_closure', $closed['closure']['id'], 'month_reopen') === 1, 'month reopen audit missing');

    $afterReopen = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-07',
        'raw_text' => '+10 reopened topup',
    ], $userId);
    assertSameValue($afterReopen['entry_type'], 'cash_income', 'entry after reopen should save normally');

    return 'close/reopen/correction API semantics preserve generated formulas and explicit closed-month mutation rules';
});

runFixture($report, 'Workspace writer roles', function () use ($repo, $pdo, $userId): string {
    $viewerUserId = 15002;
    $workspace = $repo->createWorkspace([
        'name' => 'Read Only Role Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    addFixtureWorkspaceMember($pdo, $workspace['id'], $viewerUserId, 'viewer');
    $cashFlow = byFlowType($repo->listFlows($workspace['id'], $userId), 'cash');
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 fuel',
    ], $userId);
    $xlsx = fixtureCreateXlsx([
        ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ'],
        ['2026-07-05', 'fuel marina', '', '200'],
    ]);
    $import = $repo->createLegacyExcelImport($workspace['id'], [
        'file_name' => 'july-final-2026-07-01.xlsx',
        'content_base64' => base64_encode((string)file_get_contents($xlsx)),
    ], $userId);
    @unlink($xlsx);

    fixtureAssert(count($repo->listFlows($workspace['id'], $viewerUserId)) >= 2, 'viewer should retain read access');
    expectFixtureHttpError(static fn () => $repo->updateWorkspace($workspace['id'], ['name' => 'Viewer Write'], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->createFlow($workspace['id'], ['name' => 'Viewer Cash', 'type' => 'cash'], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-06',
        'raw_text' => '+10 viewer write',
    ], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->updateEntry($entry['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-200 viewer edit',
    ], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->updateEntryCategory($entry['id'], ['category_code' => 'tech_parts'], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->deleteEntry($entry['id'], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->createCategoryRule($workspace['id'], [
        'category_code' => 'fuel',
        'pattern' => 'viewer-rule',
    ], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->createLegacyExcelImport($workspace['id'], [
        'file_name' => 'viewer.xlsx',
        'content_base64' => base64_encode('viewer import'),
    ], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->acceptLegacyImport($workspace['id'], $import['import_id'], ['decision' => 'accept'], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->closeMonth($workspace['id'], 2026, 7, [], $viewerUserId), 403, 'workspace_read_only');
    expectFixtureHttpError(static fn () => $repo->createMonthCorrection($workspace['id'], 2026, 7, [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-06',
        'raw_text' => '+5 viewer correction',
    ], $viewerUserId), 403, 'workspace_read_only');

    return 'viewer can read workspace data but core mutations, imports, month close, and corrections require writer role';
});

runFixture($report, 'Dictionary training decisions', function () use ($repo, $pdo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Dictionary Training Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '0.00',
    ], $userId);
    $archive = $repo->createWorkspace([
        'name' => 'Dictionary Training Fixture Workspace Archive Raw History',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '0.00',
    ], $userId);
    $viewerUserId = $userId + 902;
    addFixtureWorkspaceMember($pdo, $workspace['id'], $viewerUserId, 'viewer');
    addFixtureWorkspaceMember($pdo, $archive['id'], $viewerUserId, 'viewer');

    $xlsx = fixtureCreateXlsx([
        ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ', 'Исполнитель', 'Приход КАРТА', 'Расход КАРТА', 'Сводные данные'],
        ['2026-07-01', 'агент', '', '50', '', '', '', ''],
        ['2026-07-01', 'доставка фильтра', '', '15', '', '', '', ''],
        ['2026-07-01', 'мой кредит', '', '1000', '', '', '', ''],
        ['2026-07-01', 'brokerage', '100', '', '', '', '', ''],
        ['2026-07-01', 'ареда яхты', '5525', '', '', '', '', ''],
    ]);
    $repo->createLegacyExcelImport($archive['id'], [
        'file_name' => 'dictionary-training-fixture-2026-07-01.xlsx',
        'content_base64' => base64_encode((string)file_get_contents($xlsx)),
    ], $userId);
    @unlink($xlsx);

    $tables = ['v2_entries', 'v2_flows', 'v2_categories', 'v2_category_rules', 'v2_actors', 'v2_audit_log', 'v2_monthly_closures', 'v2_import_sources', 'v2_import_rows', 'v2_dictionary_training_decisions'];
    $beforeQueueCounts = fixtureTableCounts($pdo, $tables);
    $queue = $repo->getDictionaryReviewQueue($workspace['id'], ['limit' => 50, 'examples' => 10], $userId);
    $afterQueueCounts = fixtureTableCounts($pdo, $tables);
    assertSameValue($queue['workspace_id'], $archive['id'], 'dictionary training queue should route to raw archive');
    assertSameValue($queue['source_workspace_id'], $workspace['id'], 'dictionary training queue source workspace');
    assertSameValue($beforeQueueCounts, $afterQueueCounts, 'dictionary queue remains read-only before decisions');

    $examples = [];
    foreach ($queue['groups'] as $group) {
        foreach ($group['examples'] as $example) {
            $examples[mb_strtolower((string)$example['description'])] = $example;
        }
    }
    foreach (['агент', 'доставка фильтра', 'мой кредит', 'brokerage', 'ареда яхты'] as $description) {
        fixtureAssert(isset($examples[$description]), "missing dictionary training example {$description}");
    }

    $rulesBefore = fixtureTableCounts($pdo, ['v2_category_rules'])['v2_category_rules'];
    $agentDecision = $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['агент']['source']['source_row_id'],
        'decision_type' => 'approve_existing_guess_local',
        'category_code' => 'current_boat_expenses',
        'pattern' => 'агент',
        'pattern_type' => 'keyword',
        'language' => 'ru',
        'weight' => 10,
        'note' => 'fixture local approval',
    ], $userId);
    assertSameValue($agentDecision['decision_type'], 'approve_existing_guess_local', 'agent decision type');
    assertSameValue($agentDecision['target_category_code'], 'current_boat_expenses', 'agent decision target category');
    assertSameValue($agentDecision['review_reason'], 'weak_only', 'agent decision preserves review reason');
    fixtureAssert(isset($agentDecision['category_rule']['id']), 'agent decision should create local category rule');
    fixtureAssert(fixtureAuditCount($pdo, 'dictionary_training_decision', $agentDecision['id'], 'create') === 1, 'agent decision create audit missing');
    fixtureAssert(fixtureAuditCount($pdo, 'category_rule', $agentDecision['category_rule_id'], 'create') === 1, 'agent category rule audit missing');

    $duplicateAgentDecision = $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['агент']['source']['source_row_id'],
        'decision_type' => 'approve_existing_guess_local',
        'category_code' => 'current_boat_expenses',
        'pattern' => 'агент',
        'pattern_type' => 'keyword',
        'language' => 'ru',
        'weight' => 10,
    ], $userId);
    assertSameValue($duplicateAgentDecision['id'], $agentDecision['id'], 'duplicate source row should update existing decision');
    assertSameValue($duplicateAgentDecision['category_rule_id'], $agentDecision['category_rule_id'], 'duplicate source row should reuse existing rule');
    assertSameValue(fixtureTableCounts($pdo, ['v2_category_rules'])['v2_category_rules'], $rulesBefore + 1, 'duplicate source row should not create another category rule');
    fixtureAssert(fixtureAuditCount($pdo, 'dictionary_training_decision', $agentDecision['id'], 'update') === 1, 'agent decision update audit missing');

    $deliveryReject = $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['доставка фильтра']['source']['source_row_id'],
        'decision_type' => 'reject_training',
        'note' => 'fixture reject mixed context',
    ], $userId);
    assertSameValue($deliveryReject['decision_type'], 'reject_training', 'delivery reject decision type');
    assertSameValue($deliveryReject['category_rule_id'], null, 'rejected decision must not create category rule');
    assertSameValue(fixtureTableCounts($pdo, ['v2_category_rules'])['v2_category_rules'], $rulesBefore + 1, 'reject decision should not create category rule');

    expectFixtureHttpError(static fn () => $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['мой кредит']['source']['source_row_id'],
        'decision_type' => 'approve_existing_guess_local',
        'category_code' => 'current_boat_expenses',
        'pattern' => 'кредит',
    ], $userId), 422, 'dictionary_training_blocked');
    expectFixtureHttpError(static fn () => $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['brokerage']['source']['source_row_id'],
        'decision_type' => 'approve_existing_guess_local',
        'category_code' => 'commercial_income',
        'pattern' => 'brokerage',
    ], $userId), 422, 'dictionary_training_blocked');

    $universalCandidate = $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['ареда яхты']['source']['source_row_id'],
        'decision_type' => 'propose_universal_candidate',
        'category_code' => 'commercial_income',
        'pattern' => 'ареда яхты',
        'note' => 'fixture candidate only',
    ], $userId);
    assertSameValue($universalCandidate['decision_type'], 'propose_universal_candidate', 'universal candidate decision type');
    assertSameValue($universalCandidate['category_rule_id'], null, 'universal candidate must not create category rule');
    expectFixtureHttpError(static fn () => $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['ареда яхты']['source']['source_row_id'],
        'decision_type' => 'promote_universal',
        'category_code' => 'commercial_income',
        'pattern' => 'ареда яхты',
    ], $userId), 422, 'universal_promotion_not_supported');

    expectFixtureHttpError(static fn () => $repo->decideDictionaryTraining($workspace['id'], [
        'source_row_id' => $examples['агент']['source']['source_row_id'],
        'decision_type' => 'reject_training',
    ], $viewerUserId), 403, 'workspace_read_only');

    $decisions = $repo->listDictionaryTrainingDecisions($workspace['id'], ['limit' => 10], $userId);
    fixtureAssert(count($decisions) === 3, 'dictionary training decision list count mismatch');
    assertSameValue(fixtureTableCounts($pdo, ['v2_entries', 'v2_flows', 'v2_actors', 'v2_monthly_closures'])['v2_entries'], $beforeQueueCounts['v2_entries'], 'dictionary decisions must not create entries');

    return 'dictionary training decisions are explicit, audited, local by approval only, and blocked rows cannot train rules';
});

runFixture($report, 'Card plus semantics', function () use ($repo, $workspace, $cardFlow, $userId): string {
    $manual = $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+100 manual card plus',
        'amount' => '100.00',
        'source_type' => 'manual',
        'status' => 'recognized',
    ], $userId);
    $correction = $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+25 card correction',
        'source_type' => 'correction',
    ], $userId);

    assertSameValue($manual['sign'], '+', 'manual card plus sign');
    assertSameValue($manual['amount'], null, 'manual card plus amount');
    assertSameValue($manual['direction'], 'none', 'manual card plus direction');
    assertSameValue($manual['entry_type'], 'unrecognized', 'manual card plus type');
    assertSameValue($manual['status'], 'unrecognized', 'manual card plus status');

    assertSameValue($correction['sign'], '+', 'card correction sign');
    assertAmount($correction['amount'], 25.0, 'card correction amount');
    assertSameValue($correction['direction'], 'in', 'card correction direction');
    assertSameValue($correction['entry_type'], 'correction', 'card correction type');
    assertSameValue($correction['status'], 'corrected', 'card correction status');

    return 'manual Card + is unrecognized/null amount; correction Card + is accepted as corrected';
});

runFixture($report, 'Parse preview', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $before = count($repo->listEntries($workspace['id'], [], $userId));
    $preview = $repo->previewEntryParse($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-1 preview only',
    ], $userId);
    $after = count($repo->listEntries($workspace['id'], [], $userId));

    assertSameValue($preview['will_save'], false, 'preview will_save');
    assertSameValue($preview['entry_type'], 'cash_expense', 'preview entry type');
    assertAmount($preview['amount'], 1.0, 'preview amount');
    assertSameValue($after, $before, 'preview should not persist an entry');

    $guestCash = $repo->previewEntryParse($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 передал ЛВ',
    ], $userId);
    assertSameValue($guestCash['category_code'], 'guest_cash_issued', 'guest cash issued preview category');

    $acPart = $repo->previewEntryParse($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 контролька кондея',
    ], $userId);
    assertSameValue($acPart['category_code'], 'tech_parts', 'AC control preview category');

    $iphone = $repo->previewEntryParse($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-100 айфон',
    ], $userId);
    assertSameValue($iphone['category_code'], 'guest_trip_support', 'iPhone preview category');
    fixtureAssert($iphone['status'] !== 'other_review', 'iPhone preview should not stay other review');

	    foreach ([
	        ['-100 доп муринг с северной стороны', 'berth', 'mooring preview category'],
	        ['-100 материалы по тику', 'tech_parts', 'teak materials preview category'],
	        ['-100 Безопастность плавания сет', 'tech_parts', 'safety navigation set preview category'],
	        ['-100 отправка таксы в траст компанию сша', 'admin_legal', 'trust company tax preview category'],
	        ['-100 air serbia', 'transport_expenses', 'airline preview category'],
	        ['-100 запрака авто', 'transport_expenses', 'car refuel preview category'],
	        ['-100 заправка арендованной авто', 'transport_expenses', 'rental car refuel preview category'],
	        ['-100 парковка сплит', 'transport_expenses', 'parking transport preview category'],
	        ['-100 курьер подгорица', 'transport_expenses', 'courier transport preview category'],
	        ['-100 шкампи', 'provisions', 'seafood preview category'],
	        ['-100 лангустины', 'provisions', 'langoustines preview category'],
	        ['-100 осминоги', 'provisions', 'octopus preview category'],
	        ['-100 сиропы', 'provisions', 'syrups preview category'],
	        ['-100 сладости', 'provisions', 'sweets preview category'],
	        ['-100 вода', 'provisions', 'plain water provisions preview category'],
	        ['-100 вода на лодку', 'provisions', 'boat water provisions preview category'],
	        ['-100 кофемашина', 'interior', 'kitchen appliance preview category'],
	        ['-100 блендер', 'interior', 'blender interior preview category'],
	        ['-100 пледы', 'interior', 'blankets interior preview category'],
	        ['-100 печка-микроволновка', 'interior', 'microwave oven preview category'],
	        ['-100 продление сайта клаудии', 'media_comms', 'Claudia site renewal preview category'],
	        ['-100 платный годовой прогноз погоды', 'media_comms', 'weather forecast subscription preview category'],
	        ['-100 иви', 'media_comms', 'ivi media preview category'],
	        ['-100 сувениры наталья', 'representation_expenses', 'souvenirs with actor preview category'],
	        ['-100 подарки службам нг', 'representation_expenses', 'service gifts preview category'],
	        ['-100 украшения др', 'representation_expenses', 'birthday decorations representation preview category'],
	        ['-100 обед с агентом', 'representation_expenses', 'business meal with agent preview category'],
	        ['-100 снасти, маска', 'guest_trip_support', 'fishing gear and mask guest support preview category'],
	        ['-100 зарядка шефу', 'guest_trip_support', 'chef charger guest support preview category'],
	        ['-100 продолжение тур регистрации Данил', 'admin_legal', 'tour registration with actor preview category'],
	        ['-100 разрешение на вход в грецию', 'admin_legal', 'Greece entry permit preview category'],
	        ['-100 обновление морских сертифиткатов', 'admin_legal', 'marine certificates typo preview category'],
	        ['-100 печати лодки и фирмы', 'admin_legal', 'boat and company stamps preview category'],
	        ['-100 просрочка нахождения в турции женя', 'admin_legal', 'overstay admin preview category'],
	        ['-100 черные танки', 'service_water', 'black tanks service preview category'],
	        ['-100 откачка грязных вод', 'service_water', 'dirty water pumpout preview category'],
	        ['-100 токарь', 'service_water', 'turner service preview category'],
	        ['-100 петля хододильник', 'tech_parts', 'fridge hinge typo preview category'],
	        ['-100 сантехника', 'tech_parts', 'plumbing parts preview category'],
	        ['-100 Батарея для старой рст', 'tech_parts', 'radio battery preview category'],
	        ['-100 маркеры цепи', 'tech_parts', 'chain markers preview category'],
	        ['-100 сикафлекс', 'tech_parts', 'sikaflex preview category'],
	        ['-100 щетка для лодки', 'cleaning', 'boat brush cleaning preview category'],
	        ['-100 моющее средство палуба', 'cleaning', 'deck detergent preview category'],
	        ['-100 хоз товары', 'current_boat_expenses', 'household goods current boat preview category'],
	        ['-100 инвентарь', 'current_boat_expenses', 'generic inventory current boat preview category'],
	        ['-100 банковские проценты за переводы', 'current_boat_expenses', 'bank transfer fees preview category'],
	        ['-100 принтер на лодку', 'current_boat_expenses', 'boat printer current boat preview category'],
	        ['-100 александр места в самолете', 'transport_expenses', 'flight seat selection should be transport expense'],
	        ['-100 шампуни и чистящие средства', 'cleaning', 'cleaning shampoo wording should be cleaning expense'],
	        ['-100 шнуры телефон', 'media_comms', 'phone cords media preview category'],
	        ['-100 работник в помощь', 'crew', 'temporary worker preview category'],
	    ] as [$rawText, $categoryCode, $label]) {
	        $case = $repo->previewEntryParse($workspace['id'], [
	            'flow_id' => $cashFlow['id'],
            'date' => '2026-07-05',
            'raw_text' => $rawText,
        ], $userId);
	        assertSameValue($case['category_code'], $categoryCode, $label);
	    }

	    $actorCategory = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 продолжение тур регистрации Данил',
	    ], $userId);
	    assertSameValue($actorCategory['category_code'], 'admin_legal', 'actor context should keep transaction category');
	    assertSameValue(semanticMarkerHas($actorCategory, 'actor_context'), true, 'actor context marker should stay visible');

	    $agentMeal = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 обед с агентом',
	    ], $userId);
	    assertSameValue($agentMeal['category_code'], 'representation_expenses', 'agent meal should be representation expense');
	    assertSameValue(semanticMarkerHas($agentMeal, 'weak_dictionary_context'), false, 'strong agent meal should not get weak marker');
	    assertSameValue($agentMeal['review_reason'], null, 'strong agent meal should not need review');
	    assertAmount($agentMeal['confidence'], 0.92, 'strong agent meal confidence');
	    assertSameValue($agentMeal['blockers'], [], 'strong agent meal blockers');

	    $nonYacht = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 Аудио система для РФ - задаток',
	    ], $userId);
	    assertSameValue($nonYacht['category_code'], null, 'non-yacht/personal context should not force yacht category');
	    assertSameValue(semanticMarkerHas($nonYacht, 'non_yacht_or_personal'), true, 'non-yacht/personal marker should stay visible');
	    assertSameValue($nonYacht['review_reason'], 'blocked_by_personal', 'non-yacht/personal review reason');
	    assertAmount($nonYacht['confidence'], 0.20, 'non-yacht/personal confidence');
	    assertSameValue(classificationBlockerHas($nonYacht, 'non_yacht_or_personal'), true, 'non-yacht/personal blocker');

	    $mixedNonYacht = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 масло на лодку и для отправки в РФ',
	    ], $userId);
	    assertSameValue(semanticMarkerHas($mixedNonYacht, 'non_yacht_or_personal'), true, 'mixed Russia shipping marker should stay visible');

	    $privateSettlement = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 оплатил свои нужды с карты, положил кеш',
	    ], $userId);
	    assertSameValue($privateSettlement['category_code'], null, 'private card/cash settlement should not force category');
	    assertSameValue(semanticMarkerHas($privateSettlement, 'money_movement'), true, 'private settlement marker should stay visible');
	    assertSameValue($privateSettlement['review_reason'], 'private_money_movement', 'private settlement review reason');
	    assertAmount($privateSettlement['confidence'], 0.20, 'private settlement confidence');
	    assertSameValue(classificationBlockerHas($privateSettlement, 'money_movement'), true, 'private settlement blocker');
	    assertSameValue($privateSettlement['accounting_section'], 'lower_accounting', 'private settlement lower accounting section');
	    assertSameValue($privateSettlement['settlement_counterparty'], 'Private/self settlement', 'private settlement counterparty');

	    $weakAgent = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 агент',
	    ], $userId);
	    assertSameValue($weakAgent['category_code'], 'current_boat_expenses', 'weak agent category still suggested');
	    assertSameValue(semanticMarkerHas($weakAgent, 'weak_dictionary_context'), true, 'weak agent marker should stay visible');
	    assertSameValue($weakAgent['review_reason'], 'weak_only', 'weak agent review reason');
	    assertAmount($weakAgent['confidence'], 0.48, 'weak agent confidence');
	    assertSameValue(classificationSignalHas($weakAgent, 'marker', 'weak_dictionary_context'), true, 'weak agent matched signal');
	    assertSameValue($weakAgent['blockers'], [], 'weak agent blockers');

	    $deliveryPart = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 доставка фильтра',
	    ], $userId);
	    assertSameValue($deliveryPart['category_code'], 'tech_parts', 'part noun should dominate delivery context');
	    assertSameValue(semanticMarkerHas($deliveryPart, 'mixed_dictionary_context'), true, 'mixed delivery part marker should stay visible');
	    assertSameValue($deliveryPart['review_reason'], 'mixed_context', 'mixed delivery part review reason');
	    assertAmount($deliveryPart['confidence'], 0.64, 'mixed delivery part confidence');
	    assertSameValue(classificationSignalHas($deliveryPart, 'marker', 'mixed_dictionary_context'), true, 'mixed delivery part matched signal');
	    assertSameValue($deliveryPart['blockers'], [], 'mixed delivery part blockers');

	    $deliverySpareParts = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 доставка запчастей',
	    ], $userId);
	    assertSameValue($deliverySpareParts['category_code'], 'tech_parts', 'spare parts noun should dominate delivery context');

	    $personalFuel = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 Порше топливо',
	    ], $userId);
	    assertSameValue(semanticMarkerHas($personalFuel, 'non_yacht_or_personal'), true, 'personal fuel marker should stay visible');
	    assertSameValue($personalFuel['review_reason'], 'blocked_by_personal', 'personal fuel review reason');
	    assertAmount($personalFuel['confidence'], 0.20, 'personal fuel confidence');
	    assertSameValue(classificationBlockerHas($personalFuel, 'non_yacht_or_personal'), true, 'personal fuel blocker');

	    $debtGarage = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-250 долг за гараж',
	    ], $userId);
	    assertSameValue(semanticMarkerHas($debtGarage, 'debt_or_return'), true, 'debt garage marker should stay visible');
	    assertSameValue($debtGarage['review_reason'], null, 'debt garage should not need review when berth context is clear');
	    assertAmount($debtGarage['confidence'], 0.92, 'debt garage confidence');
	    assertSameValue(classificationBlockerHas($debtGarage, 'debt_or_return'), false, 'debt garage should not be blocked when berth context is clear');
	    assertSameValue($debtGarage['accounting_section'], 'operational', 'debt garage should remain operational when it has concrete boat-expense context');

	    $customsDebt = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-150 долг таможне дьюти',
	    ], $userId);
	    assertSameValue($customsDebt['category_code'], 'admin_legal', 'customs debt should stay admin/legal operational category');
	    assertSameValue($customsDebt['review_reason'], null, 'customs debt should not need lower-accounting review');
	    assertSameValue($customsDebt['accounting_section'], 'operational', 'customs debt should remain operational');

	    $unclearCommercial = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '+750 агентские',
	    ], $userId);
	    assertSameValue($unclearCommercial['category_code'], null, 'unclear commercial wording should not become commercial income');
	    assertSameValue($unclearCommercial['review_reason'], 'commercial_income_unclear', 'unclear commercial review reason');
	    assertAmount($unclearCommercial['confidence'], 0.30, 'unclear commercial confidence');
	    assertSameValue(classificationBlockerHas($unclearCommercial, 'missing_yacht_charter_phrase'), true, 'unclear commercial blocker');

	    $charterIncome = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '+5525 ареда яхты',
	    ], $userId);
	    assertSameValue($charterIncome['category_code'], 'commercial_income', 'yacht rental should be commercial income');
	    assertSameValue($charterIncome['review_reason'], null, 'yacht rental should not need review');
	    assertAmount($charterIncome['confidence'], 0.92, 'yacht rental confidence');
	    assertSameValue($charterIncome['blockers'], [], 'yacht rental blockers');

	    $accountTransfer = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 превод со счета на карту',
	    ], $userId);
	    assertSameValue($accountTransfer['category_code'], null, 'account-to-card transfer should not force category');
	    assertSameValue(semanticMarkerHas($accountTransfer, 'money_movement'), true, 'account-to-card transfer marker should stay visible');

	    $accountableTypo = $repo->previewEntryParse($workspace['id'], [
	        'flow_id' => $cashFlow['id'],
	        'date' => '2026-07-05',
	        'raw_text' => '-100 володя пот отчет',
	    ], $userId);
	    assertSameValue(semanticMarkerHas($accountableTypo, 'debt_or_return'), true, 'accountable cash typo marker should stay visible');

	    return 'parse preview returns normalized output and new Claudia Z rules without saving';
	});

runFixture($report, 'Quick notes Smith migration', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $before = count($repo->listEntries($workspace['id'], [], $userId));
    $note = $repo->createQuickNote($workspace['id'], [
        'note_date' => '2026-08-13',
        'raw_text' => "Заметка от 13.08.26\n-89 Старлинк\n-27 продукты",
    ], $userId);
    assertSameValue($note['status'], 'draft', 'quick note starts as draft');

    $preview = $repo->previewQuickNoteConversion($workspace['id'], $note['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-08-13',
    ], $userId);
    assertSameValue(count($preview['items']), 2, 'quick note preview item count');
    assertSameValue($preview['items'][0]['preview']['category_code'], 'media_comms', 'Smith quick note Starlink category');
    assertAmount($preview['items'][0]['preview']['amount'], 89.0, 'Smith quick note Starlink amount');
    assertSameValue($preview['items'][0]['duplicate_candidates'], [], 'first quick note should not have duplicates');

    $converted = $repo->convertQuickNote($workspace['id'], $note['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-08-13',
        'items' => [
            ['line_index' => 0, 'enabled' => true],
            ['line_index' => 1, 'enabled' => false],
        ],
    ], $userId);
    assertSameValue($converted['note']['status'], 'converted', 'quick note converted status');
    assertSameValue(count($converted['entries']), 1, 'quick note selected entries count');
    assertSameValue($converted['entries'][0]['raw_text'], '-89 Старлинк', 'quick note converted raw text');
    assertSameValue($converted['entries'][0]['source_type'], 'assistant', 'quick note source type');
    assertSameValue($converted['entries'][0]['source_id'], $note['id'], 'quick note source id');

    $after = count($repo->listEntries($workspace['id'], [], $userId));
    assertSameValue($after, $before + 1, 'disabled quick note line should not create entry');
    expectFixtureHttpError(function () use ($repo, $workspace, $cashFlow, $note, $userId): void {
        $repo->previewQuickNoteConversion($workspace['id'], $note['id'], [
            'flow_id' => $cashFlow['id'],
            'date' => '2026-08-13',
        ], $userId);
    }, 409, 'quick_note_already_converted');
    expectFixtureHttpError(function () use ($repo, $workspace, $cashFlow, $note, $userId): void {
        $repo->convertQuickNote($workspace['id'], $note['id'], [
            'flow_id' => $cashFlow['id'],
            'date' => '2026-08-13',
            'items' => [
                ['line_index' => 0, 'enabled' => true],
            ],
        ], $userId);
    }, 409, 'quick_note_already_converted');
    expectFixtureHttpError(function () use ($repo, $workspace, $note, $userId): void {
        $repo->updateQuickNote($workspace['id'], $note['id'], [
            'note_date' => '2026-08-14',
            'raw_text' => '-999 измененная перенесенная заметка',
        ], $userId);
    }, 409, 'quick_note_already_converted');
    expectFixtureHttpError(function () use ($repo, $workspace, $note, $userId): void {
        $repo->deleteQuickNote($workspace['id'], $note['id'], $userId);
    }, 409, 'quick_note_already_converted');
    $lockedNote = null;
    foreach ($repo->listQuickNotes($workspace['id'], [], $userId) as $candidateNote) {
        if (($candidateNote['id'] ?? null) === $note['id']) {
            $lockedNote = $candidateNote;
            break;
        }
    }
    assertSameValue($lockedNote['raw_text'] ?? null, "Заметка от 13.08.26\n-89 Старлинк\n-27 продукты", 'converted quick note source text is immutable');
    assertSameValue($lockedNote['status'] ?? null, 'converted', 'converted quick note stays visible as source');
    $afterDuplicateAttempt = count($repo->listEntries($workspace['id'], [], $userId));
    assertSameValue($afterDuplicateAttempt, $after, 'converted quick note must not create duplicate entries');

    $duplicateNote = $repo->createQuickNote($workspace['id'], [
        'note_date' => '2026-08-13',
        'raw_text' => '-89 Старлинк',
    ], $userId);
    $duplicatePreview = $repo->previewQuickNoteConversion($workspace['id'], $duplicateNote['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-08-13',
    ], $userId);
    fixtureAssert(count($duplicatePreview['items'][0]['duplicate_candidates']) >= 1, 'duplicate quick note candidate missing');

    return 'quick notes can be parsed by Smith, selectively migrated, linked to source note, and duplicate candidates are exposed';
});

runFixture($report, 'Generated monthly reports', function () use ($repo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Generated Report Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $flows = $repo->listFlows($workspace['id'], $userId);
    $cashFlow = byFlowType($flows, 'cash');
    $cardFlow = byFlowType($flows, 'card');

    $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-06-30',
        'raw_text' => '+200 prior month topup',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-01',
        'raw_text' => '+300 private topup',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-02',
        'raw_text' => '+5000 charter deposit',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-03',
        'raw_text' => '-200 fuel',
    ], $userId);
    $other = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-04',
        'raw_text' => '-50 какая-то штука',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-1000 снял с карты',
        'category_code' => 'cash_topup_from_card',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '+1000 снял с карты',
        'category_code' => 'cash_topup_from_card',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cardFlow['id'],
        'date' => '2026-07-06',
        'raw_text' => '-60 Netflix',
    ], $userId);
    $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-07',
        'raw_text' => '250 ignored no sign',
        'amount' => '250.00',
        'status' => 'recognized',
    ], $userId);
    $repo->closeMonthForFixture($workspace['id'], 2026, 7, $userId);

    $monthly = $repo->getMonthlyReport($workspace['id'], ['year' => 2026, 'month' => 7], $userId);
    assertSameValue($monthly['month_key'], '2026-07', 'monthly report key');
    assertSameValue($monthly['is_closed'], true, 'monthly report closed flag');
    assertAmount($monthly['opening_cash'], 1200.0, 'monthly opening cash');
    assertAmount($monthly['external_cash_income'], 300.0, 'monthly external cash income');
    assertAmount($monthly['commercial_income'], 5000.0, 'monthly commercial income');
    assertAmount($monthly['cash_expense'], 250.0, 'monthly cash expense');
    assertAmount($monthly['card_expense'], 1060.0, 'monthly card expense');
    assertAmount($monthly['cash_topup_from_card_card_side'], 1000.0, 'monthly card topup side');
    assertAmount($monthly['cash_topup_from_card_cash_side'], 1000.0, 'monthly cash topup side');
    assertAmount($monthly['other_expenses'], 50.0, 'monthly other expenses');
    assertAmount($monthly['ending_cash'], 7250.0, 'monthly ending cash');
    assertSameValue($monthly['counts']['entries'], 8, 'monthly entries count');
    assertSameValue($monthly['counts']['counted'], 7, 'monthly counted count');
    assertSameValue($monthly['counts']['unrecognized'], 1, 'monthly unrecognized count');
    assertSameValue($monthly['counts']['other_review'], 1, 'monthly other review count');

    $matrix = $repo->getCategoryMatrixReport($workspace['id'], ['year' => 2026], $userId);
    $rows = [];
    foreach ($matrix['rows'] as $row) {
        $rows[$row['category_code']] = $row;
    }

    assertAmount($rows['fuel']['months']['7'], 200.0, 'category matrix fuel July');
    assertAmount($rows['commercial_income']['months']['7'], 5000.0, 'category matrix commercial July');
    assertAmount($rows['non_commercial_income']['months']['7'], 300.0, 'category matrix non-commercial income July');
    assertAmount($rows['cash_topup_from_card']['months']['7'], 2000.0, 'category matrix topup July');
    assertAmount($rows['other']['months']['7'], 50.0, 'category matrix other July');
    assertAmount($rows['media_comms']['months']['7'], 60.0, 'category matrix media July');
    fixtureAssert(isset($rows['cash_topup_from_card']['breakdown']['7']['card:out']), 'category matrix missing card topup side');
    fixtureAssert(isset($rows['cash_topup_from_card']['breakdown']['7']['cash:in']), 'category matrix missing cash topup side');

    $otherReport = $repo->getOtherReviewReport($workspace['id'], $userId);
    assertSameValue($otherReport['count'], 1, 'other review report count');
    assertAmount($otherReport['total'], 50.0, 'other review report total');
    assertSameValue($otherReport['entries'][0]['id'], $other['id'], 'other review report entry');

    return 'monthly report, category matrix, and other-review report are generated from operational entries';
});

runFixture($report, 'One-file legacy Excel import', function () use ($repo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Legacy Import Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '1000.00',
    ], $userId);
    $xlsx = fixtureCreateXlsx([
        ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ', 'Исполнитель', 'Приход КАРТА', 'Расход КАРТА', 'Сводные данные'],
        ['', 'private topup', '300', '', '', '', '', ''],
        ['', 'fuel marina', '', '200', '', '', '', ''],
        ['2026-07-02', 'charter deposit', '5000', '', '', '', '', ''],
        ['2026-07-03', 'снял с карты', '', '', '', '', '1000', ''],
        ['', 'снял с карты', '1000', '', '', '', '', ''],
        ['2026-07-04', 'Netflix', '', '', '', '', '60', ''],
        ['2026-07-05', 'какая-то штука', '', '50', '', '', '', ''],
        ['2026-07-06', 'card refund', '', '', '', '25', '', ''],
        ['2026-07-07', 'информационная строка', '', '', '', '', '', ''],
        ['2026-07-08', 'ambiguous two money columns', '100', '50', '', '', '', ''],
        ['2026-07-01', 'fuel marina', '', '200', '', '', '', ''],
        ['2026-07-31', 'Сводные данные', '6300', '250', '', '25', '1060', 'summary'],
    ]);

    $import = $repo->createLegacyExcelImport($workspace['id'], [
        'file_name' => 'july-final-2026-07-01.xlsx',
        'file_id' => 'fixture-file-001',
        'content_base64' => base64_encode((string)file_get_contents($xlsx)),
    ], $userId);
    @unlink($xlsx);

    assertSameValue($import['include_decision'], 'included', 'import include decision');
    assertSameValue($import['rows_scanned'], 12, 'import rows scanned');
    assertSameValue($import['rows_parsed'], 9, 'import rows parsed');
    assertSameValue($import['rows_unrecognized'], 1, 'import rows unrecognized');
    assertSameValue(count($import['duplicate_suspects']), 1, 'import duplicate suspect count');
    assertAmount($import['source_summary_totals']['cash_income'], 6300.0, 'import source summary cash income');

    $accepted = $repo->acceptLegacyImport($workspace['id'], $import['import_id'], ['decision' => 'accept'], $userId);
    assertSameValue($accepted['entries_created'], 9, 'accepted import entries created');
    assertSameValue($accepted['rows_unrecognized'], 1, 'accepted import rows unrecognized');
    assertAmount($accepted['normalized_totals']['cash_income'], 6300.0, 'accepted import cash income');
    assertAmount($accepted['normalized_totals']['cash_expense'], 250.0, 'accepted import cash expense');
    assertAmount($accepted['normalized_totals']['card_income'], 25.0, 'accepted import card income');
    assertAmount($accepted['normalized_totals']['card_expense'], 1060.0, 'accepted import card expense');
    assertAmount($accepted['source_total_comparison']['cash_income'], 0.0, 'accepted import cash income comparison');
    $dateSources = array_fill_keys(array_map(static fn (array $trace): ?string => $trace['date_source'] ?? null, $accepted['row_traces']), true);
    fixtureAssert(isset($dateSources['filename_date']), 'import fixture missing filename date provenance');
    fixtureAssert(isset($dateSources['inherited_previous_row_date']), 'import fixture missing inherited date provenance');
    fixtureAssert(isset($dateSources['row_date']), 'import fixture missing row date provenance');
    $unrecognizedTrace = null;
    foreach ($accepted['row_traces'] as $trace) {
        if (($trace['parse_status'] ?? null) === 'unrecognized') {
            $unrecognizedTrace = $trace;
            break;
        }
    }
    fixtureAssert(is_array($unrecognizedTrace), 'import fixture missing unrecognized row trace');
    assertSameValue($unrecognizedTrace['parse_notes'], 'multiple money columns in one row', 'unrecognized row parse notes');

    $entries = $repo->listEntries($workspace['id'], ['year' => 2026, 'month' => 7], $userId);
    fixtureAssert(count($entries) === 9, 'accepted import entries are not visible');
    $linked = 0;
    $duplicates = 0;
    $cardIncome = 0;
    foreach ($entries as $entry) {
        if ($entry['source_id'] === $import['import_id'] && $entry['source_row_id'] !== null) {
            $linked++;
        }
        if ($entry['status'] === 'duplicate_suspect') {
            $duplicates++;
        }
        if ($entry['entry_type'] === 'card_income') {
            $cardIncome++;
        }
    }
    assertSameValue($linked, 9, 'imported entries source links');
    assertSameValue($duplicates, 1, 'duplicate suspect entries');
    assertSameValue($cardIncome, 1, 'imported card income entry');

    $monthly = $repo->getMonthlyReport($workspace['id'], ['year' => 2026, 'month' => 7], $userId);
    assertAmount($monthly['external_cash_income'], 300.0, 'import monthly external cash income');
    assertAmount($monthly['commercial_income'], 5000.0, 'import monthly commercial income');
    assertAmount($monthly['cash_topup_from_card_cash_side'], 1000.0, 'import monthly cash topup side');
    assertAmount($monthly['cash_expense'], 250.0, 'import monthly cash expense');
    assertAmount($monthly['card_expense'], 1060.0, 'import monthly card expense');
    assertAmount($monthly['ending_cash'], 7050.0, 'import monthly ending cash');
    fixtureAssert(count($monthly['source_files']) === 1, 'monthly report source file trace missing');

    $excluded = $repo->createLegacyExcelImport($workspace['id'], [
        'file_name' => 'draft legacy import.xlsx',
        'content_base64' => base64_encode('not parsed because excluded'),
    ], $userId);
    assertSameValue($excluded['include_decision'], 'excluded_by_title_marker', 'excluded import marker');
    assertSameValue($excluded['rows_scanned'], 0, 'excluded import should not scan rows');

    return 'one xlsx import preserves traceability, review totals, duplicate suspects, exclusion markers, and generated reports';
});

runFixture($report, 'Chronology legacy Excel import', function () use ($repo, $userId): string {
    $workspace = $repo->createWorkspace([
        'name' => 'Chronology Import Fixture Workspace',
        'type' => 'yacht',
        'currency' => 'EUR',
        'locale' => 'ru',
        'opening_cash' => '0.00',
    ], $userId);
    $xlsx = fixtureCreateXlsx([
        ['Финансовый отчет — хронология', '', '', '', '', ''],
        ['', '', '', '', '', ''],
        ['№', 'Дата', 'Описание', 'Приход', 'Расход', 'Остаток'],
        ['1', '', 'переходящий остаток', '4205', '', '4205'],
        ['2', '', 'Netflix hipo apple', '', '20', '4185'],
        ['3', '', 'информационная строка, не считается', '', '', ''],
        ['4', '2026-06-03', 'от Данила', '5000', '', '9185'],
        ['5', '', 'заправка 1541 л', '', '2622', '6563'],
        ['', '', 'Итоговый остаток', '', '', '6563'],
    ]);

    $import = $repo->createLegacyExcelImport($workspace['id'], [
        'file_name' => '15.06.2026.xlsx',
        'file_id' => 'fixture-chronology-001',
        'content_base64' => base64_encode((string)file_get_contents($xlsx)),
    ], $userId);
    @unlink($xlsx);

    assertSameValue($import['include_decision'], 'included', 'chronology import include decision');
    assertSameValue($import['rows_scanned'], 6, 'chronology rows scanned');
    assertSameValue($import['rows_parsed'], 3, 'chronology rows parsed');
    assertAmount($import['normalized_totals']['cash_income'], 5000.0, 'chronology preview cash income');
    assertAmount($import['normalized_totals']['cash_expense'], 2642.0, 'chronology preview cash expense');
    fixtureAssert(in_array('2026-06', $import['months_covered'], true), 'chronology filename date month missing');

    $accepted = $repo->acceptLegacyImport($workspace['id'], $import['import_id'], ['decision' => 'accept'], $userId);
    assertSameValue($accepted['entries_created'], 3, 'chronology accepted entries created');
    assertAmount($accepted['normalized_totals']['cash_income'], 5000.0, 'chronology accepted cash income');
    assertAmount($accepted['normalized_totals']['cash_expense'], 2642.0, 'chronology accepted cash expense');

    return 'chronology-style xlsx skips report title rows, maps generic income/expense, ignores opening/info rows, and parses dd.mm.yyyy filename dates';
});

$report->print();
exit($report->hasFailures() ? 1 : 0);
