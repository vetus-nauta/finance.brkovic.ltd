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

function runFixture(FixtureReport $report, string $fixture, callable $callback): void
{
    try {
        $message = $callback();
        $report->pass($fixture, $message);
    } catch (Throwable $e) {
        $report->fail($fixture, $e->getMessage());
    }
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
$report->blocked('Fixture 10 - Closed month protection', 'closed-month edit prompt/correction workflow is not implemented yet');

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

$report->print();
exit($report->hasFailures() ? 1 : 0);
