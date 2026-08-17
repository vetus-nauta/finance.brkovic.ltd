<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ExportClaudiaZApril2025Feed
{
    private PDO $db;
    private FinDeskV2Repository $repo;
    private ReflectionMethod $recalculateFlowBalance;

    private string $targetWorkspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
    private string $archiveWorkspaceId = '3bb2f598-540e-4878-9d92-aad24a7d12ac';
    private string $rangeStart = '2025-04-01';
    private string $rangeEnd = '2025-12-31';
    private int $userId;
    private bool $commit;

    public function __construct(bool $commit)
    {
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
        $this->recalculateFlowBalance = new ReflectionMethod(FinDeskV2Repository::class, 'recalculateFlowBalance');
        $this->recalculateFlowBalance->setAccessible(true);
        $this->commit = $commit;
    }

    public function run(): void
    {
        $this->userId = $this->userId();
        $flows = [
            'target' => $this->flowsByType($this->targetWorkspaceId),
            'archive' => $this->flowsByType($this->archiveWorkspaceId),
        ];
        $targetCashFlowId = (string)$flows['target']['cash']['id'];
        $oldTargetSnapshot = $this->cashSnapshot($this->targetWorkspaceId);
        $archiveOpeningBeforeStart = $this->cashBalanceBefore($this->archiveWorkspaceId, $this->rangeStart);
        $targetNetBeforeStart = $this->cashNetBefore($this->targetWorkspaceId, $this->rangeStart);
        $targetOpening = $archiveOpeningBeforeStart - $targetNetBeforeStart;
        $candidates = $this->missingArchiveEntries($flows);
        $rangeCashNet = $this->cashNet($candidates);
        $joinDate = $this->firstTargetDateAfter($this->rangeEnd) ?? '2026-01-01';
        $oldBalanceAtJoin = $this->cashBalanceBeforeWithOpening(
            (float)$oldTargetSnapshot['opening_balance'],
            $this->targetWorkspaceId,
            $joinDate
        );
        $targetNetBetween = $this->cashNetBetween($this->targetWorkspaceId, $this->rangeStart, $joinDate);
        $newBalanceAtJoinWithoutSeam = $targetOpening + $targetNetBeforeStart + $rangeCashNet + $targetNetBetween;
        $seamDelta = round($oldBalanceAtJoin - $newBalanceAtJoinWithoutSeam, 2);
        $seamNeeded = abs($seamDelta) >= 0.01 && !$this->existingSeamEntry();

        $plan = [
            'mode' => $this->commit ? 'commit' : 'preview',
            'target_workspace_id' => $this->targetWorkspaceId,
            'archive_workspace_id' => $this->archiveWorkspaceId,
            'range' => [$this->rangeStart, $this->rangeEnd],
            'missing_entries' => count($candidates),
            'archive_opening_before_start' => round($archiveOpeningBeforeStart, 2),
            'range_cash_net' => round($rangeCashNet, 2),
            'old_target_opening' => round((float)$oldTargetSnapshot['opening_balance'], 2),
            'new_target_opening' => round($targetOpening, 2),
            'old_target_cash_now' => round((float)$oldTargetSnapshot['cash_now'], 2),
            'join_date' => $joinDate,
            'old_balance_at_join' => round($oldBalanceAtJoin, 2),
            'new_balance_at_join_without_seam' => round($newBalanceAtJoinWithoutSeam, 2),
            'seam_delta' => $seamNeeded ? $seamDelta : 0.0,
            'backup' => null,
            'created_entries' => 0,
            'created_seam' => false,
            'after' => null,
        ];

        if (!$this->commit) {
            echo FinDeskV2Support::jsonEncode($plan) . PHP_EOL;
            return;
        }

        $plan['backup'] = $this->writeBackup();
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE v2_flows SET opening_balance = ? WHERE id = ?")
                ->execute([number_format($targetOpening, 2, '.', ''), $targetCashFlowId]);

            $insert = $this->db->prepare("
                INSERT INTO v2_entries (
                    id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                    entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
                )
                VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($candidates as $entry) {
                $flowType = (string)$entry['flow_type'];
                $flowId = (string)$flows['target'][$flowType]['id'];
                $rules = FinDeskV2Support::jsonDecode($entry['matched_rules_json'] ?? null, []);
                if (!is_array($rules)) {
                    $rules = [];
                }
                $rules[] = [
                    'source' => 'claudia_z_april_2025_feed_export',
                    'archive_entry_id' => (string)$entry['id'],
                    'archive_created_seq' => (int)$entry['created_seq'],
                    'range' => [$this->rangeStart, $this->rangeEnd],
                ];
                $insert->execute([
                    FinDeskV2Support::uuid(),
                    $this->targetWorkspaceId,
                    $flowId,
                    $this->userId,
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
                    FinDeskV2Support::jsonEncode($rules),
                ]);
                $plan['created_entries']++;
            }

            if ($seamNeeded) {
                $this->insertSeamEntry($seamDelta, $targetCashFlowId);
                $plan['created_seam'] = true;
            }

            $this->recalculateFlowBalance->invoke($this->repo, $targetCashFlowId);
            $after = $this->cashSnapshot($this->targetWorkspaceId);
            if (abs((float)$after['cash_now'] - (float)$oldTargetSnapshot['cash_now']) > 0.01) {
                throw new RuntimeException('Cash truth changed after export.');
            }
            $plan['after'] = [
                'opening_balance' => round((float)$after['opening_balance'], 2),
                'cash_now' => round((float)$after['cash_now'], 2),
                'entries_count' => (int)$after['entries_count'],
                'first_date' => $after['first_date'],
                'last_date' => $after['last_date'],
            ];
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        echo FinDeskV2Support::jsonEncode($plan) . PHP_EOL;
    }

    private function userId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute(['vetus.nauta@gmail.com']);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException('Active owner user not found.');
        }

        return (int)$id;
    }

    /** @return array<string, array<string, mixed>> */
    private function flowsByType(string $workspaceId): array
    {
        $flows = [];
        foreach ($this->repo->listFlows($workspaceId, $this->userId) as $flow) {
            $flows[(string)$flow['type']] = $flow;
        }
        foreach (['cash', 'card'] as $type) {
            if (!isset($flows[$type])) {
                throw new RuntimeException("Missing {$type} flow for {$workspaceId}");
            }
        }

        return $flows;
    }

    /** @return array<int, array<string, mixed>> */
    private function missingArchiveEntries(array $flows): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, f.type AS flow_type
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_entries existing
                ON existing.workspace_id = ?
               AND existing.source_row_id = e.source_row_id
               AND existing.archived_at IS NULL
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date BETWEEN ? AND ?
              AND e.source_row_id IS NOT NULL
              AND existing.id IS NULL
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute([$this->targetWorkspaceId, $this->archiveWorkspaceId, $this->rangeStart, $this->rangeEnd]);
        $entries = $stmt->fetchAll();
        foreach ($entries as $entry) {
            $flowType = (string)$entry['flow_type'];
            if (!isset($flows['target'][$flowType])) {
                throw new RuntimeException("Target flow missing for {$flowType}");
            }
        }

        return $entries;
    }

    private function cashSnapshot(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT f.id, f.opening_balance,
                   COUNT(e.id) AS entries_count,
                   MIN(e.date) AS first_date,
                   MAX(e.date) AS last_date,
                   COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0) AS net
            FROM v2_flows f
            LEFT JOIN v2_entries e ON e.flow_id = f.id AND e.archived_at IS NULL
            WHERE f.workspace_id = ? AND f.type = 'cash'
            GROUP BY f.id
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Cash flow missing for {$workspaceId}");
        }
        $opening = (float)$row['opening_balance'];

        return [
            'flow_id' => (string)$row['id'],
            'opening_balance' => $opening,
            'entries_count' => (int)$row['entries_count'],
            'first_date' => $row['first_date'] === null ? null : (string)$row['first_date'],
            'last_date' => $row['last_date'] === null ? null : (string)$row['last_date'],
            'net' => (float)$row['net'],
            'cash_now' => $opening + (float)$row['net'],
        ];
    }

    private function cashBalanceBefore(string $workspaceId, string $date): float
    {
        $snapshot = $this->cashSnapshot($workspaceId);
        return $this->cashBalanceBeforeWithOpening((float)$snapshot['opening_balance'], $workspaceId, $date);
    }

    private function cashBalanceBeforeWithOpening(float $opening, string $workspaceId, string $date): float
    {
        return $opening + $this->cashNetBefore($workspaceId, $date);
    }

    private function cashNetBefore(string $workspaceId, string $date): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0)
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id AND f.type = 'cash'
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date < ?
        ");
        $stmt->execute([$workspaceId, $date]);

        return (float)$stmt->fetchColumn();
    }

    private function cashNetBetween(string $workspaceId, string $fromInclusive, string $toExclusive): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0)
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id AND f.type = 'cash'
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
        ");
        $stmt->execute([$workspaceId, $fromInclusive, $toExclusive]);

        return (float)$stmt->fetchColumn();
    }

    private function cashNet(array $entries): float
    {
        $net = 0.0;
        foreach ($entries as $entry) {
            if (($entry['flow_type'] ?? null) !== 'cash') {
                continue;
            }
            $net += (string)$entry['direction'] === 'in' ? (float)$entry['amount'] : -(float)$entry['amount'];
        }

        return $net;
    }

    private function firstTargetDateAfter(string $date): ?string
    {
        $stmt = $this->db->prepare("
            SELECT MIN(date)
            FROM v2_entries
            WHERE workspace_id = ?
              AND archived_at IS NULL
              AND date > ?
        ");
        $stmt->execute([$this->targetWorkspaceId, $date]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function existingSeamEntry(): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM v2_entries
            WHERE workspace_id = ?
              AND source_type = 'correction'
              AND raw_text LIKE '%стыковка входящего остатка с текущей лентой 2026%'
              AND archived_at IS NULL
        ");
        $stmt->execute([$this->targetWorkspaceId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function insertSeamEntry(float $delta, string $cashFlowId): void
    {
        $sign = $delta >= 0 ? '+' : '-';
        $amount = abs($delta);
        $direction = $delta >= 0 ? 'in' : 'out';
        $rawText = $sign . number_format($amount, 2, '.', '') . ' стыковка входящего остатка с текущей лентой 2026';
        $matchedRules = [[
            'source' => 'claudia_z_april_2025_feed_export',
            'kind' => 'visible_chain_seam',
            'purpose' => 'preserve existing 2026 operational balance after importing April-December 2025 history',
        ]];
        $this->db->prepare("
            INSERT INTO v2_entries (
                id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
            )
            VALUES (?, ?, ?, ?, NULL, '2025-12-31', ?, ?, ?, ?, 'correction', NULL, 'corrected', 'correction', NULL, NULL, ?, NULL, ?)
        ")->execute([
            FinDeskV2Support::uuid(),
            $this->targetWorkspaceId,
            $cashFlowId,
            $this->userId,
            $rawText,
            $sign,
            number_format($amount, 2, '.', ''),
            $direction,
            'Служебная стыковка: апрель-декабрь 2025 перенесены из архива, текущий остаток 2026 сохранен без изменения.',
            FinDeskV2Support::jsonEncode($matchedRules),
        ]);
    }

    private function writeBackup(): string
    {
        $dir = dirname(__DIR__) . '/storage/production-audits/claudia-z-april-2025-feed-export-' . date('Ymd-His');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create backup dir {$dir}");
        }
        $tables = ['v2_entries', 'v2_flows', 'v2_report_batches', 'v2_report_batch_entries'];
        $backup = [];
        foreach ($tables as $table) {
            if ($table === 'v2_report_batch_entries') {
                $stmt = $this->db->prepare("
                    SELECT rbe.*
                    FROM v2_report_batch_entries rbe
                    INNER JOIN v2_report_batches rb ON rb.id = rbe.batch_id
                    WHERE rb.workspace_id IN (?, ?)
                ");
            } else {
                $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE workspace_id IN (?, ?)");
            }
            $stmt->execute([$this->targetWorkspaceId, $this->archiveWorkspaceId]);
            $backup[$table] = $stmt->fetchAll();
        }
        $path = $dir . '/before.json';
        file_put_contents($path, FinDeskV2Support::jsonEncode($backup));

        return $path;
    }
}

$commit = in_array('--commit', $argv, true);
(new ExportClaudiaZApril2025Feed($commit))->run();
