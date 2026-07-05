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

foreach (['commercial_income', 'media_comms', 'cash_topup_from_card', 'other', 'fuel', 'tech_parts'] as $categoryCode) {
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

runFixture($report, 'Fixture 6 - Other expenses', function () use ($repo, $workspace, $cashFlow, $userId): string {
    $entry = $repo->createEntry($workspace['id'], [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => '-180 какая-то штука',
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
    fixtureAssert(entryIsVisible($repo, $workspace['id'], $userId, $entry['id']), 'other expense row is not visible in feed');
    $queue = $repo->listOtherExpenseQueue($workspace['id'], $userId);
    fixtureAssert(count($queue) === 1, 'other expense queue count mismatch');
    assertSameValue($queue[0]['id'], $entry['id'], 'other expense queue entry mismatch');
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
runFixture($report, 'Fixture 9 - Month insertion recalculation', function () use ($repo, $userId): string {
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

    return 'inserting a middle cash row recalculates later balance_after values';
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

    return 'closed month edit/category/delete and open-to-closed date moves are blocked without silent mutation';
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

    return 'parse preview returns normalized output without saving';
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
    assertSameValue($import['rows_scanned'], 11, 'import rows scanned');
    assertSameValue($import['rows_parsed'], 9, 'import rows parsed');
    assertSameValue(count($import['duplicate_suspects']), 1, 'import duplicate suspect count');
    assertAmount($import['source_summary_totals']['cash_income'], 6300.0, 'import source summary cash income');

    $accepted = $repo->acceptLegacyImport($workspace['id'], $import['import_id'], ['decision' => 'accept'], $userId);
    assertSameValue($accepted['entries_created'], 9, 'accepted import entries created');
    assertAmount($accepted['normalized_totals']['cash_income'], 6300.0, 'accepted import cash income');
    assertAmount($accepted['normalized_totals']['cash_expense'], 250.0, 'accepted import cash expense');
    assertAmount($accepted['normalized_totals']['card_income'], 25.0, 'accepted import card income');
    assertAmount($accepted['normalized_totals']['card_expense'], 1060.0, 'accepted import card expense');
    assertAmount($accepted['source_total_comparison']['cash_income'], 0.0, 'accepted import cash income comparison');
    $dateSources = array_fill_keys(array_map(static fn (array $trace): ?string => $trace['date_source'] ?? null, $accepted['row_traces']), true);
    fixtureAssert(isset($dateSources['filename_date']), 'import fixture missing filename date provenance');
    fixtureAssert(isset($dateSources['inherited_previous_row_date']), 'import fixture missing inherited date provenance');
    fixtureAssert(isset($dateSources['row_date']), 'import fixture missing row date provenance');

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

$report->print();
exit($report->hasFailures() ? 1 : 0);
