<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ClaudiaZHistoryChainApply
{
    private PDO $db;
    private FinDeskV2Repository $repo;
    private ReflectionMethod $parseLegacyImportRow;
    private ReflectionMethod $existingLegacyEntryKeys;
    private ReflectionMethod $createEntryInCurrentTransaction;
    private ReflectionMethod $recalculateFlowBalance;

    private string $workspaceId;
    private string $archiveWorkspaceId;
    private string $boundaryDate;
    private bool $commit;
    private int $userId;
    /** @var array<string, ?string> */
    private array $sourceReportDateCache = [];

    public function __construct(bool $commit)
    {
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
        $this->workspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed');
        $this->archiveWorkspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_ARCHIVE_WORKSPACE_ID') ?: '3bb2f598-540e-4878-9d92-aad24a7d12ac');
        $this->boundaryDate = (string)(getenv('FINDESK_V2_CLAUDIA_Z_HISTORY_BOUNDARY_DATE') ?: '2025-12-31');
        $this->commit = $commit;

        $this->parseLegacyImportRow = new ReflectionMethod(FinDeskV2Repository::class, 'parseLegacyImportRow');
        $this->parseLegacyImportRow->setAccessible(true);
        $this->existingLegacyEntryKeys = new ReflectionMethod(FinDeskV2Repository::class, 'existingLegacyEntryKeys');
        $this->existingLegacyEntryKeys->setAccessible(true);
        $this->createEntryInCurrentTransaction = new ReflectionMethod(FinDeskV2Repository::class, 'createEntryInCurrentTransaction');
        $this->createEntryInCurrentTransaction->setAccessible(true);
        $this->recalculateFlowBalance = new ReflectionMethod(FinDeskV2Repository::class, 'recalculateFlowBalance');
        $this->recalculateFlowBalance->setAccessible(true);
    }

    public function run(): void
    {
        $this->userId = $this->userId();
        $before = $this->workspaceFinancialSnapshot($this->workspaceId);
        $archiveBefore = $this->workspaceFinancialSnapshot($this->archiveWorkspaceId);
        $candidates = $this->collectCandidates();
        $currentNet = $this->cashNet($candidates['current_prepend']);
        $oldOpening = $before['cash_flow']['opening_balance'];
        $targetOpening = $oldOpening - $currentNet;
        $expectedCurrentCashAfter = $before['cash_now'];

        $preview = [
            'generated_at' => date(DATE_ATOM),
            'mode' => $this->commit ? 'commit' : 'preview_no_db_writes',
            'boundary_date' => $this->boundaryDate,
            'current_workspace_id' => $this->workspaceId,
            'archive_workspace_id' => $this->archiveWorkspaceId,
            'before' => [
                'current' => $before,
                'archive_history' => $archiveBefore,
            ],
            'plan' => [
                'archive_history_entries' => count($candidates['archive_history']),
                'current_prepend_entries' => count($candidates['current_prepend']),
                'current_prepend_cash_net' => $currentNet,
                'old_current_cash_opening' => $oldOpening,
                'target_current_cash_opening' => $targetOpening,
                'expected_current_cash_after' => $expectedCurrentCashAfter,
            ],
            'commit' => null,
        ];

        if (!$this->commit) {
            $path = $this->writeArtifact('preview', $preview);
            $this->printSummary($preview, $path);
            return;
        }

        $created = [
            'archive_history' => [],
            'current_prepend' => [],
        ];
        $this->db->beginTransaction();
        try {
            $flows = [
                'archive_history' => $this->flowsByType($this->archiveWorkspaceId),
                'current_prepend' => $this->flowsByType($this->workspaceId),
            ];
            $currentCashFlowId = $flows['current_prepend']['cash']['id'] ?? null;
            if (!is_string($currentCashFlowId)) {
                throw new RuntimeException('Current cash flow not found.');
            }

            $this->db->prepare("UPDATE v2_flows SET opening_balance = ? WHERE id = ?")
                ->execute([number_format($targetOpening, 2, '.', ''), $currentCashFlowId]);

            foreach (['archive_history', 'current_prepend'] as $destination) {
                $targetWorkspaceId = $destination === 'archive_history' ? $this->archiveWorkspaceId : $this->workspaceId;
                foreach ($candidates[$destination] as $candidate) {
                    $flowType = (string)$candidate['entry']['flow_type'];
                    $flow = $flows[$destination][$flowType] ?? null;
                    if (!is_array($flow)) {
                        throw new RuntimeException("Missing {$flowType} flow for {$destination}.");
                    }
                    $entry = $this->createEntryInCurrentTransaction->invoke($this->repo, $targetWorkspaceId, [
                        'flow_id' => $flow['id'],
                        'date' => $candidate['entry']['date'],
                        'raw_text' => $candidate['entry']['raw_text'],
                        'amount' => number_format((float)$candidate['entry']['amount'], 2, '.', ''),
                        'category_code' => $candidate['entry']['category_code'],
                        'status' => 'imported',
                        'source_type' => 'import',
                        'source_id' => $candidate['source_id'],
                        'source_row_id' => $candidate['source_row_id'],
                        'closed_month_decision' => 'recalculate_chain',
                        'matched_rules' => [[
                            'source' => 'claudia_z_history_chain_split',
                            'destination' => $destination,
                            'file_name' => $candidate['file_name'],
                            'sheet_name' => $candidate['sheet_name'],
                            'row_number' => $candidate['row_number'],
                            'boundary_date' => $this->boundaryDate,
                        ]],
                    ], $this->userId);

                    $this->db->prepare("
                        UPDATE v2_import_rows
                        SET entry_id = ?, parse_status = 'imported', parse_notes = ?
                        WHERE id = ? AND entry_id IS NULL
                    ")->execute([
                        $entry['id'],
                        'claudia_z_history_chain_split:' . $destination,
                        $candidate['source_row_id'],
                    ]);
                    $created[$destination][] = [
                        'entry_id' => (string)$entry['id'],
                        'source_row_id' => $candidate['source_row_id'],
                        'date' => $candidate['entry']['date'],
                        'raw_text' => $candidate['entry']['raw_text'],
                    ];
                }
            }

            foreach ([$currentCashFlowId, $flows['archive_history']['cash']['id'] ?? null] as $flowId) {
                if (is_string($flowId)) {
                    $this->recalculateFlowBalance->invoke($this->repo, $flowId);
                }
            }

            $after = $this->workspaceFinancialSnapshot($this->workspaceId);
            if (abs($after['cash_now'] - $expectedCurrentCashAfter) > 0.001) {
                throw new RuntimeException('Current cash truth would change: expected '
                    . number_format($expectedCurrentCashAfter, 2, '.', '')
                    . ', got ' . number_format($after['cash_now'], 2, '.', ''));
            }

            $preview['commit'] = [
                'created' => [
                    'archive_history' => count($created['archive_history']),
                    'current_prepend' => count($created['current_prepend']),
                ],
                'after' => [
                    'current' => $after,
                    'archive_history' => $this->workspaceFinancialSnapshot($this->archiveWorkspaceId),
                ],
                'created_samples' => [
                    'archive_history' => array_slice($created['archive_history'], 0, 8),
                    'current_prepend' => array_slice($created['current_prepend'], 0, 24),
                ],
            ];

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $path = $this->writeArtifact('commit', $preview);
        $this->printSummary($preview, $path);
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

    /** @return array{archive_history: array<int, array<string, mixed>>, current_prepend: array<int, array<string, mixed>>} */
    private function collectCandidates(): array
    {
        $seen = [
            'archive_history' => $this->existingKeys($this->archiveWorkspaceId),
            'current_prepend' => $this->existingKeys($this->workspaceId),
        ];
        $result = [
            'archive_history' => [],
            'current_prepend' => [],
        ];

        foreach ($this->archiveSources() as $source) {
            $reportDate = $this->sourceReportDate($source);
            if ($reportDate === null) {
                continue;
            }
            $destination = $reportDate <= $this->boundaryDate ? 'archive_history' : 'current_prepend';
            foreach ($this->sourceRows((string)$source['id']) as $row) {
                if ($row['entry_id'] !== null) {
                    continue;
                }
                $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
                if (!is_array($raw)) {
                    continue;
                }
                $args = [$raw, $row, &$seen[$destination]];
                /** @var array<string, mixed> $parsed */
                $parsed = $this->parseLegacyImportRow->invokeArgs($this->repo, $args);
                $entry = is_array($parsed['entry'] ?? null) ? $parsed['entry'] : null;
                if ($entry === null || !empty($parsed['duplicate_suspect'])) {
                    continue;
                }
                if (!$this->dateInAccountingRange((string)$entry['date'])) {
                    continue;
                }
                $result[$destination][] = [
                    'source_id' => (string)$source['id'],
                    'source_row_id' => (string)$row['id'],
                    'file_name' => (string)($source['file_name'] ?? ''),
                    'sheet_name' => $row['sheet_name'] === null ? null : (string)$row['sheet_name'],
                    'row_number' => $row['row_number'] === null ? null : (int)$row['row_number'],
                    'entry' => $entry,
                ];
            }
        }

        return $result;
    }

    /** @return array<string, bool> */
    private function existingKeys(string $workspaceId): array
    {
        /** @var array<string, bool> $keys */
        $keys = $this->existingLegacyEntryKeys->invoke($this->repo, $workspaceId);
        return $keys;
    }

    /** @return array<int, array<string, mixed>> */
    private function archiveSources(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, file_name, file_url, status, include_decision, reason, created_at
            FROM v2_import_sources
            WHERE workspace_id = ?
              AND include_decision = 'included'
            ORDER BY COALESCE(file_name, '') ASC, created_at ASC
        ");
        $stmt->execute([$this->archiveWorkspaceId]);
        $sources = $stmt->fetchAll();
        usort($sources, function (array $a, array $b): int {
            return [$this->sourceReportDate($a) ?? '9999-99-99', (string)($a['file_name'] ?? '')]
                <=> [$this->sourceReportDate($b) ?? '9999-99-99', (string)($b['file_name'] ?? '')];
        });

        return $sources;
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceRows(string $sourceId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, import_source_id, sheet_name, `row_number`, raw_json, entry_id, parse_status, parse_notes
            FROM v2_import_rows
            WHERE import_source_id = ?
            ORDER BY sheet_name ASC, `row_number` ASC, id ASC
        ");
        $stmt->execute([$sourceId]);

        return $stmt->fetchAll();
    }

    private function sourceReportDate(array $source): ?string
    {
        $sourceId = (string)($source['id'] ?? '');
        if ($sourceId !== '' && array_key_exists($sourceId, $this->sourceReportDateCache)) {
            return $this->sourceReportDateCache[$sourceId];
        }

        $date = $this->filenameDate((string)($source['file_name'] ?? '')) ?? $this->sourceFilenameDateContext($sourceId);
        if ($sourceId !== '') {
            $this->sourceReportDateCache[$sourceId] = $date;
        }

        return $date;
    }

    private function sourceFilenameDateContext(string $sourceId): ?string
    {
        if ($sourceId === '') {
            return null;
        }
        $stmt = $this->db->prepare("
            SELECT raw_json
            FROM v2_import_rows
            WHERE import_source_id = ?
            ORDER BY `row_number` ASC, id ASC
            LIMIT 10
        ");
        $stmt->execute([$sourceId]);
        foreach ($stmt->fetchAll() as $row) {
            $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
            if (!is_array($raw)) {
                continue;
            }
            $date = $raw['_date_context']['filename_date'] ?? null;
            if (is_string($date) && preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $date) === 1) {
                return $date;
            }
        }

        return null;
    }

    private function filenameDate(string $fileName): ?string
    {
        if (preg_match('/(?:^|[^0-9])(20[0-9]{2})[-_. ]?([01]?[0-9])[-_. ]?([0-3]?[0-9])(?:[^0-9]|$)/', $fileName, $match) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$match[1]}-{$match[2]}-{$match[3]}");
            return $date ? $date->format('Y-m-d') : null;
        }
        if (preg_match('/(?:^|[^0-9])([0-3]?[0-9])[-_. ]([01]?[0-9])[-_. ](20[0-9]{2}|[0-9]{2})(?:[^0-9]|$)/', $fileName, $match) !== 1) {
            return null;
        }
        $year = (int)$match[3];
        if ($year < 100) {
            $year += 2000;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-{$match[2]}-{$match[1]}");

        return $date ? $date->format('Y-m-d') : null;
    }

    private function dateInAccountingRange(string $date): bool
    {
        return $date >= '2022-01-01' && $date <= date('Y-m-d', strtotime('+1 year'));
    }

    /** @param array<int, array<string, mixed>> $candidates */
    private function cashNet(array $candidates): float
    {
        $net = 0.0;
        foreach ($candidates as $candidate) {
            $entry = $candidate['entry'];
            if (($entry['flow_type'] ?? null) !== 'cash') {
                continue;
            }
            $sign = ((string)$entry['raw_text'])[0] ?? '';
            $net += $sign === '+' ? (float)$entry['amount'] : -(float)$entry['amount'];
        }

        return $net;
    }

    /** @return array<string, array<string, mixed>> */
    private function flowsByType(string $workspaceId): array
    {
        $flows = [];
        foreach ($this->repo->listFlows($workspaceId, $this->userId) as $flow) {
            $flows[(string)$flow['type']] = $flow;
        }

        return $flows;
    }

    private function workspaceFinancialSnapshot(string $workspaceId): array
    {
        $flows = $this->flowsByType($workspaceId);
        $cashFlow = $flows['cash'] ?? null;
        if (!is_array($cashFlow)) {
            throw new RuntimeException("Cash flow not found for {$workspaceId}");
        }
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS entries_count,
                   MIN(date) AS first_date,
                   MAX(date) AS last_date,
                   COALESCE(SUM(CASE WHEN direction = 'in' THEN amount WHEN direction = 'out' THEN -amount ELSE 0 END), 0) AS net
            FROM v2_entries
            WHERE workspace_id = ?
              AND archived_at IS NULL
        ");
        $stmt->execute([$workspaceId]);
        $all = $stmt->fetch() ?: [];

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
            'entries_count' => (int)($all['entries_count'] ?? 0),
            'first_date' => $all['first_date'] === null ? null : (string)$all['first_date'],
            'last_date' => $all['last_date'] === null ? null : (string)$all['last_date'],
            'net' => (float)($all['net'] ?? 0),
            'cash_flow' => [
                'id' => (string)$cashFlow['id'],
                'opening_balance' => $opening,
                'net' => $cashNet,
            ],
            'cash_now' => $opening + $cashNet,
        ];
    }

    private function writeArtifact(string $kind, array $payload): string
    {
        $dir = dirname(__DIR__) . '/storage/imports/claudia-z-history-split';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}");
        }
        $path = $dir . '/apply-' . $kind . '-' . date('Ymd-His') . '.json';
        file_put_contents($path, FinDeskV2Support::jsonEncode($payload));

        return $path;
    }

    private function printSummary(array $payload, string $path): void
    {
        echo "Claudia Z history chain apply\n";
        echo "Mode: {$payload['mode']}\n";
        echo "Artifact: {$path}\n";
        echo "Archive entries: {$payload['plan']['archive_history_entries']}\n";
        echo "Current prepend entries: {$payload['plan']['current_prepend_entries']}\n";
        echo "Current prepend cash net: " . number_format((float)$payload['plan']['current_prepend_cash_net'], 2, '.', '') . "\n";
        echo "Opening cash: " . number_format((float)$payload['plan']['old_current_cash_opening'], 2, '.', '')
            . " -> " . number_format((float)$payload['plan']['target_current_cash_opening'], 2, '.', '') . "\n";
        echo "Expected current cash after: " . number_format((float)$payload['plan']['expected_current_cash_after'], 2, '.', '') . "\n";
        if (is_array($payload['commit'] ?? null)) {
            echo "Created archive/current: {$payload['commit']['created']['archive_history']} / {$payload['commit']['created']['current_prepend']}\n";
            echo "Actual current cash after: " . number_format((float)$payload['commit']['after']['current']['cash_now'], 2, '.', '') . "\n";
        }
    }
}

$commit = in_array('--commit', $argv, true);
(new ClaudiaZHistoryChainApply($commit))->run();
