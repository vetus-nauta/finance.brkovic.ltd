<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ClaudiaZOriginMergeApply
{
    private PDO $db;
    private FinDeskV2Repository $repo;
    private ReflectionMethod $recalculateFlowBalance;
    private string $workspaceId;
    private string $archiveWorkspaceId;
    private string $outputDir;
    private int $userId;

    public function __construct()
    {
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
        $this->workspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed');
        $this->archiveWorkspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_ARCHIVE_WORKSPACE_ID') ?: '3bb2f598-540e-4878-9d92-aad24a7d12ac');
        $this->outputDir = dirname(__DIR__) . '/storage/imports/claudia-z-origin-merge';
        $this->recalculateFlowBalance = new ReflectionMethod(FinDeskV2Repository::class, 'recalculateFlowBalance');
        $this->recalculateFlowBalance->setAccessible(true);
    }

    public function run(): void
    {
        $this->userId = $this->userId();
        $before = $this->workspaceSnapshot($this->workspaceId);
        $candidates = $this->candidates();
        $cashNet = $this->cashNet($candidates);
        $targetOpening = (float)$before['cash_flow']['opening_balance'] - $cashNet;
        $expectedCashNow = (float)$before['cash_now'];
        $backupPath = $this->writeArtifact('backup-before-commit', [
            'generated_at' => date(DATE_ATOM),
            'mode' => 'backup_before_commit',
            'current_before' => $before,
            'candidate_count' => count($candidates),
            'candidate_cash_net' => $cashNet,
            'target_current_cash_opening' => $targetOpening,
            'candidates' => array_map([$this, 'candidateBackupRow'], $candidates),
        ]);

        if (count($candidates) === 0) {
            echo "No origin merge candidates. Backup: {$backupPath}\n";
            return;
        }

        $created = [];
        $this->db->beginTransaction();
        try {
            $flows = $this->flowsByType($this->workspaceId);
            $cashFlow = $flows['cash'] ?? null;
            if (!is_array($cashFlow)) {
                throw new RuntimeException('Current cash flow not found.');
            }

            $this->db->prepare("UPDATE v2_flows SET opening_balance = ? WHERE id = ?")
                ->execute([number_format($targetOpening, 2, '.', ''), (string)$cashFlow['id']]);

            $insert = $this->db->prepare("
                INSERT INTO v2_entries (
                    id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                    entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
                )
                VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($candidates as $candidate) {
                $entry = $candidate['entry'];
                $flowType = (string)$entry['flow_type'];
                $flow = $flows[$flowType] ?? null;
                if (!is_array($flow)) {
                    throw new RuntimeException("Missing target flow: {$flowType}");
                }
                $id = FinDeskV2Support::uuid();
                $categoryCode = $entry['category_code'] === null ? null : (string)$entry['category_code'];
                $matchedRules = FinDeskV2Support::jsonDecode($entry['matched_rules_json'] ?? null, []);
                if (!is_array($matchedRules)) {
                    $matchedRules = [];
                }
                $matchedRules[] = [
                    'source' => 'claudia_z_origin_merge',
                    'archive_workspace_id' => $this->archiveWorkspaceId,
                    'archive_entry_id' => (string)$entry['id'],
                    'archive_created_seq' => (int)$entry['created_seq'],
                ];

                $insert->execute([
                    $id,
                    $this->workspaceId,
                    (string)$flow['id'],
                    $this->userId,
                    (string)$entry['date'],
                    (string)$entry['raw_text'],
                    (string)$entry['sign'],
                    number_format((float)$entry['amount'], 2, '.', ''),
                    (string)$entry['direction'],
                    (string)$entry['entry_type'],
                    $categoryCode === null ? null : $this->categoryIdByCode($categoryCode),
                    (string)$entry['status'],
                    'import',
                    $entry['source_id'] === null ? null : (string)$entry['source_id'],
                    $entry['source_row_id'] === null ? null : (string)$entry['source_row_id'],
                    $entry['notes'] === null ? null : (string)$entry['notes'],
                    $entry['confidence'] === null ? null : number_format((float)$entry['confidence'], 3, '.', ''),
                    FinDeskV2Support::jsonEncode($matchedRules),
                ]);

                $created[] = [
                    'entry_id' => $id,
                    'archive_entry_id' => (string)$entry['id'],
                    'date' => (string)$entry['date'],
                    'raw_text' => (string)$entry['raw_text'],
                    'amount' => (float)$entry['amount'],
                    'flow_type' => $flowType,
                    'category_code' => $categoryCode,
                ];
            }

            foreach ($flows as $flow) {
                $this->recalculateFlowBalance->invoke($this->repo, (string)$flow['id']);
            }

            $after = $this->workspaceSnapshot($this->workspaceId);
            if (abs((float)$after['cash_now'] - $expectedCashNow) > 0.001) {
                throw new RuntimeException('Cash truth changed: expected '
                    . number_format($expectedCashNow, 2, '.', '')
                    . ', got ' . number_format((float)$after['cash_now'], 2, '.', ''));
            }

            $this->audit($before, $after, $created, $backupPath);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $commitPath = $this->writeArtifact('commit', [
            'generated_at' => date(DATE_ATOM),
            'mode' => 'commit',
            'backup_path' => $backupPath,
            'created_count' => count($created),
            'candidate_cash_net' => $cashNet,
            'before' => $before,
            'after' => $this->workspaceSnapshot($this->workspaceId),
            'created_samples' => array_slice($created, 0, 80),
        ]);

        echo "Claudia Z origin merge committed\n";
        echo "Created: " . count($created) . "\n";
        echo "Cash opening: " . number_format((float)$before['cash_flow']['opening_balance'], 2, '.', '')
            . " -> " . number_format($targetOpening, 2, '.', '') . "\n";
        echo "Cash now preserved: " . number_format($expectedCashNow, 2, '.', '') . "\n";
        echo "Backup: {$backupPath}\n";
        echo "Commit artifact: {$commitPath}\n";
    }

    private function userId(): int
    {
        $email = (string)(getenv('FINDESK_V2_CLAUDIA_Z_OWNER_EMAIL') ?: 'vetus.nauta@gmail.com');
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException("Active user not found: {$email}");
        }

        return (int)$id;
    }

    /** @return array<int, array{entry: array<string, mixed>}> */
    private function candidates(): array
    {
        $currentKeys = $this->entryKeys($this->workspaceId);
        $result = [];
        foreach ($this->entries($this->archiveWorkspaceId) as $entry) {
            if (isset($currentKeys[$this->entryKey($entry)])) {
                continue;
            }
            $result[] = ['entry' => $entry];
        }

        return $result;
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

    /** @param array<int, array{entry: array<string, mixed>}> $candidates */
    private function cashNet(array $candidates): float
    {
        $net = 0.0;
        foreach ($candidates as $candidate) {
            $entry = $candidate['entry'];
            if ((string)$entry['flow_type'] !== 'cash') {
                continue;
            }
            $net += (string)$entry['sign'] === '+' ? (float)$entry['amount'] : -(float)$entry['amount'];
        }

        return $net;
    }

    /** @return array<string, array<string, mixed>> */
    private function flowsByType(string $workspaceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM v2_flows WHERE workspace_id = ? ORDER BY is_default DESC, created_at ASC");
        $stmt->execute([$workspaceId]);
        $flows = [];
        foreach ($stmt->fetchAll() as $flow) {
            $flows[(string)$flow['type']] = $flow;
        }

        return $flows;
    }

    private function categoryIdByCode(string $code): string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM v2_categories
            WHERE code = ? AND is_active = 1 AND (workspace_id IS NULL OR workspace_id = ?)
            ORDER BY workspace_id IS NULL ASC
            LIMIT 1
        ");
        $stmt->execute([$code, $this->workspaceId]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException("Unknown category in current workspace: {$code}");
        }

        return (string)$id;
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

    private function candidateBackupRow(array $candidate): array
    {
        $entry = $candidate['entry'];

        return [
            'archive_entry_id' => (string)$entry['id'],
            'archive_created_seq' => (int)$entry['created_seq'],
            'date' => (string)$entry['date'],
            'flow_type' => (string)$entry['flow_type'],
            'raw_text' => (string)$entry['raw_text'],
            'amount' => (float)$entry['amount'],
            'category_code' => $entry['category_code'] === null ? null : (string)$entry['category_code'],
            'status' => (string)$entry['status'],
            'source_id' => $entry['source_id'] === null ? null : (string)$entry['source_id'],
            'source_row_id' => $entry['source_row_id'] === null ? null : (string)$entry['source_row_id'],
        ];
    }

    private function audit(array $before, array $after, array $created, string $backupPath): void
    {
        $id = FinDeskV2Support::uuid();
        $this->db->prepare("
            INSERT INTO v2_audit_log (id, workspace_id, entity_type, entity_id, action, before_json, after_json, performed_by)
            VALUES (?, ?, 'workspace', ?, 'claudia_z_origin_merge', ?, ?, ?)
        ")->execute([
            $id,
            $this->workspaceId,
            $this->workspaceId,
            FinDeskV2Support::jsonEncode($before),
            FinDeskV2Support::jsonEncode([
                'after' => $after,
                'created_count' => count($created),
                'backup_path' => $backupPath,
            ]),
            $this->userId,
        ]);
    }

    private function writeArtifact(string $kind, array $payload): string
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException("Cannot create {$this->outputDir}");
        }
        $path = $this->outputDir . '/' . $kind . '-' . date('Ymd-His') . '.json';
        file_put_contents($path, FinDeskV2Support::jsonEncode($payload));

        return $path;
    }
}

(new ClaudiaZOriginMergeApply())->run();
