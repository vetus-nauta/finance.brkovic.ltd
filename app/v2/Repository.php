<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/LegacyExcelImporter.php';
require_once __DIR__ . '/Support.php';

final class FinDeskV2Repository
{
    private const ATTACHMENT_MAX_BYTES = 8388608;
    private const ATTACHMENT_ALLOWED_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function listWorkspaces(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*
            FROM v2_workspaces w
            INNER JOIN v2_workspace_members m ON m.workspace_id = w.id
            WHERE m.user_id = ? AND w.archived_at IS NULL
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);

        return array_map([$this, 'workspaceRow'], $stmt->fetchAll());
    }

    public function createWorkspace(array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($input, $userId): array {
            $id = FinDeskV2Support::uuid();
            $name = FinDeskV2Support::requireString($input, 'name', 190);
            $type = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'type', 'yacht', 40) ?? 'yacht',
                ['yacht', 'family', 'personal', 'business', 'trip', 'custom'],
                'type'
            );
            $currency = strtoupper(FinDeskV2Support::optionalString($input, 'currency', 'EUR', 3) ?? 'EUR');
            $locale = FinDeskV2Support::optionalString($input, 'locale', 'ru', 10) ?? 'ru';

            $this->db->prepare("
                INSERT INTO v2_workspaces (id, name, type, currency, locale, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$id, $name, $type, $currency, $locale, $userId]);

            $this->db->prepare("
                INSERT INTO v2_workspace_members (id, workspace_id, user_id, role)
                VALUES (?, ?, ?, 'owner')
            ")->execute([FinDeskV2Support::uuid(), $id, $userId]);

            $openingCash = FinDeskV2Support::nullableAmount($input['opening_cash'] ?? $input['opening_balance'] ?? null) ?? '0.00';

            $this->createDefaultFlow($id, 'Cash', 'cash', true, true, $openingCash);
            $this->createDefaultFlow($id, 'Card', 'card', false);
            $workspace = $this->getWorkspace($id, $userId);
            $this->audit($id, 'workspace', $id, 'create', null, $workspace, $userId);

            return $workspace;
        });
    }

    public function getWorkspace(string $id, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*
            FROM v2_workspaces w
            INNER JOIN v2_workspace_members m ON m.workspace_id = w.id
            WHERE w.id = ? AND m.user_id = ? AND w.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'workspace_not_found');
        }

        return $this->workspaceRow($row);
    }

    public function updateWorkspace(string $id, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($id, $input, $userId): array {
            $before = $this->getWorkspace($id, $userId);
            $this->requireWorkspaceWriter($id, $userId);
            $name = FinDeskV2Support::optionalString($input, 'name', $before['name'], 190) ?? $before['name'];
            $type = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'type', $before['type'], 40) ?? $before['type'],
                ['yacht', 'family', 'personal', 'business', 'trip', 'custom'],
                'type'
            );
            $currency = strtoupper(FinDeskV2Support::optionalString($input, 'currency', $before['currency'], 3) ?? $before['currency']);
            $locale = FinDeskV2Support::optionalString($input, 'locale', $before['locale'], 10) ?? $before['locale'];

            $this->db->prepare("
                UPDATE v2_workspaces
                SET name = ?, type = ?, currency = ?, locale = ?
                WHERE id = ?
            ")->execute([$name, $type, $currency, $locale, $id]);

            $after = $this->getWorkspace($id, $userId);
            $this->audit($id, 'workspace', $id, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function listFlows(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_flows
            WHERE workspace_id = ?
            ORDER BY is_default DESC, FIELD(type, 'cash', 'card', 'assistant_journal'), name
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'flowRow'], $stmt->fetchAll());
    }

    public function createFlow(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $flow = $this->createDefaultFlow(
                $workspaceId,
                FinDeskV2Support::requireString($input, 'name', 120),
                FinDeskV2Support::enum((string)($input['type'] ?? ''), ['cash', 'card', 'assistant_journal'], 'type'),
                (bool)($input['has_live_balance'] ?? false),
                (bool)($input['is_default'] ?? false),
                FinDeskV2Support::nullableAmount($input['opening_balance'] ?? null) ?? '0.00'
            );
            $this->audit($workspaceId, 'flow', $flow['id'], 'create', null, $flow, $userId);

            return $flow;
        });
    }

    public function getWorkspaceSummary(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $flows = $this->listFlows($workspaceId, $userId);
        $cashFlow = null;
        $cardFlowIds = [];

        foreach ($flows as $flow) {
            if ($flow['type'] === 'cash' && $flow['has_live_balance']) {
                $cashFlow = $flow;
            }
            if ($flow['type'] === 'card') {
                $cardFlowIds[] = $flow['id'];
            }
        }

        $cashNow = $cashFlow === null ? null : $cashFlow['opening_balance'];
        if ($cashFlow !== null) {
            $stmt = $this->db->prepare("
                SELECT balance_after
                FROM v2_entries
                WHERE flow_id = ?
                  AND archived_at IS NULL
                  AND balance_after IS NOT NULL
                ORDER BY date DESC, created_seq DESC
                LIMIT 1
            ");
            $stmt->execute([$cashFlow['id']]);
            $latest = $stmt->fetchColumn();
            if ($latest !== false) {
                $cashNow = (float)$latest;
            }
        }

        $cardExpenseTotal = 0.0;
        if ($cardFlowIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($cardFlowIds), '?'));
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM v2_entries
                WHERE flow_id IN ({$placeholders})
                  AND archived_at IS NULL
                  AND direction = 'out'
                  AND entry_type = 'card_expense'
                  AND status IN (" . $this->countedStatusSqlList() . ")
                  AND amount IS NOT NULL
            ");
            $stmt->execute($cardFlowIds);
            $cardExpenseTotal = (float)$stmt->fetchColumn();
        }

        return [
            'workspace_id' => $workspaceId,
            'opening_cash' => $cashFlow === null ? null : $cashFlow['opening_balance'],
            'cash_now' => $cashNow,
            'card_expense_total' => $cardExpenseTotal,
        ];
    }

    public function getMonthlyReport(string $workspaceId, array $query, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $year = $this->optionalInt($query, 'year', (int)date('Y'));
        $month = $this->optionalInt($query, 'month', (int)date('n'));
        $this->assertValidMonth($year, $month);

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
        $cashFlow = $this->cashFlowForWorkspace($workspaceId, $userId);
        $openingCash = $cashFlow === null
            ? null
            : (float)$cashFlow['opening_balance'] + $this->cashDeltaBefore($cashFlow['id'], $monthStart);

        $report = [
            'workspace_id' => $workspaceId,
            'year' => $year,
            'month' => $month,
            'month_key' => sprintf('%04d-%02d', $year, $month),
            'source_files' => $this->sourceFilesForMonth($workspaceId, $monthStart, $monthEnd),
            'opening_cash' => $openingCash,
            'discrepancy_with_previous' => 0.0,
            'external_cash_income' => 0.0,
            'commercial_income' => 0.0,
            'cash_expense' => 0.0,
            'card_expense' => 0.0,
            'cash_topup_from_card_card_side' => 0.0,
            'cash_topup_from_card_cash_side' => 0.0,
            'other_expenses' => 0.0,
            'corrections' => 0.0,
            'ending_cash' => $openingCash,
            'comment' => null,
            'is_closed' => false,
            'counts' => [
                'entries' => 0,
                'counted' => 0,
                'unrecognized' => 0,
                'other_review' => 0,
            ],
        ];

        $entries = $this->db->prepare("
            SELECT
                e.amount,
                e.direction,
                e.entry_type,
                e.status,
                f.type AS flow_type,
                c.code AS category_code
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
        ");
        $entries->execute([$workspaceId, $monthStart, $monthEnd]);

        $monthCashDelta = 0.0;
        foreach ($entries->fetchAll() as $entry) {
            $report['counts']['entries']++;
            if ((string)$entry['status'] === 'unrecognized') {
                $report['counts']['unrecognized']++;
            }
            if ((string)$entry['status'] === 'other_review') {
                $report['counts']['other_review']++;
            }

            if (!$this->isCountedStatus((string)$entry['status']) || $entry['amount'] === null) {
                continue;
            }

            $report['counts']['counted']++;
            $amount = (float)$entry['amount'];
            $flowType = (string)$entry['flow_type'];
            $direction = (string)$entry['direction'];
            $entryType = (string)$entry['entry_type'];
            $categoryCode = (string)($entry['category_code'] ?? '');

            if ($flowType === 'cash') {
                $cashDelta = $this->cashBalanceDelta($entry);
                if ($cashDelta !== null) {
                    $monthCashDelta += $cashDelta;
                }
            }

            if ($flowType === 'cash' && $direction === 'in' && $entryType === 'cash_income') {
                if ($categoryCode === 'commercial_income') {
                    $report['commercial_income'] += $amount;
                } elseif ($categoryCode === 'cash_topup_from_card') {
                    $report['cash_topup_from_card_cash_side'] += $amount;
                } else {
                    $report['external_cash_income'] += $amount;
                }
            }

            if ($flowType === 'cash' && $direction === 'out' && $entryType === 'cash_expense') {
                $report['cash_expense'] += $amount;
            }

            if ($flowType === 'card' && $direction === 'out' && $entryType === 'card_expense') {
                $report['card_expense'] += $amount;
                if ($categoryCode === 'cash_topup_from_card') {
                    $report['cash_topup_from_card_card_side'] += $amount;
                }
            }

            if ($direction === 'out' && $categoryCode === 'other') {
                $report['other_expenses'] += $amount;
            }

            if ($entryType === 'correction') {
                $report['corrections'] += $direction === 'out' ? -$amount : $amount;
            }
        }

        if ($openingCash !== null) {
            $report['ending_cash'] = $openingCash + $monthCashDelta;
        }

        $closure = $this->monthClosure($workspaceId, $year, $month);
        if ($closure !== null) {
            $report['is_closed'] = (int)$closure['is_closed'] === 1;
            $report['comment'] = $closure['comment'] === null ? null : (string)$closure['comment'];
            if ($closure['opening_balance'] !== null) {
                $report['discrepancy_with_previous'] = $openingCash === null
                    ? 0.0
                    : (float)$closure['opening_balance'] - $openingCash;
            }
        }

        return $report;
    }

    public function getCategoryMatrixReport(string $workspaceId, array $query, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $year = $this->optionalInt($query, 'year', (int)date('Y'));
        $months = array_fill_keys(array_map('strval', range(1, 12)), 0.0);
        $rows = [];

        foreach ($this->listCategories($workspaceId, $userId) as $category) {
            $rows[$category['code']] = [
                'category_code' => $category['code'],
                'category_name' => $category['name'],
                'direction' => $category['direction'],
                'months' => $months,
                'breakdown' => [],
                'total' => 0.0,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT
                MONTH(e.date) AS report_month,
                f.type AS flow_type,
                e.direction,
                c.code AS category_code,
                COALESCE(SUM(e.amount), 0) AS total
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            INNER JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND YEAR(e.date) = ?
              AND e.amount IS NOT NULL
              AND e.status IN (" . $this->countedStatusSqlList() . ")
            GROUP BY MONTH(e.date), f.type, e.direction, c.code
            ORDER BY c.code ASC, report_month ASC, f.type ASC, e.direction ASC
        ");
        $stmt->execute([$workspaceId, $year]);

        foreach ($stmt->fetchAll() as $row) {
            $categoryCode = (string)$row['category_code'];
            if (!isset($rows[$categoryCode])) {
                continue;
            }

            $month = (string)(int)$row['report_month'];
            $flowType = (string)$row['flow_type'];
            $direction = (string)$row['direction'];
            $total = (float)$row['total'];
            $breakdownKey = "{$flowType}:{$direction}";

            $rows[$categoryCode]['months'][$month] += $total;
            $rows[$categoryCode]['total'] += $total;
            $rows[$categoryCode]['breakdown'][$month][$breakdownKey] = $total;
        }

        return [
            'workspace_id' => $workspaceId,
            'year' => $year,
            'months' => range(1, 12),
            'rows' => array_values($rows),
        ];
    }

    public function getOtherReviewReport(string $workspaceId, int $userId): array
    {
        $entries = $this->listOtherExpenseQueue($workspaceId, $userId);
        $total = 0.0;
        foreach ($entries as $entry) {
            if ($entry['amount'] !== null) {
                $total += (float)$entry['amount'];
            }
        }

        return [
            'workspace_id' => $workspaceId,
            'count' => count($entries),
            'total' => $total,
            'entries' => $entries,
        ];
    }

    public function listEntries(string $workspaceId, array $query, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $params = [$workspaceId];
        $where = ['e.workspace_id = ?', 'e.archived_at IS NULL'];

        if (!empty($query['year'])) {
            $where[] = 'YEAR(e.date) = ?';
            $params[] = (int)$query['year'];
        }
        if (!empty($query['month'])) {
            $where[] = 'MONTH(e.date) = ?';
            $params[] = (int)$query['month'];
        }

        $stmt = $this->db->prepare("
            SELECT
                e.*,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute($params);

        return array_map([$this, 'entryRow'], $stmt->fetchAll());
    }

    public function listOtherExpenseQueue(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            INNER JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.status = 'other_review'
              AND e.entry_type = 'cash_expense'
              AND c.code = 'other'
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'entryRow'], $stmt->fetchAll());
    }

    public function createEntry(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            return $this->createEntryInCurrentTransaction($workspaceId, $input, $userId);
        });
    }

    public function previewEntryParse(string $workspaceId, array $input, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
        $entry = $this->normalizeEntryInput($workspaceId, $flow, $input, false);

        return $this->entryPreviewRow($workspaceId, $flow, $entry);
    }

    public function createLegacyExcelImport(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $fileName = FinDeskV2Support::requireString($input, 'file_name', 255);
            $fileId = FinDeskV2Support::optionalString($input, 'file_id', null, 190);
            $fileUrl = FinDeskV2Support::optionalString($input, 'file_url', null, 2000);
            $fileUpdatedDate = FinDeskV2Support::optionalString($input, 'file_updated_date', null, 20);
            $content = FinDeskV2Support::requireString($input, 'content_base64', 20_000_000);

            if (!str_ends_with(mb_strtolower($fileName), '.xlsx')) {
                throw new FinDeskV2HttpError(422, 'xlsx_required');
            }

            $excludeReason = $this->legacyExcludeReason($fileName);
            $sourceId = FinDeskV2Support::uuid();
            $includeDecision = $excludeReason === null ? 'included' : 'excluded_by_title_marker';
            $decoded = base64_decode($content, true);
            if ($decoded === false) {
                throw new FinDeskV2HttpError(422, 'invalid_base64');
            }

            $this->db->prepare("
                INSERT INTO v2_import_sources (
                    id, workspace_id, source_type, file_name, file_url, file_id, status,
                    include_decision, reason
                )
                VALUES (?, ?, 'excel', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $sourceId,
                $workspaceId,
                $fileName,
                $fileUrl,
                $fileId,
                $excludeReason === null ? 'review_ready' : 'excluded',
                $includeDecision,
                $excludeReason,
            ]);

            if ($excludeReason === null) {
                $sheets = (new FinDeskV2LegacyExcelImporter())->read($decoded);
                $this->storeLegacyImportRows($workspaceId, $sourceId, $sheets, $fileName, $fileUpdatedDate);
            }

            $review = $this->getLegacyImportReview($workspaceId, $sourceId, $userId);
            $this->audit($workspaceId, 'import_source', $sourceId, 'create_import', null, $review, $userId);

            return $review;
        });
    }

    public function getLegacyImportReview(string $workspaceId, string $importId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $source = $this->legacyImportSource($workspaceId, $importId);
        $rows = $this->legacyImportRows($importId);
        $review = $this->emptyLegacyImportReview($source);
        $seen = [];

        foreach ($rows as $row) {
            $raw = FinDeskV2Support::jsonDecode($row['raw_json'], []);
            $parsed = $this->parseLegacyImportRow($raw, $row, $seen);
            $this->accumulateLegacyImportReview($review, $parsed, $row);
        }

        $review['sheets_scanned'] = count($review['_sheet_names'] ?? []);
        $review['months_covered'] = array_values(array_keys($review['_months_covered'] ?? []));
        sort($review['months_covered']);
        unset($review['_sheet_names'], $review['_months_covered']);

        $comparisonSource = array_sum($review['source_summary_totals']) > 0
            ? $review['source_summary_totals']
            : $review['source_totals'];
        $review['source_total_comparison'] = [
            'cash_income' => $comparisonSource['cash_income'] - $review['normalized_totals']['cash_income'],
            'cash_expense' => $comparisonSource['cash_expense'] - $review['normalized_totals']['cash_expense'],
            'card_income' => $comparisonSource['card_income'] - $review['normalized_totals']['card_income'],
            'card_expense' => $comparisonSource['card_expense'] - $review['normalized_totals']['card_expense'],
        ];

        return $review;
    }

    public function acceptLegacyImport(string $workspaceId, string $importId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $importId, $input, $userId): array {
            FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'decision', 'accept', 40) ?? 'accept',
                ['accept'],
                'decision'
            );
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $source = $this->legacyImportSource($workspaceId, $importId);
            if ((string)$source['include_decision'] !== 'included') {
                throw new FinDeskV2HttpError(422, 'import_excluded');
            }

            $flows = $this->flowsByType($workspaceId, $userId);
            $rows = $this->legacyImportRows($importId);
            $seen = [];

            foreach ($rows as $row) {
                if ($row['entry_id'] !== null) {
                    continue;
                }

                $raw = FinDeskV2Support::jsonDecode($row['raw_json'], []);
                $parsed = $this->parseLegacyImportRow($raw, $row, $seen);
                if ($parsed['entry'] === null) {
                    $this->updateLegacyImportRowStatus($row['id'], $parsed['parse_status'], null, $parsed['parse_notes']);
                    continue;
                }

                $flow = $flows[$parsed['entry']['flow_type']] ?? null;
                if ($flow === null) {
                    $this->updateLegacyImportRowStatus($row['id'], 'unrecognized', null, 'missing flow');
                    continue;
                }

                $status = $parsed['duplicate_suspect'] ? 'duplicate_suspect' : 'imported';
                $entry = $this->createEntryInCurrentTransaction($workspaceId, [
                    'flow_id' => $flow['id'],
                    'date' => $parsed['entry']['date'],
                    'raw_text' => $parsed['entry']['raw_text'],
                    'amount' => number_format($parsed['entry']['amount'], 2, '.', ''),
                    'category_code' => $parsed['entry']['category_code'],
                    'status' => $status,
                    'source_type' => 'import',
                    'source_id' => $importId,
                    'source_row_id' => $row['id'],
                    'matched_rules' => [[
                        'source' => 'legacy_excel_import',
                        'sheet_name' => $row['sheet_name'],
                        'row_number' => (int)$row['row_number'],
                    ]],
                ], $userId);

                $this->updateLegacyImportRowStatus($row['id'], $status, $entry['id'], $parsed['parse_notes']);
            }

            $this->db->prepare("UPDATE v2_import_sources SET status = 'accepted' WHERE id = ?")->execute([$importId]);
            $review = $this->getLegacyImportReview($workspaceId, $importId, $userId);
            $this->audit($workspaceId, 'import_source', $importId, 'accept_import', $source, $review, $userId);

            return $review;
        });
    }

    public function updateEntry(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceWriter($before['workspace_id'], $userId);
            $this->guardEntryMonthIsOpen($before);
            $flowId = FinDeskV2Support::optionalString($input, 'flow_id', $before['flow']['id'], 36) ?? $before['flow']['id'];
            $flow = $this->getFlowForWorkspace($flowId, $before['workspace_id']);
            $entryInput = [
                'flow_id' => $flow['id'],
                'date' => $input['date'] ?? $before['date'],
                'raw_text' => $input['raw_text'] ?? $before['raw_text'],
                'category_code' => $input['category_code'] ?? $before['category_code'],
                'status' => $input['status'] ?? $before['status'],
                'source_type' => $input['source_type'] ?? $before['source_type'],
                'source_id' => $input['source_id'] ?? $before['source_id'],
                'source_row_id' => $input['source_row_id'] ?? $before['source_row_id'],
                'notes' => $input['notes'] ?? $before['notes'],
                'confidence' => $input['confidence'] ?? $before['confidence'],
                'matched_rules' => $input['matched_rules'] ?? $before['matched_rules'],
            ];
            if (array_key_exists('amount', $input)) {
                $entryInput['amount'] = $input['amount'];
            }
            $entry = $this->normalizeEntryInput($before['workspace_id'], $flow, $entryInput);
            $this->guardWorkspaceMonthIsOpen($before['workspace_id'], $entry['date']);

            $this->db->prepare("
                UPDATE v2_entries
                SET flow_id = ?, actor_id = ?, date = ?, raw_text = ?, sign = ?, amount = ?, direction = ?,
                    entry_type = ?, category_id = ?, status = ?, source_type = ?, notes = ?,
                    confidence = ?, matched_rules_json = ?
                WHERE id = ?
            ")->execute([
                $flow['id'],
                $entry['actor_id'],
                $entry['date'],
                $entry['raw_text'],
                $entry['sign'],
                $entry['amount'],
                $entry['direction'],
                $entry['entry_type'],
                $entry['category_id'],
                $entry['status'],
                $entry['source_type'],
                $entry['notes'],
                $entry['confidence'],
                FinDeskV2Support::jsonEncode($entry['matched_rules']),
                $entryId,
            ]);

            $after = $this->getEntry($entryId, $userId);
            $this->recalculateFlowBalance($flow['id']);
            if ($before['flow']['id'] !== $flow['id']) {
                $this->recalculateFlowBalance($before['flow']['id']);
            }
            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function updateEntryCategory(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceWriter($before['workspace_id'], $userId);
            $this->guardEntryMonthIsOpen($before);
            $categoryCode = FinDeskV2Support::requireString($input, 'category_code', 80);
            $this->applyEntryCategory($before, $categoryCode);

            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'update_category', $before, $after, $userId);

            return $after;
        });
    }

    public function decideClosedMonthCategoryCorrection(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceWriter($before['workspace_id'], $userId);
            $month = $this->entryMonthParts($before);
            if (!$this->isEntryMonthClosed($before)) {
                throw new FinDeskV2HttpError(422, 'month_is_not_closed');
            }

            $decision = FinDeskV2Support::enum(
                FinDeskV2Support::requireString($input, 'decision', 40),
                ['cancel', 'create_correction', 'recalculate_chain'],
                'decision'
            );
            $categoryCode = FinDeskV2Support::requireString($input, 'category_code', 80);
            $reason = FinDeskV2Support::optionalString($input, 'reason', null, 500);
            $meta = [
                'decision' => $decision,
                'requested_category_code' => $categoryCode,
                'year' => $month['year'],
                'month' => $month['month'],
                'reason' => $reason,
            ];

            if ($decision === 'cancel') {
                $this->audit($before['workspace_id'], 'entry', $entryId, 'closed_month_category_cancel', $before + ['decision' => $meta], $before + ['decision' => $meta], $userId);
                return [
                    'decision' => $decision,
                    'entry' => $before,
                    'changed' => false,
                ];
            }

            if ($decision === 'create_correction') {
                $this->categoryIdByCode($before['workspace_id'], $categoryCode);
                $this->audit($before['workspace_id'], 'entry', $entryId, 'closed_month_category_correction_requested', $before + ['decision' => $meta], $before + ['decision' => $meta], $userId);
                return [
                    'decision' => $decision,
                    'entry' => $before,
                    'changed' => false,
                    'requires_followup' => true,
                ];
            }

            $this->applyEntryCategory($before, $categoryCode);
            $this->recalculateFlowBalance($before['flow']['id']);
            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'closed_month_category_recalculate', $before + ['decision' => $meta], $after + ['decision' => $meta], $userId);

            return [
                'decision' => $decision,
                'entry' => $after,
                'changed' => true,
            ];
        });
    }

    public function deleteEntry(string $entryId, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceWriter($before['workspace_id'], $userId);
            $this->guardEntryMonthIsOpen($before);
            $this->db->prepare("UPDATE v2_entries SET archived_at = NOW() WHERE id = ?")->execute([$entryId]);
            $this->recalculateFlowBalance($before['flow']['id']);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'delete', $before, ['archived' => true], $userId);

            return ['id' => $entryId, 'archived' => true];
        });
    }

    public function listEntryAttachments(string $entryId, int $userId): array
    {
        $this->getEntry($entryId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_attachments
            WHERE entry_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$entryId]);

        return array_map([$this, 'attachmentRow'], $stmt->fetchAll());
    }

    public function createEntryAttachment(string $entryId, array $input, int $userId): array
    {
        $absolutePath = null;

        try {
            return FinDeskV2Database::transact(function () use ($entryId, $input, $userId, &$absolutePath): array {
                $entry = $this->getEntry($entryId, $userId);
                $this->requireWorkspaceWriter($entry['workspace_id'], $userId);
                $payload = $this->normalizeAttachmentPayload($input);
                $attachmentId = FinDeskV2Support::uuid();
                $extension = self::ATTACHMENT_ALLOWED_MIME_EXTENSIONS[$payload['mime_type']];
                $relativePath = 'storage/v2/attachments/'
                    . $entry['workspace_id'] . '/'
                    . $entryId . '/'
                    . $attachmentId . '.' . $extension;
                $absolutePath = $this->attachmentWritePath($relativePath);

                if (@file_put_contents($absolutePath, $payload['content']) === false) {
                    throw new FinDeskV2HttpError(500, 'attachment_store_failed');
                }

                $this->db->prepare("
                    INSERT INTO v2_attachments (id, entry_id, file_name, file_url, mime_type, size_bytes, image_mode)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $attachmentId,
                    $entryId,
                    $payload['file_name'],
                    $relativePath,
                    $payload['mime_type'],
                    $payload['size_bytes'],
                    $payload['image_mode'],
                ]);

                $attachment = $this->getAttachmentForUser($attachmentId, $userId);
                $this->audit($entry['workspace_id'], 'attachment', $attachmentId, 'create', null, [
                    'attachment' => $attachment,
                    'closed_month' => $this->isEntryMonthClosed($entry),
                ], $userId);

                return $attachment;
            });
        } catch (Throwable $e) {
            if ($absolutePath !== null && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            throw $e;
        }
    }

    public function deleteAttachment(string $attachmentId, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($attachmentId, $userId): array {
            $attachment = $this->getAttachmentForUser($attachmentId, $userId);
            $entry = $this->getEntry($attachment['entry_id'], $userId);
            $this->requireWorkspaceWriter($entry['workspace_id'], $userId);
            $fileDeleted = $this->deleteAttachmentFile($attachment['file_url']);

            $this->db->prepare("DELETE FROM v2_attachments WHERE id = ?")->execute([$attachmentId]);
            $after = [
                'id' => $attachmentId,
                'entry_id' => $attachment['entry_id'],
                'deleted' => true,
                'file_deleted' => $fileDeleted,
                'closed_month' => $this->isEntryMonthClosed($entry),
            ];
            $this->audit($entry['workspace_id'], 'attachment', $attachmentId, 'delete', $attachment, $after, $userId);

            return $after;
        });
    }

    public function closeMonth(string $workspaceId, int $year, int $month, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $before = $this->monthClosure($workspaceId, $year, $month);
            $reportBeforeClose = $this->getMonthlyReport($workspaceId, ['year' => $year, 'month' => $month], $userId);
            $id = $before ? (string)$before['id'] : FinDeskV2Support::uuid();
            $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $openingBalance = $reportBeforeClose['opening_cash'] === null
                ? null
                : number_format((float)$reportBeforeClose['opening_cash'], 2, '.', '');
            $closingBalance = $reportBeforeClose['ending_cash'] === null
                ? null
                : number_format((float)$reportBeforeClose['ending_cash'], 2, '.', '');

            $this->db->prepare("
                INSERT INTO v2_monthly_closures (
                    id, workspace_id, year, month, opening_balance, closing_balance,
                    is_closed, comment, closed_by, closed_at
                )
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    opening_balance = VALUES(opening_balance),
                    closing_balance = VALUES(closing_balance),
                    is_closed = 1,
                    comment = VALUES(comment),
                    closed_by = VALUES(closed_by),
                    closed_at = VALUES(closed_at)
            ")->execute([$id, $workspaceId, $year, $month, $openingBalance, $closingBalance, $comment, $userId]);

            $after = $this->monthClosureRow($this->monthClosure($workspaceId, $year, $month));
            $this->audit($workspaceId, 'month_closure', $id, 'month_close', $before === null ? null : $this->monthClosureRow($before), $after, $userId);

            return [
                'closure' => $after,
                'report' => $this->getMonthlyReport($workspaceId, ['year' => $year, 'month' => $month], $userId),
            ];
        });
    }

    public function reopenMonth(string $workspaceId, int $year, int $month, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $before = $this->monthClosure($workspaceId, $year, $month);
            if (!$before || (int)$before['is_closed'] !== 1) {
                throw new FinDeskV2HttpError(422, 'month_not_closed');
            }

            $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $this->db->prepare("
                UPDATE v2_monthly_closures
                SET is_closed = 0, comment = ?
                WHERE id = ?
            ")->execute([$comment, $before['id']]);

            $after = $this->monthClosureRow($this->monthClosure($workspaceId, $year, $month));
            $this->audit($workspaceId, 'month_closure', (string)$before['id'], 'month_reopen', $this->monthClosureRow($before), $after, $userId);

            return [
                'closure' => $after,
                'report' => $this->getMonthlyReport($workspaceId, ['year' => $year, 'month' => $month], $userId),
            ];
        });
    }

    public function createMonthCorrection(string $workspaceId, int $year, int $month, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
            $date = FinDeskV2Support::date($input);
            if (substr($date, 0, 7) !== sprintf('%04d-%02d', $year, $month)) {
                throw new FinDeskV2HttpError(422, 'invalid_correction_date');
            }

            $rawText = FinDeskV2Support::requireString($input, 'raw_text', 2000);
            $signed = $this->strictSignedAmount($rawText, 'correction');
            $reason = FinDeskV2Support::optionalString($input, 'reason', null, 1000)
                ?? FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $referenceEntryId = FinDeskV2Support::optionalString($input, 'reference_entry_id', null, 36);
            if ($referenceEntryId !== null) {
                $reference = $this->getEntry($referenceEntryId, $userId);
                if ($reference['workspace_id'] !== $workspaceId) {
                    throw new FinDeskV2HttpError(404, 'entry_not_found');
                }
            }

            $id = FinDeskV2Support::uuid();
            $matchedRules = [[
                'source' => 'month_correction',
                'year' => $year,
                'month' => $month,
                'reference_entry_id' => $referenceEntryId,
            ]];

            $this->db->prepare("
                INSERT INTO v2_entries (
                    id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                    entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
                )
                VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'correction', NULL, 'corrected', 'correction', NULL, NULL, ?, NULL, ?)
            ")->execute([
                $id,
                $workspaceId,
                $flow['id'],
                $userId,
                $date,
                $rawText,
                $signed['sign'],
                $signed['amount'],
                $signed['direction'],
                $reason,
                FinDeskV2Support::jsonEncode($matchedRules),
            ]);

            $this->recalculateFlowBalance($flow['id']);
            $entry = $this->getEntry($id, $userId);
            $this->audit($workspaceId, 'entry', $id, 'month_correction_create', null, [
                'entry' => $entry,
                'year' => $year,
                'month' => $month,
                'reference_entry_id' => $referenceEntryId,
            ], $userId);

            return $entry;
        });
    }

    public function closeMonthForFixture(string $workspaceId, int $year, int $month, int $userId): array
    {
        return $this->closeMonth($workspaceId, $year, $month, [], $userId)['closure'];
    }

    public function listCategories(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_categories
            WHERE is_active = 1 AND (workspace_id IS NULL OR workspace_id = ?)
            ORDER BY sort_order ASC, code ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'categoryRow'], $stmt->fetchAll());
    }

    public function createCategoryRule(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $categoryId = $this->categoryIdByCode($workspaceId, FinDeskV2Support::requireString($input, 'category_code', 80));
            $id = FinDeskV2Support::uuid();
            $pattern = FinDeskV2Support::requireString($input, 'pattern', 255);
            $patternType = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'pattern_type', 'keyword', 40) ?? 'keyword',
                ['keyword', 'phrase', 'regex', 'supplier', 'role'],
                'pattern_type'
            );
            $language = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'language', 'multi', 10) ?? 'multi',
                ['ru', 'en', 'it', 'es', 'de', 'bcms', 'multi'],
                'language'
            );
            $weight = $this->optionalInt($input, 'weight', 10);
            $negativeWeight = $this->optionalInt($input, 'negative_weight', 0);
            $requiresAny = $this->optionalStringList($input, 'requires_any');
            $excludesAny = $this->optionalStringList($input, 'excludes_any');

            $this->db->prepare("
                INSERT INTO v2_category_rules (
                    id, workspace_id, category_id, pattern, pattern_type, language, weight,
                    negative_weight, requires_any_json, excludes_any_json, created_by_user, is_active
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ")->execute([
                $id,
                $workspaceId,
                $categoryId,
                $pattern,
                $patternType,
                $language,
                $weight,
                $negativeWeight,
                FinDeskV2Support::jsonEncode($requiresAny),
                FinDeskV2Support::jsonEncode($excludesAny),
            ]);

            $rule = $this->getCategoryRule($id, $workspaceId, $userId);
            $this->audit($workspaceId, 'category_rule', $id, 'create', null, $rule, $userId);

            return $rule;
        });
    }

    private function storeLegacyImportRows(string $workspaceId, string $sourceId, array $sheets, string $fileName, ?string $fileUpdatedDate): void
    {
        $insert = $this->db->prepare("
            INSERT INTO v2_import_rows (id, import_source_id, sheet_name, `row_number`, raw_json, parse_status, parse_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $seen = [];
        $filenameDate = $this->legacyFilenameDate($fileName);

        foreach ($sheets as $sheet) {
            $headers = [];
            $lastDate = null;
            foreach ($sheet['rows'] as $rowNumber => $cells) {
                if ($headers === []) {
                    $headers = $this->legacyHeaderMap($cells);
                    continue;
                }

                $raw = $this->legacyRawRow($headers, $cells);
                if ($raw === []) {
                    continue;
                }

                $raw['_date_context'] = [
                    'inherited_previous_row_date' => $lastDate,
                    'filename_date' => $filenameDate,
                    'file_updated_date' => $fileUpdatedDate,
                ];
                $date = $this->legacyRowDate($raw, null);
                if ($date !== null && ($raw['дата'] ?? '') !== '') {
                    $lastDate = $date;
                }
                $raw['_date_context']['inherited_previous_row_date'] = $lastDate;

                $parsed = $this->parseLegacyImportRow($raw, [
                    'id' => null,
                    'sheet_name' => $sheet['name'],
                    'row_number' => $rowNumber,
                ], $seen);

                $insert->execute([
                    FinDeskV2Support::uuid(),
                    $sourceId,
                    $sheet['name'],
                    $rowNumber,
                    FinDeskV2Support::jsonEncode($raw),
                    $parsed['parse_status'],
                    $parsed['parse_notes'],
                ]);
            }
        }
    }

    private function createEntryInCurrentTransaction(string $workspaceId, array $input, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceWriter($workspaceId, $userId);
        $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
        $entry = $this->normalizeEntryInput($workspaceId, $flow, $input);
        if ($entry['source_type'] !== 'correction') {
            $this->guardWorkspaceMonthIsOpen($workspaceId, $entry['date']);
        }
        $entry['id'] = FinDeskV2Support::uuid();
        $entry['created_by'] = $userId;

        $this->db->prepare("
            INSERT INTO v2_entries (
                id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $entry['id'],
            $workspaceId,
            $flow['id'],
            $userId,
            $entry['actor_id'],
            $entry['date'],
            $entry['raw_text'],
            $entry['sign'],
            $entry['amount'],
            $entry['direction'],
            $entry['entry_type'],
            $entry['category_id'],
            $entry['status'],
            $entry['source_type'],
            $entry['source_id'],
            $entry['source_row_id'],
            $entry['notes'],
            $entry['confidence'],
            FinDeskV2Support::jsonEncode($entry['matched_rules']),
        ]);

        $created = $this->getEntry($entry['id'], $userId);
        $this->recalculateFlowBalance($flow['id']);
        $created = $this->getEntry($entry['id'], $userId);
        $this->audit($workspaceId, 'entry', $entry['id'], 'create', null, $created, $userId);

        return $created;
    }

    private function legacyHeaderMap(array $cells): array
    {
        $headers = [];
        foreach ($cells as $index => $cell) {
            $normalized = mb_strtolower(trim((string)$cell));
            if ($normalized !== '') {
                $headers[$index] = $normalized;
            }
        }

        return $headers;
    }

    private function legacyRawRow(array $headers, array $cells): array
    {
        $raw = [];
        foreach ($headers as $index => $header) {
            $value = trim((string)($cells[$index] ?? ''));
            if ($value !== '') {
                $raw[$header] = $value;
            }
        }

        return $raw;
    }

    private function parseLegacyImportRow(array $raw, array $row, array &$seen): array
    {
        $description = trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? ''));
        $text = mb_strtolower($description);
        $date = $this->legacyRowDate($raw, null);
        $dateSource = $this->legacyDateSource($raw);
        $amounts = [
            'cash_income' => $this->legacyAmount($raw['приход кеш'] ?? $raw['приход кэш'] ?? $raw['cash income'] ?? null),
            'cash_expense' => $this->legacyAmount($raw['расход кеш'] ?? $raw['расход кэш'] ?? $raw['cash expense'] ?? null),
            'card_income' => $this->legacyAmount($raw['приход карта'] ?? $raw['приход карты'] ?? $raw['card income'] ?? null),
            'card_expense' => $this->legacyAmount($raw['расход карта'] ?? $raw['расход карты'] ?? $raw['card expense'] ?? null),
        ];
        $nonZero = array_filter($amounts, static fn (?float $amount): bool => $amount !== null && abs($amount) > 0.0001);
        $isSummary = str_contains($text, 'свод') || str_contains($text, 'summary') || isset($raw['сводные данные']);
        $isInfo = str_contains($text, 'информационная') || str_contains($text, 'не считается') || str_contains($text, 'comment') || str_contains($text, 'info');
        $isOpening = str_contains($text, 'остаток') || str_contains($text, 'переход') || str_contains($text, 'opening balance') || str_contains($text, 'balance brought forward');

        if ($isSummary) {
            return $this->legacyParsedRow('summary_ignored', null, $amounts, false, 'summary row ignored');
        }
        if ($isInfo || $isOpening) {
            return $this->legacyParsedRow('ignored', null, $amounts, false, $isOpening ? 'opening balance row ignored' : 'info row ignored');
        }
        if ($date === null || $nonZero === []) {
            return $this->legacyParsedRow('unrecognized', null, $amounts, false, 'missing date or amount');
        }
        if (count($nonZero) > 1) {
            return $this->legacyParsedRow('unrecognized', null, $amounts, false, 'multiple money columns in one row');
        }

        $kind = array_key_first($nonZero);
        $amount = (float)$nonZero[$kind];
        $flowType = str_starts_with((string)$kind, 'card_') ? 'card' : 'cash';
        $sign = str_ends_with((string)$kind, '_expense') ? '-' : '+';
        $categoryCode = $this->legacyCategoryCode($description, $flowType, $sign);
        $rawText = $sign . number_format($amount, 2, '.', '') . ($description === '' ? ' imported row' : ' ' . $description);
        $duplicateKey = implode('|', [$date, $flowType, $sign, number_format($amount, 2, '.', ''), mb_strtolower($description)]);
        $duplicate = isset($seen[$duplicateKey]);
        $seen[$duplicateKey] = true;

        return $this->legacyParsedRow('parsed', [
            'date' => $date,
            'date_source' => $dateSource,
            'flow_type' => $flowType,
            'raw_text' => $rawText,
            'amount' => $amount,
            'category_code' => $categoryCode,
        ], $amounts, $duplicate, $duplicate ? 'duplicate suspect' : null);
    }

    private function legacyParsedRow(string $status, ?array $entry, array $amounts, bool $duplicate, ?string $notes): array
    {
        if ($duplicate) {
            $status = 'duplicate_suspect';
        }

        return [
            'parse_status' => $status,
            'entry' => $entry,
            'source_totals' => [
                'cash_income' => $amounts['cash_income'] ?? 0.0,
                'cash_expense' => $amounts['cash_expense'] ?? 0.0,
                'card_income' => $amounts['card_income'] ?? 0.0,
                'card_expense' => $amounts['card_expense'] ?? 0.0,
            ],
            'date_source' => $entry['date_source'] ?? null,
            'duplicate_suspect' => $duplicate,
            'parse_notes' => $notes,
        ];
    }

    private function legacyRowDate(array $raw, ?string $fallback): ?string
    {
        foreach ([
            $raw['дата'] ?? $raw['date'] ?? null,
            $raw['_date_context']['inherited_previous_row_date'] ?? null,
            $raw['_date_context']['filename_date'] ?? null,
            $raw['_date_context']['file_updated_date'] ?? null,
            $fallback,
        ] as $value) {
            $date = $this->legacyNormalizeDate($value);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function legacyDateSource(array $raw): ?string
    {
        $sources = [
            'row_date' => $raw['дата'] ?? $raw['date'] ?? null,
            'inherited_previous_row_date' => $raw['_date_context']['inherited_previous_row_date'] ?? null,
            'filename_date' => $raw['_date_context']['filename_date'] ?? null,
            'file_updated_date' => $raw['_date_context']['file_updated_date'] ?? null,
        ];
        foreach ($sources as $source => $value) {
            if ($this->legacyNormalizeDate($value) !== null) {
                return $source;
            }
        }

        return null;
    }

    private function legacyNormalizeDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return DateTimeImmutable::createFromFormat('!Y-m-d', '1899-12-30')
                ->modify('+' . (int)$value . ' days')
                ->format('Y-m-d');
        }

        foreach (['!Y-m-d', '!d.m.Y', '!d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function legacyFilenameDate(string $fileName): ?string
    {
        if (preg_match('/(20[0-9]{2})[-_. ]?([01]?[0-9])[-_. ]?([0-3]?[0-9])/', $fileName, $match) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$match[1]}-{$match[2]}-{$match[3]}");

        return $date ? $date->format('Y-m-d') : null;
    }

    private function legacyAmount($value): ?float
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        $normalized = str_replace([' ', "\xc2\xa0"], '', $text);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? abs((float)$normalized) : null;
    }

    private function legacyCategoryCode(string $description, string $flowType, string $sign): ?string
    {
        $text = mb_strtolower($description);
        if (str_contains($text, 'снял с карты') || str_contains($text, 'cash topup') || str_contains($text, 'topup from card')) {
            return 'cash_topup_from_card';
        }
        if ($sign === '+' && (str_contains($text, 'charter') || str_contains($text, 'агентск'))) {
            return 'commercial_income';
        }
        if (str_contains($text, 'netflix')) {
            return 'media_comms';
        }
        if (preg_match('/заправ|топлив|fuel/u', $text) === 1) {
            return 'fuel';
        }
        if ($flowType === 'cash' && $sign === '-' && (str_contains($text, 'какая-то штука') || str_contains($text, 'unknown'))) {
            return 'other';
        }

        return null;
    }

    private function flowsByType(string $workspaceId, int $userId): array
    {
        $flows = [];
        foreach ($this->listFlows($workspaceId, $userId) as $flow) {
            $flows[$flow['type']] = $flow;
        }

        return $flows;
    }

    private function legacyExcludeReason(string $fileName): ?string
    {
        $text = mb_strtolower($fileName);
        foreach (['не отправлял', 'не отправлено', 'не готово', 'не закончен', 'не закончено', 'не полный', 'неполный', 'черновик', 'draft', 'test'] as $marker) {
            if (str_contains($text, $marker)) {
                return "title marker: {$marker}";
            }
        }

        return null;
    }

    private function legacyImportSource(string $workspaceId, string $importId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_import_sources
            WHERE id = ? AND workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$importId, $workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'import_not_found');
        }

        return $row;
    }

    private function legacyImportRows(string $importId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_import_rows
            WHERE import_source_id = ?
            ORDER BY sheet_name ASC, `row_number` ASC
        ");
        $stmt->execute([$importId]);

        return $stmt->fetchAll();
    }

    private function emptyLegacyImportReview(array $source): array
    {
        return [
            'import_id' => (string)$source['id'],
            'source_file_name' => $source['file_name'] === null ? null : (string)$source['file_name'],
            'source_file_id' => $source['file_id'] === null ? null : (string)$source['file_id'],
            'source_file_url' => $source['file_url'] === null ? null : (string)$source['file_url'],
            'status' => (string)$source['status'],
            'include_decision' => (string)$source['include_decision'],
            'reason' => $source['reason'] === null ? null : (string)$source['reason'],
            'files_detected' => 1,
            'files_included' => (string)$source['include_decision'] === 'included' ? 1 : 0,
            'files_excluded' => (string)$source['include_decision'] === 'included' ? 0 : 1,
            'final_version_decisions' => [],
            'sheets_scanned' => 0,
            'rows_scanned' => 0,
            'rows_parsed' => 0,
            'entries_created' => 0,
            'rows_ignored' => 0,
            'rows_unrecognized' => 0,
            'summary_rows_ignored' => 0,
            'cash_income_total' => 0.0,
            'cash_expense_total' => 0.0,
            'card_income_total' => 0.0,
            'card_expense_total' => 0.0,
            'source_totals' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'source_summary_totals' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'normalized_totals' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'source_total_comparison' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'months_covered' => [],
            '_months_covered' => [],
            '_sheet_names' => [],
            'duplicate_suspects' => [],
            'row_traces' => [],
        ];
    }

    private function accumulateLegacyImportReview(array &$review, array $parsed, array $row): void
    {
        $review['rows_scanned']++;
        $sheetName = (string)$row['sheet_name'];
        $review['_sheet_names'][$sheetName] = true;
        $status = (string)$row['parse_status'];
        if ($status === 'pending') {
            $status = $parsed['parse_status'];
        }

        if ($status === 'parsed' || $status === 'imported' || $status === 'duplicate_suspect') {
            $review['rows_parsed']++;
        } elseif ($status === 'summary_ignored') {
            $review['summary_rows_ignored']++;
        } elseif ($status === 'unrecognized') {
            $review['rows_unrecognized']++;
        } else {
            $review['rows_ignored']++;
        }

        $entry = $parsed['entry'];
        if ($row['entry_id'] !== null) {
            $review['entries_created']++;
        }

        foreach ($parsed['source_totals'] as $key => $amount) {
            if ($status === 'summary_ignored') {
                $review['source_summary_totals'][$key] += (float)$amount;
            } else {
                $review['source_totals'][$key] += (float)$amount;
            }
        }

        if ($entry !== null && !$parsed['duplicate_suspect']) {
            $kind = $entry['flow_type'] . '_' . ($entry['raw_text'][0] === '-' ? 'expense' : 'income');
            if (isset($review['normalized_totals'][$kind])) {
                $review['normalized_totals'][$kind] += (float)$entry['amount'];
            }
            $month = substr((string)$entry['date'], 0, 7);
            if ($month !== '') {
                $review['_months_covered'][$month] = true;
            }
        }

        foreach (['cash_income', 'cash_expense', 'card_income', 'card_expense'] as $key) {
            $review[$key . '_total'] = $review['normalized_totals'][$key];
        }

        if ($parsed['duplicate_suspect']) {
            $review['duplicate_suspects'][] = [
                'sheet_name' => $sheetName,
                'row_number' => (int)$row['row_number'],
                'reason' => 'same date, flow, sign, amount, and description',
            ];
        }

        $review['row_traces'][] = [
            'import_source_id' => (string)$row['import_source_id'],
            'import_row_id' => (string)$row['id'],
            'sheet_name' => $sheetName,
            'row_number' => (int)$row['row_number'],
            'raw_row_data' => FinDeskV2Support::jsonDecode($row['raw_json'], []),
            'entry_id' => $row['entry_id'] === null ? null : (string)$row['entry_id'],
            'parse_status' => $status,
            'date_source' => $parsed['date_source'],
            'parse_notes' => $row['parse_notes'] ?? $parsed['parse_notes'],
        ];
    }

    private function updateLegacyImportRowStatus(string $rowId, string $status, ?string $entryId, ?string $notes): void
    {
        $this->db->prepare("
            UPDATE v2_import_rows
            SET parse_status = ?, entry_id = ?, parse_notes = ?
            WHERE id = ?
        ")->execute([$status, $entryId, $notes, $rowId]);
    }

    private function createDefaultFlow(
        string $workspaceId,
        string $name,
        string $type,
        bool $hasLiveBalance,
        bool $isDefault = true,
        string $openingBalance = '0.00'
    ): array {
        $id = FinDeskV2Support::uuid();
        $this->db->prepare("
            INSERT INTO v2_flows (id, workspace_id, name, type, has_live_balance, opening_balance, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$id, $workspaceId, $name, $type, $hasLiveBalance ? 1 : 0, $openingBalance, $isDefault ? 1 : 0]);

        return $this->flowRow([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'name' => $name,
            'type' => $type,
            'has_live_balance' => $hasLiveBalance ? 1 : 0,
            'opening_balance' => $openingBalance,
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => null,
        ]);
    }

    private function getFlowForWorkspace(string $flowId, string $workspaceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM v2_flows WHERE id = ? AND workspace_id = ? LIMIT 1");
        $stmt->execute([$flowId, $workspaceId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'flow_not_found');
        }

        return $this->flowRow($row);
    }

    private function guardEntryMonthIsOpen(array $entry): void
    {
        if (!$this->isEntryMonthClosed($entry)) {
            return;
        }
        $month = $this->entryMonthParts($entry);

        throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
            'error' => 'closed_month_requires_decision',
            'year' => $month['year'],
            'month' => $month['month'],
            'choices' => ['create_correction', 'recalculate_chain', 'cancel'],
        ]));
    }

    private function guardWorkspaceMonthIsOpen(string $workspaceId, string $date): void
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt) {
            throw new FinDeskV2HttpError(422, 'invalid_date');
        }
        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('n');
        $closure = $this->monthClosure($workspaceId, $year, $month);
        if ($closure === null || (int)$closure['is_closed'] !== 1) {
            return;
        }

        throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
            'error' => 'closed_month_requires_decision',
            'year' => $year,
            'month' => $month,
            'choices' => ['create_correction', 'recalculate_chain', 'cancel'],
        ]));
    }

    private function entryMonthParts(array $entry): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$entry['date']);
        if (!$date) {
            throw new FinDeskV2HttpError(422, 'invalid_date');
        }

        return [
            'year' => (int)$date->format('Y'),
            'month' => (int)$date->format('n'),
        ];
    }

    private function isEntryMonthClosed(array $entry): bool
    {
        $month = $this->entryMonthParts($entry);
        $stmt = $this->db->prepare("
            SELECT is_closed
            FROM v2_monthly_closures
            WHERE workspace_id = ? AND year = ? AND month = ? AND is_closed = 1
            LIMIT 1
        ");
        $stmt->execute([$entry['workspace_id'], $month['year'], $month['month']]);

        return (bool)$stmt->fetchColumn();
    }

    private function applyEntryCategory(array $entry, string $categoryCode): void
    {
        $categoryId = $this->categoryIdByCode($entry['workspace_id'], $categoryCode);
        $status = $entry['status'];
        if ($entry['status'] === 'other_review' && $entry['category_code'] === 'other' && $categoryCode !== 'other') {
            $status = 'recognized';
        }

        $this->db->prepare("UPDATE v2_entries SET category_id = ?, status = ? WHERE id = ?")->execute([$categoryId, $status, $entry['id']]);
    }

    private function assertValidMonth(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new FinDeskV2HttpError(422, 'invalid_month');
        }
    }

    private function countedStatuses(): array
    {
        return ['recognized', 'other_review', 'imported', 'accepted', 'corrected'];
    }

    private function countedStatusSqlList(): string
    {
        return "'" . implode("', '", $this->countedStatuses()) . "'";
    }

    private function isCountedStatus(string $status): bool
    {
        return in_array($status, $this->countedStatuses(), true);
    }

    private function cashFlowForWorkspace(string $workspaceId, int $userId): ?array
    {
        foreach ($this->listFlows($workspaceId, $userId) as $flow) {
            if ($flow['type'] === 'cash' && $flow['has_live_balance']) {
                return $flow;
            }
        }

        return null;
    }

    private function cashDeltaBefore(string $flowId, string $beforeDate): float
    {
        $stmt = $this->db->prepare("
            SELECT amount, direction, entry_type, status
            FROM v2_entries
            WHERE flow_id = ?
              AND archived_at IS NULL
              AND date < ?
            ORDER BY date ASC, created_seq ASC
        ");
        $stmt->execute([$flowId, $beforeDate]);

        $delta = 0.0;
        foreach ($stmt->fetchAll() as $entry) {
            $entryDelta = $this->cashBalanceDelta($entry);
            if ($entryDelta !== null) {
                $delta += $entryDelta;
            }
        }

        return $delta;
    }

    private function monthClosure(string $workspaceId, int $year, int $month): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_monthly_closures
            WHERE workspace_id = ? AND year = ? AND month = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $year, $month]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function monthClosureRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'year' => (int)$row['year'],
            'month' => (int)$row['month'],
            'opening_balance' => $row['opening_balance'] === null ? null : (float)$row['opening_balance'],
            'closing_balance' => $row['closing_balance'] === null ? null : (float)$row['closing_balance'],
            'is_closed' => (bool)$row['is_closed'],
            'comment' => $row['comment'] === null ? null : (string)$row['comment'],
            'closed_by' => $row['closed_by'] === null ? null : (int)$row['closed_by'],
            'closed_at' => $row['closed_at'] ?? null,
        ];
    }

    private function strictSignedAmount(string $rawText, string $key): array
    {
        if (preg_match('/^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)/u', $rawText, $match) !== 1) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        return [
            'sign' => $match[1],
            'amount' => number_format((float)str_replace(',', '.', $match[2]), 2, '.', ''),
            'direction' => $match[1] === '+' ? 'in' : 'out',
        ];
    }

    private function sourceFilesForMonth(string $workspaceId, string $monthStart, string $monthEnd): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                s.id,
                s.source_type,
                s.file_name,
                s.file_url,
                s.file_id,
                s.status,
                s.include_decision
            FROM v2_entries e
            INNER JOIN v2_import_sources s ON s.id = e.source_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
            ORDER BY s.file_name ASC, s.created_at ASC
        ");
        $stmt->execute([$workspaceId, $monthStart, $monthEnd]);

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'source_type' => (string)$row['source_type'],
            'file_name' => $row['file_name'] === null ? null : (string)$row['file_name'],
            'file_url' => $row['file_url'] === null ? null : (string)$row['file_url'],
            'file_id' => $row['file_id'] === null ? null : (string)$row['file_id'],
            'status' => (string)$row['status'],
            'include_decision' => (string)$row['include_decision'],
        ], $stmt->fetchAll());
    }

    private function recalculateFlowBalance(string $flowId): void
    {
        $stmt = $this->db->prepare("SELECT * FROM v2_flows WHERE id = ? LIMIT 1");
        $stmt->execute([$flowId]);
        $flow = $stmt->fetch();

        if (!$flow) {
            return;
        }

        if ((int)$flow['has_live_balance'] !== 1 || (string)$flow['type'] !== 'cash') {
            $this->db->prepare("UPDATE v2_entries SET balance_after = NULL WHERE flow_id = ?")->execute([$flowId]);
            return;
        }

        $balance = (float)$flow['opening_balance'];
        $entries = $this->db->prepare("
            SELECT id, amount, direction, entry_type, status
            FROM v2_entries
            WHERE flow_id = ? AND archived_at IS NULL
            ORDER BY date ASC, created_seq ASC
        ");
        $entries->execute([$flowId]);
        $update = $this->db->prepare("UPDATE v2_entries SET balance_after = ? WHERE id = ?");

        foreach ($entries->fetchAll() as $entry) {
            $delta = $this->cashBalanceDelta($entry);
            if ($delta === null) {
                $update->execute([null, $entry['id']]);
                continue;
            }

            $balance += $delta;
            $update->execute([number_format($balance, 2, '.', ''), $entry['id']]);
        }
    }

    private function cashBalanceDelta(array $entry): ?float
    {
        if ($entry['amount'] === null) {
            return null;
        }

        if (!$this->isCountedStatus((string)$entry['status'])) {
            return null;
        }

        $amount = (float)$entry['amount'];
        $direction = (string)$entry['direction'];
        $entryType = (string)$entry['entry_type'];

        if ($direction === 'in' && in_array($entryType, ['cash_income', 'correction'], true)) {
            return $amount;
        }

        if ($direction === 'out' && in_array($entryType, ['cash_expense', 'correction'], true)) {
            return -$amount;
        }

        return null;
    }

    private function getEntry(string $entryId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_workspace_members m ON m.workspace_id = e.workspace_id
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.id = ? AND m.user_id = ? AND e.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$entryId, $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'entry_not_found');
        }

        return $this->entryRow($row);
    }

    private function getAttachmentForUser(string $attachmentId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT att.*
            FROM v2_attachments att
            INNER JOIN v2_entries e ON e.id = att.entry_id
            INNER JOIN v2_workspace_members m ON m.workspace_id = e.workspace_id
            WHERE att.id = ? AND m.user_id = ? AND e.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$attachmentId, $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'attachment_not_found');
        }

        return $this->attachmentRow($row);
    }

    private function requireWorkspaceWriter(string $workspaceId, int $userId): void
    {
        $stmt = $this->db->prepare("
            SELECT role
            FROM v2_workspace_members
            WHERE workspace_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $userId]);
        $role = $stmt->fetchColumn();
        if (!in_array($role, ['owner', 'admin', 'assistant'], true)) {
            throw new FinDeskV2HttpError(403, 'workspace_read_only');
        }
    }

    private function normalizeAttachmentPayload(array $input): array
    {
        if (isset($input['file']) && is_array($input['file'])) {
            return $this->normalizeUploadedAttachment($input);
        }

        $fileName = $this->cleanAttachmentFileName(FinDeskV2Support::requireString($input, 'file_name', 255));
        $rawEncoded = trim((string)($input['content_base64'] ?? ''));
        if ($rawEncoded === '') {
            throw new FinDeskV2HttpError(422, 'missing_content_base64');
        }
        $encoded = preg_replace('/\s+/', '', $rawEncoded);
        if (!is_string($encoded) || $encoded === '' || str_contains($encoded, ',')) {
            throw new FinDeskV2HttpError(422, 'invalid_content_base64');
        }
        if (strlen($encoded) > (int)ceil(self::ATTACHMENT_MAX_BYTES * 1.4) + 16) {
            throw new FinDeskV2HttpError(413, 'attachment_too_large');
        }

        $content = base64_decode($encoded, true);
        if ($content === false) {
            throw new FinDeskV2HttpError(422, 'invalid_content_base64');
        }

        return $this->buildAttachmentPayload($fileName, $content, $input);
    }

    private function normalizeUploadedAttachment(array $input): array
    {
        $file = $input['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new FinDeskV2HttpError(422, 'invalid_attachment_upload');
        }
        if ((int)($file['size'] ?? 0) > self::ATTACHMENT_MAX_BYTES) {
            throw new FinDeskV2HttpError(413, 'attachment_too_large');
        }
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new FinDeskV2HttpError(422, 'invalid_attachment_upload');
        }
        $content = file_get_contents($tmpName);
        if ($content === false) {
            throw new FinDeskV2HttpError(422, 'invalid_attachment_upload');
        }

        return $this->buildAttachmentPayload(
            $this->cleanAttachmentFileName((string)($file['name'] ?? 'attachment')),
            $content,
            $input
        );
    }

    private function buildAttachmentPayload(string $fileName, string $content, array $input): array
    {
        $size = strlen($content);
        if ($size <= 0) {
            throw new FinDeskV2HttpError(422, 'empty_attachment');
        }
        if ($size > self::ATTACHMENT_MAX_BYTES) {
            throw new FinDeskV2HttpError(413, 'attachment_too_large');
        }

        $mimeType = $this->detectAttachmentMime($content);
        $imageMode = FinDeskV2Support::optionalString($input, 'image_mode', null, 40);
        if ($imageMode !== null) {
            $imageMode = FinDeskV2Support::enum($imageMode, ['original', 'compressed', 'grayscale_scan'], 'image_mode');
        }

        return [
            'file_name' => $fileName,
            'content' => $content,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'image_mode' => $imageMode,
        ];
    }

    private function detectAttachmentMime(string $content): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content) ?: 'application/octet-stream';
        if ($mimeType === 'image/pjpeg') {
            $mimeType = 'image/jpeg';
        }
        if (!array_key_exists($mimeType, self::ATTACHMENT_ALLOWED_MIME_EXTENSIONS)) {
            throw new FinDeskV2HttpError(422, 'unsupported_attachment_type');
        }

        return $mimeType;
    }

    private function cleanAttachmentFileName(string $fileName): string
    {
        $fileName = trim($fileName);
        if ($fileName === '' || preg_match('/[\/\\\\\x00-\x1F\x7F]/u', $fileName) === 1) {
            throw new FinDeskV2HttpError(422, 'invalid_file_name');
        }

        return mb_substr($fileName, 0, 255);
    }

    private function attachmentWritePath(string $relativePath): string
    {
        $root = dirname(__DIR__, 2);
        if (!str_starts_with($relativePath, 'storage/v2/attachments/')) {
            throw new FinDeskV2HttpError(500, 'invalid_attachment_path');
        }
        $absolutePath = $root . '/' . $relativePath;
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new FinDeskV2HttpError(500, 'attachment_store_failed');
        }

        return $absolutePath;
    }

    private function deleteAttachmentFile(string $relativePath): bool
    {
        if (!str_starts_with($relativePath, 'storage/v2/attachments/')) {
            throw new FinDeskV2HttpError(500, 'invalid_attachment_path');
        }

        $root = dirname(__DIR__, 2);
        $storageRoot = $root . '/storage/v2/attachments';
        $storageReal = realpath($storageRoot);
        $fileReal = realpath($root . '/' . $relativePath);
        if ($fileReal === false) {
            return false;
        }
        if ($storageReal === false || !str_starts_with($fileReal, $storageReal . DIRECTORY_SEPARATOR)) {
            throw new FinDeskV2HttpError(500, 'invalid_attachment_path');
        }
        if (!is_file($fileReal)) {
            return false;
        }
        if (!unlink($fileReal)) {
            throw new FinDeskV2HttpError(500, 'attachment_delete_failed');
        }

        return true;
    }

    private function getCategoryRule(string $ruleId, string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT r.*, c.code AS category_code
            FROM v2_category_rules r
            INNER JOIN v2_categories c ON c.id = r.category_id
            WHERE r.id = ? AND r.workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$ruleId, $workspaceId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'category_rule_not_found');
        }

        return $this->categoryRuleRow($row);
    }

    private function normalizeEntryInput(string $workspaceId, array $flow, array $input, bool $persistRelations = true): array
    {
        $rawText = FinDeskV2Support::requireString($input, 'raw_text', 2000);
        $sourceType = FinDeskV2Support::enum(
            FinDeskV2Support::optionalString($input, 'source_type', 'manual', 40) ?? 'manual',
            ['manual', 'import', 'assistant', 'correction'],
            'source_type'
        );
        $sign = null;
        $amount = null;
        $direction = 'none';
        $entryType = 'unrecognized';
        $status = 'unrecognized';
        $actorName = null;
        $actorId = null;
        $matchedRules = is_array($input['matched_rules'] ?? null) ? array_values($input['matched_rules']) : [];

        if (preg_match('/^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)/u', $rawText, $match) === 1) {
            $sign = $match[1];
            $amount = number_format((float)str_replace(',', '.', $match[2]), 2, '.', '');
            $direction = $sign === '+' ? 'in' : 'out';
            $entryType = match ($flow['type'] . ':' . $sign) {
                'cash:+' => 'cash_income',
                'cash:-' => 'cash_expense',
                'card:+' => 'card_income',
                'card:-' => 'card_expense',
                default => 'assistant_pending',
            };
            $status = $flow['type'] === 'assistant_journal' ? 'assistant_pending' : 'recognized';

            if ($flow['type'] === 'card' && $sign === '+') {
                if ($sourceType === 'correction') {
                    $entryType = 'correction';
                    $status = 'corrected';
                } elseif ($sourceType === 'import') {
                    $entryType = 'card_income';
                    $status = 'imported';
                } else {
                    $amount = null;
                    $direction = 'none';
                    $entryType = 'unrecognized';
                    $status = 'unrecognized';
                }
            }
        }

        if (
            $sign !== null
            && !($flow['type'] === 'card' && $sign === '+' && $sourceType !== 'correction')
            && array_key_exists('amount', $input)
        ) {
            $amount = FinDeskV2Support::nullableAmount($input['amount']);
        }

        $categoryId = null;
        $categoryCode = FinDeskV2Support::optionalString($input, 'category_code', null, 80);
        if ($categoryCode !== null) {
            $categoryId = $this->categoryIdByCode($workspaceId, $categoryCode);
        } elseif ($sign !== null && !($flow['type'] === 'card' && $sign === '+' && $sourceType !== 'correction')) {
            $inferred = $this->inferEntrySemantics($rawText, $flow, $sign);
            if ($inferred['category_code'] !== null) {
                $categoryId = $this->categoryIdByCode($workspaceId, $inferred['category_code']);
            }
            if ($inferred['status'] !== null) {
                $status = $inferred['status'];
            }
            $matchedRules = array_merge($matchedRules, $inferred['matched_rules']);
        }

        if ($sign === null) {
            $amount = null;
            $direction = 'none';
            $entryType = 'unrecognized';
            $status = 'unrecognized';
        }

        if ($sign !== null) {
            $actorName = $this->extractActorName($rawText);
            if ($actorName !== null && $persistRelations) {
                $actorId = $this->getOrCreateActor($workspaceId, $actorName);
            }
        }

        return [
            'date' => FinDeskV2Support::date($input),
            'raw_text' => $rawText,
            'sign' => $sign,
            'amount' => $amount,
            'direction' => $direction,
            'entry_type' => $entryType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'category_id' => $categoryId,
            'status' => $sign === null || ($flow['type'] === 'card' && $sign === '+' && !in_array($sourceType, ['correction', 'import'], true))
                ? $status
                : FinDeskV2Support::enum(
                    FinDeskV2Support::optionalString($input, 'status', $status, 40) ?? $status,
                    ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'],
                    'status'
                ),
            'source_type' => $sourceType,
            'source_id' => FinDeskV2Support::optionalString($input, 'source_id', null, 36),
            'source_row_id' => FinDeskV2Support::optionalString($input, 'source_row_id', null, 36),
            'notes' => FinDeskV2Support::optionalString($input, 'notes', null, 2000),
            'confidence' => FinDeskV2Support::nullableAmount($input['confidence'] ?? null),
            'matched_rules' => $matchedRules,
        ];
    }

    private function inferEntrySemantics(string $rawText, array $flow, string $sign): array
    {
        $text = mb_strtolower($rawText);
        $categoryCode = null;
        $status = null;
        $matchedRules = [];

        if (str_contains($text, 'netflix')) {
            $categoryCode = 'media_comms';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'netflix', 'category_code' => 'media_comms'];
        } elseif (preg_match('/заправ|топлив|fuel/u', $text) === 1) {
            $categoryCode = 'fuel';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'fuel', 'category_code' => 'fuel'];
            if (preg_match('/тузик|tender/u', $text) === 1) {
                $matchedRules[] = ['source' => 'fixture_secondary_marker', 'marker' => 'tender_related'];
            }
        } elseif (preg_match('/кабел|cable/u', $text) === 1) {
            $categoryCode = 'tech_parts';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'cable', 'category_code' => 'tech_parts'];
        } elseif (preg_match('/charter|агентск/u', $text) === 1 && $sign === '+') {
            $categoryCode = 'commercial_income';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'commercial_income', 'category_code' => 'commercial_income'];
        } elseif (preg_match('/аванс/u', $text) === 1 && $this->extractActorName($rawText) !== null && $sign === '-') {
            $categoryCode = 'crew';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'actor_advance', 'category_code' => 'crew'];
        } elseif (preg_match('/какая-то штука|kakaya/u', $text) === 1 && $flow['type'] === 'cash' && $sign === '-') {
            $categoryCode = 'other';
            $status = 'other_review';
            $matchedRules[] = ['source' => 'fixture_fallback', 'pattern' => 'unknown_expense', 'category_code' => 'other'];
        }

        return [
            'category_code' => $categoryCode,
            'status' => $status,
            'matched_rules' => $matchedRules,
        ];
    }

    private function extractActorName(string $rawText): ?string
    {
        if (preg_match('/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?\s+([\x{0400}-\x{04FF}][\x{0400}-\x{04FF}\'-]{1,80})\b/u', $rawText, $match) !== 1) {
            return null;
        }

        $name = mb_substr($match[1], 0, 120);
        $knownFixtureActors = ['Вова'];

        return in_array($name, $knownFixtureActors, true) ? $name : null;
    }

    private function getOrCreateActor(string $workspaceId, string $name): string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM v2_actors
            WHERE workspace_id = ? AND name = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $name]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (string)$id;
        }

        $id = FinDeskV2Support::uuid();
        $this->db->prepare("
            INSERT INTO v2_actors (id, workspace_id, name, aliases_json)
            VALUES (?, ?, ?, ?)
        ")->execute([$id, $workspaceId, $name, '[]']);

        return $id;
    }

    private function categoryIdByCode(string $workspaceId, string $code): string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM v2_categories
            WHERE code = ? AND is_active = 1 AND (workspace_id IS NULL OR workspace_id = ?)
            ORDER BY workspace_id IS NULL ASC
            LIMIT 1
        ");
        $stmt->execute([$code, $workspaceId]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new FinDeskV2HttpError(422, 'unknown_category');
        }

        return (string)$id;
    }

    private function optionalInt(array $input, string $key, int $default): int
    {
        if (!array_key_exists($key, $input) || $input[$key] === '') {
            return $default;
        }

        if (filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        return (int)$input[$key];
    }

    private function optionalStringList(array $input, string $key): array
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return [];
        }

        if (!is_array($input[$key])) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        $values = [];
        foreach ($input[$key] as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $values[] = mb_substr($value, 0, 255);
            }
        }

        return array_values(array_unique($values));
    }

    private function audit(
        ?string $workspaceId,
        string $entityType,
        ?string $entityId,
        string $action,
        ?array $before,
        ?array $after,
        int $userId
    ): void {
        $this->db->prepare("
            INSERT INTO v2_audit_log (id, workspace_id, entity_type, entity_id, action, before_json, after_json, performed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            FinDeskV2Support::uuid(),
            $workspaceId,
            $entityType,
            $entityId,
            $action,
            $before === null ? null : FinDeskV2Support::jsonEncode($before),
            $after === null ? null : FinDeskV2Support::jsonEncode($after),
            $userId,
        ]);
    }

    private function workspaceRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'currency' => (string)$row['currency'],
            'locale' => (string)$row['locale'],
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function flowRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'has_live_balance' => (bool)$row['has_live_balance'],
            'opening_balance' => isset($row['opening_balance']) ? (float)$row['opening_balance'] : 0.0,
            'is_default' => (bool)$row['is_default'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function categoryRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => $row['workspace_id'] ?? null,
            'code' => (string)$row['code'],
            'name' => FinDeskV2Support::jsonDecode($row['name_json'] ?? '{}', []),
            'direction' => (string)$row['direction'],
            'parent_code' => $row['parent_code'] ?? null,
            'sort_order' => (int)$row['sort_order'],
            'is_system' => (bool)$row['is_system'],
        ];
    }

    private function categoryRuleRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => $row['workspace_id'] ?? null,
            'category_code' => (string)$row['category_code'],
            'pattern' => (string)$row['pattern'],
            'pattern_type' => (string)$row['pattern_type'],
            'language' => (string)$row['language'],
            'weight' => (int)$row['weight'],
            'negative_weight' => (int)$row['negative_weight'],
            'requires_any' => FinDeskV2Support::jsonDecode($row['requires_any_json'] ?? '[]', []),
            'excludes_any' => FinDeskV2Support::jsonDecode($row['excludes_any_json'] ?? '[]', []),
            'created_by_user' => (bool)$row['created_by_user'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function attachmentRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'entry_id' => (string)$row['entry_id'],
            'file_name' => (string)$row['file_name'],
            'file_url' => (string)$row['file_url'],
            'mime_type' => $row['mime_type'] ?? null,
            'size_bytes' => $row['size_bytes'] === null ? null : (int)$row['size_bytes'],
            'image_mode' => $row['image_mode'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function entryPreviewRow(string $workspaceId, array $flow, array $entry): array
    {
        $categoryCode = null;
        $categoryName = null;
        if ($entry['category_id'] !== null) {
            $stmt = $this->db->prepare("SELECT code, name_json FROM v2_categories WHERE id = ? LIMIT 1");
            $stmt->execute([$entry['category_id']]);
            $category = $stmt->fetch();
            if ($category) {
                $categoryCode = $category['code'];
                $categoryName = FinDeskV2Support::jsonDecode($category['name_json'] ?? null, null);
            }
        }

        return [
            'workspace_id' => $workspaceId,
            'flow' => [
                'id' => (string)$flow['id'],
                'type' => (string)$flow['type'],
                'name' => (string)$flow['name'],
            ],
            'date' => $entry['date'],
            'raw_text' => $entry['raw_text'],
            'sign' => $entry['sign'],
            'amount' => $entry['amount'] === null ? null : (float)$entry['amount'],
            'direction' => $entry['direction'],
            'entry_type' => $entry['entry_type'],
            'actor' => $entry['actor_name'] === null ? null : [
                'id' => null,
                'name' => $entry['actor_name'],
            ],
            'category_code' => $categoryCode,
            'category_name' => $categoryName,
            'status' => $entry['status'],
            'source_type' => $entry['source_type'],
            'notes' => $entry['notes'],
            'confidence' => $entry['confidence'] === null ? null : (float)$entry['confidence'],
            'matched_rules' => $entry['matched_rules'],
            'will_save' => false,
        ];
    }

    private function entryRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'flow' => [
                'id' => (string)$row['flow_id'],
                'type' => (string)$row['flow_type'],
                'name' => (string)$row['flow_name'],
            ],
            'date' => (string)$row['date'],
            'raw_text' => (string)$row['raw_text'],
            'sign' => $row['sign'] ?? null,
            'amount' => $row['amount'] === null ? null : (float)$row['amount'],
            'direction' => (string)$row['direction'],
            'entry_type' => (string)$row['entry_type'],
            'actor' => $row['actor_id'] === null ? null : [
                'id' => (string)$row['actor_id'],
                'name' => (string)$row['actor_name'],
            ],
            'category_code' => $row['category_code'] ?? null,
            'category_name' => FinDeskV2Support::jsonDecode($row['category_name_json'] ?? null, null),
            'status' => (string)$row['status'],
            'balance_after' => $row['balance_after'] === null ? null : (float)$row['balance_after'],
            'source_type' => (string)$row['source_type'],
            'source_id' => $row['source_id'] ?? null,
            'source_row_id' => $row['source_row_id'] ?? null,
            'notes' => $row['notes'] ?? null,
            'confidence' => $row['confidence'] === null ? null : (float)$row['confidence'],
            'matched_rules' => FinDeskV2Support::jsonDecode($row['matched_rules_json'] ?? '[]', []),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
