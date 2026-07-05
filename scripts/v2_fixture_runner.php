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

    assertSameValue($expense['flow']['type'], 'cash', 'expense flow');
    assertSameValue($expense['sign'], '-', 'expense sign');
    assertSameValue($expense['direction'], 'out', 'expense direction');
    assertSameValue($expense['entry_type'], 'cash_expense', 'expense type');
    assertSameValue($expense['status'], 'recognized', 'expense status');
    assertAmount($expense['amount'], 250.0, 'expense amount');

    return 'cash +500/-250 normalized as income/expense with correct amounts';
});
$report->blocked('Fixture 1 - Basic cash', 'cash_now/live balance chain is outside the SPRINT-01R repository foundation');

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
    assertSameValue($entry['status'], 'recognized', 'card status');
    assertAmount($entry['amount'], 60.0, 'card amount');
    assertSameValue($cashEntriesAfter, $cashEntriesBefore, 'card entry should not create/touch cash entries');

    return 'card -60 normalizes as card_expense and does not create cash repository rows';
});
$report->blocked('Fixture 3 - Card expense', 'automatic media_comms categorization and card expense rollups are not implemented yet');

runFixture($report, 'Fixture 4 - Card to cash', function () use ($repo, $workspace, $cashFlow, $cardFlow, $userId): string {
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
    fixtureAssert($cardSide['status'] !== 'duplicate_suspect', 'card side was marked duplicate');

    assertSameValue($cashSide['entry_type'], 'cash_income', 'cash side type');
    assertSameValue($cashSide['status'], 'recognized', 'cash side status');
    assertSameValue($cashSide['category_code'], 'cash_topup_from_card', 'cash side category');
    assertAmount($cashSide['amount'], 1000.0, 'cash side amount');
    fixtureAssert($cashSide['status'] !== 'duplicate_suspect', 'cash side was marked duplicate');

    return 'card -1000 and cash +1000 can both be saved with transfer category and no duplicate/error status';
});
$report->blocked('Fixture 4 - Card to cash', 'cash balance increase is not calculated by the SPRINT-01R foundation');

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

    $total = (float)$charter['amount'] + (float)$agency['amount'];
    assertAmount($total, 5750.0, 'commercial income total');

    return 'commercial_income can be explicitly assigned and remains a distinct income category totaling 5750';
});
$report->blocked('Fixture 5 - Commercial income', 'opening balance behavior is not implemented in the SPRINT-01R foundation');

$report->blocked('Fixture 6 - Other expenses', 'fallback category parser and Other expenses review queue are not implemented yet');
$report->blocked('Fixture 7 - Tender fuel ambiguity', 'weighted category parser and secondary tender metadata marker are not implemented yet');
$report->blocked('Fixture 8 - Person is actor, not category', 'actor extraction and actor/category separation are not implemented yet');
$report->blocked('Fixture 9 - Month insertion recalculation', 'balance_after chain recalculation for inserted rows is not implemented yet');
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
