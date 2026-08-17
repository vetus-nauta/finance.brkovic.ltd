<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ClaudiaZReconciliationAudit
{
    private PDO $db;
    private FinDeskV2Repository $repo;

    private string $email;
    private string $workspaceId;
    private string $archiveWorkspaceId;
    private int $userId;

    public function __construct()
    {
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
        $this->email = (string)(getenv('FINDESK_V2_CLAUDIA_Z_OWNER_EMAIL') ?: 'vetus.nauta@gmail.com');
        $this->workspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed');
        $this->archiveWorkspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_ARCHIVE_WORKSPACE_ID') ?: '3bb2f598-540e-4878-9d92-aad24a7d12ac');
    }

    public function run(): void
    {
        $this->userId = $this->userId();
        $audit = [
            'generated_at' => date(DATE_ATOM),
            'beta_account' => $this->accountSummary(),
            'workspace' => $this->workspaceSummary($this->workspaceId),
            'archive_workspace' => $this->workspaceSummary($this->archiveWorkspaceId),
            'cash_chain' => $this->cashChain(),
            'entry_distribution' => [
                'by_month' => $this->rows("
                    SELECT DATE_FORMAT(e.date, '%Y-%m') AS month_key, COUNT(*) AS entries_count,
                           COALESCE(SUM(CASE WHEN e.status = 'other_review' THEN 1 ELSE 0 END), 0) AS other_review_count,
                           COALESCE(SUM(CASE WHEN e.status = 'unrecognized' THEN 1 ELSE 0 END), 0) AS unrecognized_count
                    FROM v2_entries e
                    WHERE e.workspace_id = ? AND e.archived_at IS NULL
                    GROUP BY DATE_FORMAT(e.date, '%Y-%m')
                    ORDER BY month_key
                ", [$this->workspaceId]),
                'by_flow_status' => $this->rows("
                    SELECT f.type AS flow_type, e.status, COUNT(*) AS entries_count, COALESCE(SUM(e.amount), 0) AS total
                    FROM v2_entries e
                    INNER JOIN v2_flows f ON f.id = e.flow_id
                    WHERE e.workspace_id = ? AND e.archived_at IS NULL
                    GROUP BY f.type, e.status
                    ORDER BY f.type, e.status
                ", [$this->workspaceId]),
                'by_entry_type' => $this->rows("
                    SELECT e.entry_type, e.direction, COUNT(*) AS entries_count, COALESCE(SUM(e.amount), 0) AS total
                    FROM v2_entries e
                    WHERE e.workspace_id = ? AND e.archived_at IS NULL
                    GROUP BY e.entry_type, e.direction
                    ORDER BY e.entry_type, e.direction
                ", [$this->workspaceId]),
            ],
            'imports' => [
                'current_workspace_sources' => $this->importSourceSummary($this->workspaceId),
                'current_workspace_rows' => $this->importRowSummary($this->workspaceId),
                'archive_sources' => $this->importSourceSummary($this->archiveWorkspaceId),
                'archive_rows' => $this->importRowSummary($this->archiveWorkspaceId),
                'current_files' => $this->rows("
                    SELECT file_name, include_decision, status, COUNT(r.id) AS rows_count,
                           COALESCE(SUM(CASE WHEN r.entry_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS linked_entries
                    FROM v2_import_sources s
                    LEFT JOIN v2_import_rows r ON r.import_source_id = s.id
                    WHERE s.workspace_id = ?
                    GROUP BY s.id
                    ORDER BY s.file_name
                ", [$this->workspaceId]),
            ],
            'classification' => [
                'category_totals' => $this->categoryTotals(),
                'review_examples' => $this->reviewExamples(),
                'unrecognized_examples' => $this->unrecognizedExamples(),
                'duplicate_suspects' => $this->duplicateSuspects(),
            ],
            'layer1_latest_month' => $this->latestLayer1Summary(),
            'flags' => [],
        ];

        $audit['flags'] = $this->flags($audit);
        $this->writeArtifact($audit);
        $this->printSummary($audit);
    }

    private function userId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$this->email]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException("Active beta user not found: {$this->email}");
        }

        return (int)$id;
    }

    private function accountSummary(): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.display_name, u.status, m.role
            FROM users u
            LEFT JOIN v2_workspace_members m ON m.user_id = u.id AND m.workspace_id = ?
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$this->workspaceId, $this->userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Beta user disappeared during audit.');
        }

        return $this->castRow($row);
    }

    private function workspaceSummary(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.id, w.name, w.type, w.currency, w.locale,
                   COUNT(DISTINCT e.id) AS entries_count,
                   COUNT(DISTINCT s.id) AS import_sources_count,
                   COUNT(DISTINCT r.id) AS import_rows_count
            FROM v2_workspaces w
            LEFT JOIN v2_entries e ON e.workspace_id = w.id AND e.archived_at IS NULL
            LEFT JOIN v2_import_sources s ON s.workspace_id = w.id
            LEFT JOIN v2_import_rows r ON r.import_source_id = s.id
            WHERE w.id = ? AND w.archived_at IS NULL
            GROUP BY w.id
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Workspace not found: {$workspaceId}");
        }

        return $this->castRow($row);
    }

    private function cashChain(): array
    {
        $cashFlow = $this->one("
            SELECT *
            FROM v2_flows
            WHERE workspace_id = ? AND type = 'cash' AND has_live_balance = 1
            ORDER BY is_default DESC, created_at ASC
            LIMIT 1
        ", [$this->workspaceId]);
        if ($cashFlow === null) {
            return ['error' => 'cash_flow_not_found'];
        }

        $entries = $this->rows("
            SELECT id, date, created_seq, raw_text, amount, direction, entry_type, status, balance_after
            FROM v2_entries
            WHERE flow_id = ? AND archived_at IS NULL
            ORDER BY date ASC, created_seq ASC
        ", [$cashFlow['id']]);

        $computed = (float)$cashFlow['opening_balance'];
        $lastBalanceAfter = null;
        $mismatches = [];
        foreach ($entries as $entry) {
            $delta = $this->cashDelta($entry);
            if ($delta !== null) {
                $computed += $delta;
            }
            if ($entry['balance_after'] !== null) {
                $lastBalanceAfter = (float)$entry['balance_after'];
                if (abs($computed - $lastBalanceAfter) > 0.01) {
                    $mismatches[] = [
                        'id' => $entry['id'],
                        'date' => $entry['date'],
                        'raw_text' => $entry['raw_text'],
                        'computed' => round($computed, 2),
                        'balance_after' => round($lastBalanceAfter, 2),
                    ];
                }
            }
        }

        return [
            'cash_flow_id' => (string)$cashFlow['id'],
            'opening_cash' => round((float)$cashFlow['opening_balance'], 2),
            'computed_cash_now' => round($computed, 2),
            'latest_balance_after' => $lastBalanceAfter === null ? null : round($lastBalanceAfter, 2),
            'difference' => $lastBalanceAfter === null ? null : round($computed - $lastBalanceAfter, 2),
            'cash_entries_count' => count($entries),
            'balance_mismatch_count' => count($mismatches),
            'balance_mismatches' => array_slice($mismatches, 0, 20),
        ];
    }

    private function cashDelta(array $entry): ?float
    {
        if ($entry['amount'] === null) {
            return null;
        }
        if (!in_array((string)$entry['status'], ['recognized', 'other_review', 'imported', 'accepted', 'corrected'], true)) {
            return null;
        }
        if ((string)$entry['direction'] === 'in' && in_array((string)$entry['entry_type'], ['cash_income', 'correction'], true)) {
            return (float)$entry['amount'];
        }
        if ((string)$entry['direction'] === 'out' && in_array((string)$entry['entry_type'], ['cash_expense', 'correction'], true)) {
            return -(float)$entry['amount'];
        }

        return null;
    }

    private function importSourceSummary(string $workspaceId): array
    {
        return $this->rows("
            SELECT include_decision, status, COUNT(*) AS sources_count
            FROM v2_import_sources
            WHERE workspace_id = ?
            GROUP BY include_decision, status
            ORDER BY include_decision, status
        ", [$workspaceId]);
    }

    private function importRowSummary(string $workspaceId): array
    {
        return $this->rows("
            SELECT r.parse_status, COUNT(*) AS rows_count,
                   COALESCE(SUM(CASE WHEN r.entry_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS linked_entries
            FROM v2_import_rows r
            INNER JOIN v2_import_sources s ON s.id = r.import_source_id
            WHERE s.workspace_id = ?
            GROUP BY r.parse_status
            ORDER BY r.parse_status
        ", [$workspaceId]);
    }

    private function categoryTotals(): array
    {
        return $this->rows("
            SELECT COALESCE(c.code, 'no_category') AS category_code,
                   f.type AS flow_type,
                   e.direction,
                   COUNT(*) AS entries_count,
                   COALESCE(SUM(e.amount), 0) AS total
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.amount IS NOT NULL
              AND e.status IN ('recognized', 'other_review', 'imported', 'accepted', 'corrected')
            GROUP BY COALESCE(c.code, 'no_category'), f.type, e.direction
            ORDER BY total DESC, entries_count DESC
        ", [$this->workspaceId]);
    }

    private function reviewExamples(): array
    {
        return $this->rows("
            SELECT e.id, e.date, f.type AS flow_type, e.raw_text, e.amount, c.code AS category_code, e.status
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.status = 'other_review'
            ORDER BY e.date ASC, e.created_seq ASC
            LIMIT 40
        ", [$this->workspaceId]);
    }

    private function unrecognizedExamples(): array
    {
        return $this->rows("
            SELECT e.id, e.date, f.type AS flow_type, e.raw_text, e.amount, e.status
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.status = 'unrecognized'
            ORDER BY e.date ASC, e.created_seq ASC
            LIMIT 40
        ", [$this->workspaceId]);
    }

    private function duplicateSuspects(): array
    {
        return $this->rows("
            SELECT s.file_name, r.sheet_name, r.row_number, r.parse_status, r.parse_notes, r.raw_json
            FROM v2_import_rows r
            INNER JOIN v2_import_sources s ON s.id = r.import_source_id
            WHERE s.workspace_id = ?
              AND r.parse_status = 'duplicate_suspect'
            ORDER BY s.file_name, r.sheet_name, r.row_number
            LIMIT 40
        ", [$this->workspaceId]);
    }

    private function latestLayer1Summary(): ?array
    {
        $row = $this->one("
            SELECT YEAR(date) AS year, MONTH(date) AS month
            FROM v2_entries
            WHERE workspace_id = ? AND archived_at IS NULL
            ORDER BY date DESC, created_seq DESC
            LIMIT 1
        ", [$this->workspaceId]);
        if ($row === null) {
            return null;
        }

        $report = $this->repo->getLayer1SummaryReport($this->workspaceId, [
            'year' => (int)$row['year'],
            'month' => (int)$row['month'],
        ], $this->userId);

        return [
            'month_key' => $report['header']['period']['month_key'] ?? null,
            'status' => $report['header']['status'] ?? null,
            'entries_count' => $report['header']['entries_count'] ?? null,
            'review_count' => $report['header']['review_count'] ?? null,
            'totals' => $report['totals'] ?? [],
            'other_review_count' => $report['blocks']['other_review']['count'] ?? null,
            'lower_accounting_count' => $report['blocks']['lower_accounting']['count'] ?? null,
            'lower_accounting_total' => $report['blocks']['lower_accounting']['total'] ?? null,
            'lower_accounting_settlements' => $report['blocks']['lower_accounting']['settlements'] ?? [],
        ];
    }

    private function flags(array $audit): array
    {
        $flags = [];
        $cash = $audit['cash_chain'];
        if (($cash['balance_mismatch_count'] ?? 0) > 0) {
            $flags[] = 'cash_balance_after_mismatches_present';
        }
        if (($cash['difference'] ?? 0) !== null && abs((float)$cash['difference']) > 0.01) {
            $flags[] = 'cash_latest_balance_differs_from_computed';
        }
        if ((int)($audit['workspace']['entries_count'] ?? 0) === 0) {
            $flags[] = 'primary_workspace_has_no_entries';
        }
        if ((int)($audit['archive_workspace']['import_rows_count'] ?? 0) === 0) {
            $flags[] = 'archive_has_no_raw_rows';
        }
        if (count($audit['classification']['review_examples']) > 0) {
            $flags[] = 'manual_review_items_present';
        }
        if (count($audit['classification']['unrecognized_examples']) > 0) {
            $flags[] = 'unrecognized_entries_present';
        }

        return $flags;
    }

    private function writeArtifact(array $audit): void
    {
        $dir = dirname(__DIR__) . '/storage/imports/claudia-z-reconciliation';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create artifact dir: {$dir}");
        }
        file_put_contents($dir . '/sprint-41-reconciliation-audit.json', json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function printSummary(array $audit): void
    {
        echo "SPRINT-41R Claudia Z reconciliation audit\n";
        echo "account={$audit['beta_account']['email']} user_id={$audit['beta_account']['id']} role={$audit['beta_account']['role']}\n";
        echo "workspace={$audit['workspace']['id']} entries={$audit['workspace']['entries_count']} import_rows={$audit['workspace']['import_rows_count']}\n";
        echo "archive={$audit['archive_workspace']['id']} import_rows={$audit['archive_workspace']['import_rows_count']}\n";
        echo "cash opening={$audit['cash_chain']['opening_cash']} computed={$audit['cash_chain']['computed_cash_now']} latest_balance_after={$audit['cash_chain']['latest_balance_after']} diff={$audit['cash_chain']['difference']} mismatches={$audit['cash_chain']['balance_mismatch_count']}\n";
        echo "latest_layer1_month=" . ($audit['layer1_latest_month']['month_key'] ?? 'none') . " review_count=" . ($audit['layer1_latest_month']['review_count'] ?? 'n/a') . " lower_accounting_total=" . ($audit['layer1_latest_month']['lower_accounting_total'] ?? 'n/a') . "\n";
        echo "flags=" . implode(',', $audit['flags']) . "\n";
        echo "artifact=storage/imports/claudia-z-reconciliation/sprint-41-reconciliation-audit.json\n";
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ? $this->castRow($row) : null;
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'castRow'], $stmt->fetchAll());
    }

    private function castRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            if (is_numeric($value) && preg_match('/^-?\d+$/', (string)$value) === 1) {
                $out[$key] = (int)$value;
            } elseif (is_numeric($value) && preg_match('/^-?\d+\.\d+$/', (string)$value) === 1) {
                $out[$key] = round((float)$value, 2);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}

(new ClaudiaZReconciliationAudit())->run();
