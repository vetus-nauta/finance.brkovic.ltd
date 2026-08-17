<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ClaudiaZOriginMergeDryRun
{
    private PDO $db;
    private string $workspaceId;
    private string $archiveWorkspaceId;
    private string $outputDir;

    public function __construct()
    {
        $this->db = ql_db();
        $this->workspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed');
        $this->archiveWorkspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_ARCHIVE_WORKSPACE_ID') ?: '3bb2f598-540e-4878-9d92-aad24a7d12ac');
        $this->outputDir = dirname(__DIR__) . '/storage/imports/claudia-z-origin-merge';
    }

    public function run(): void
    {
        $currentKeys = $this->entryKeys($this->workspaceId);
        $archiveEntries = $this->entries($this->archiveWorkspaceId);
        $currentBefore = $this->workspaceSnapshot($this->workspaceId);
        $archiveBefore = $this->workspaceSnapshot($this->archiveWorkspaceId);
        $report = [
            'generated_at' => date(DATE_ATOM),
            'mode' => 'dry_run_no_db_writes',
            'purpose' => 'Preview copying non-duplicate Archive Raw History entries into Claudia Z operational feed while preserving current cash truth.',
            'workspaces' => [
                'current' => $currentBefore,
                'archive_history' => $archiveBefore,
            ],
            'archive_entries_scanned' => count($archiveEntries),
            'duplicates_against_current' => 0,
            'candidates' => [
                'count' => 0,
                'first_date' => null,
                'last_date' => null,
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
                'cash_net' => 0.0,
                'by_month' => [],
                'by_category' => [],
                'samples' => [],
            ],
            'opening_adjustment' => [
                'current_cash_now_truth' => $currentBefore['cash_now'],
                'old_current_cash_opening' => $currentBefore['cash_flow']['opening_balance'],
                'candidate_cash_net' => 0.0,
                'target_current_cash_opening' => $currentBefore['cash_flow']['opening_balance'],
            ],
            'risks' => [],
        ];

        foreach ($archiveEntries as $entry) {
            $key = $this->entryKey($entry);
            if (isset($currentKeys[$key])) {
                $report['duplicates_against_current']++;
                continue;
            }

            $this->accumulateCandidate($report['candidates'], $entry);
        }

        $cashNet = (float)$report['candidates']['cash_net'];
        $report['opening_adjustment']['candidate_cash_net'] = $cashNet;
        $report['opening_adjustment']['target_current_cash_opening'] = (float)$currentBefore['cash_flow']['opening_balance'] - $cashNet;
        $report['risks'] = $this->risks($report);
        $path = $this->writeArtifact($report);
        $this->printSummary($report, $path);
    }

    /** @return array<string, true> */
    private function entryKeys(string $workspaceId): array
    {
        $keys = [];
        foreach ($this->entries($workspaceId) as $entry) {
            $keys[$this->entryKey($entry)] = true;
        }

        return $keys;
    }

    /** @return array<int, array<string, mixed>> */
    private function entries(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.id, e.created_seq, e.workspace_id, e.flow_id, f.type AS flow_type,
                   e.created_by, e.actor_id, e.date, e.raw_text, e.sign, e.amount,
                   e.direction, e.entry_type, e.category_id, c.code AS category_code,
                   e.status, e.balance_after, e.source_type, e.source_id, e.source_row_id,
                   e.notes, e.confidence, e.matched_rules_json, e.created_at
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.sign IS NOT NULL
              AND e.amount IS NOT NULL
              AND e.direction IN ('in', 'out')
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute([$workspaceId]);

        return $stmt->fetchAll();
    }

    private function entryKey(array $entry): string
    {
        $description = preg_replace('/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?\s*/u', '', (string)$entry['raw_text']);

        return implode('|', [
            (string)$entry['date'],
            (string)$entry['flow_type'],
            (string)$entry['sign'],
            number_format((float)$entry['amount'], 2, '.', ''),
            mb_strtolower(trim((string)$description)),
        ]);
    }

    private function accumulateCandidate(array &$summary, array $entry): void
    {
        $date = (string)$entry['date'];
        $flowType = (string)$entry['flow_type'];
        $sign = (string)$entry['sign'];
        $amount = (float)$entry['amount'];
        $kind = $flowType . '_' . ($sign === '+' ? 'income' : 'expense');
        $month = substr($date, 0, 7);
        $category = (string)($entry['category_code'] ?? 'no_category');

        $summary['count']++;
        $summary['first_date'] = $summary['first_date'] === null ? $date : min((string)$summary['first_date'], $date);
        $summary['last_date'] = $summary['last_date'] === null ? $date : max((string)$summary['last_date'], $date);
        if (array_key_exists($kind, $summary)) {
            $summary[$kind] += $amount;
        }
        if ($flowType === 'cash') {
            $summary['cash_net'] += $sign === '+' ? $amount : -$amount;
        }
        if (!isset($summary['by_month'][$month])) {
            $summary['by_month'][$month] = [
                'count' => 0,
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
                'cash_net' => 0.0,
            ];
        }
        $summary['by_month'][$month]['count']++;
        if (array_key_exists($kind, $summary['by_month'][$month])) {
            $summary['by_month'][$month][$kind] += $amount;
        }
        if ($flowType === 'cash') {
            $summary['by_month'][$month]['cash_net'] += $sign === '+' ? $amount : -$amount;
        }
        $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;
        if (count($summary['samples']) < 80) {
            $summary['samples'][] = [
                'created_seq' => (int)$entry['created_seq'],
                'entry_id' => (string)$entry['id'],
                'date' => $date,
                'flow_type' => $flowType,
                'raw_text' => (string)$entry['raw_text'],
                'amount' => $amount,
                'category_code' => $category,
                'source_row_id' => $entry['source_row_id'] === null ? null : (string)$entry['source_row_id'],
            ];
        }
    }

    private function workspaceSnapshot(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS entries_count, MIN(date) AS first_date, MAX(date) AS last_date
            FROM v2_entries
            WHERE workspace_id = ?
              AND archived_at IS NULL
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch() ?: [];
        $cashFlow = $this->cashFlow($workspaceId);
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount WHEN direction = 'out' THEN -amount ELSE 0 END), 0)
            FROM v2_entries
            WHERE flow_id = ?
              AND archived_at IS NULL
        ");
        $stmt->execute([(string)$cashFlow['id']]);
        $cashNet = (float)$stmt->fetchColumn();
        $opening = (float)$cashFlow['opening_balance'];

        return [
            'workspace_id' => $workspaceId,
            'entries_count' => (int)($row['entries_count'] ?? 0),
            'first_date' => $row['first_date'] === null ? null : (string)$row['first_date'],
            'last_date' => $row['last_date'] === null ? null : (string)$row['last_date'],
            'cash_flow' => [
                'id' => (string)$cashFlow['id'],
                'opening_balance' => $opening,
                'net' => $cashNet,
            ],
            'cash_now' => $opening + $cashNet,
        ];
    }

    private function cashFlow(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, opening_balance
            FROM v2_flows
            WHERE workspace_id = ?
              AND type = 'cash'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $flow = $stmt->fetch();
        if (!$flow) {
            throw new RuntimeException("Cash flow not found for {$workspaceId}");
        }

        return $flow;
    }

    /** @return array<int, string> */
    private function risks(array $report): array
    {
        $risks = [];
        if ((int)$report['candidates']['count'] > 800) {
            $risks[] = 'Large candidate count; apply must be reviewed by month before commit.';
        }
        if (abs((float)$report['candidates']['cash_net']) > 10000) {
            $risks[] = 'Large cash opening adjustment; current cash truth can be preserved, but historical opening will change visibly.';
        }
        if ((string)($report['candidates']['first_date'] ?? '') < '2025-04-01') {
            $risks[] = 'Candidates include pre-April-2025 history; this changes Claudia Z from current workspace into full historical workspace.';
        }

        return $risks;
    }

    private function writeArtifact(array $payload): string
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException("Cannot create {$this->outputDir}");
        }
        $path = $this->outputDir . '/dry-run-' . date('Ymd-His') . '.json';
        file_put_contents($path, FinDeskV2Support::jsonEncode($payload));

        return $path;
    }

    private function printSummary(array $report, string $path): void
    {
        echo "Claudia Z origin merge dry-run\n";
        echo "Artifact: {$path}\n";
        echo "Archive scanned: {$report['archive_entries_scanned']}\n";
        echo "Duplicates against current: {$report['duplicates_against_current']}\n";
        echo "Candidates: {$report['candidates']['count']}\n";
        echo "Candidate dates: " . ($report['candidates']['first_date'] ?? '-') . " .. " . ($report['candidates']['last_date'] ?? '-') . "\n";
        echo "Cash income/expense: "
            . number_format((float)$report['candidates']['cash_income'], 2, '.', '')
            . " / "
            . number_format((float)$report['candidates']['cash_expense'], 2, '.', '')
            . "\n";
        echo "Card income/expense: "
            . number_format((float)$report['candidates']['card_income'], 2, '.', '')
            . " / "
            . number_format((float)$report['candidates']['card_expense'], 2, '.', '')
            . "\n";
        echo "Cash net: " . number_format((float)$report['candidates']['cash_net'], 2, '.', '') . "\n";
        echo "Opening cash: "
            . number_format((float)$report['opening_adjustment']['old_current_cash_opening'], 2, '.', '')
            . " -> "
            . number_format((float)$report['opening_adjustment']['target_current_cash_opening'], 2, '.', '')
            . "\n";
        if ($report['risks'] !== []) {
            echo "Risks:\n";
            foreach ($report['risks'] as $risk) {
                echo "  - {$risk}\n";
            }
        }
    }
}

(new ClaudiaZOriginMergeDryRun())->run();
