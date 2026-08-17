<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ClaudiaZHistorySplitDryRun
{
    private PDO $db;
    private FinDeskV2Repository $repo;
    private ReflectionMethod $parseLegacyImportRow;
    private ReflectionMethod $existingLegacyEntryKeys;

    private string $workspaceId;
    private string $archiveWorkspaceId;
    private string $boundaryDate;
    private string $outputDir;
    /** @var array<string, ?string> */
    private array $sourceReportDateCache = [];

    public function __construct()
    {
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
        $this->workspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed');
        $this->archiveWorkspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_ARCHIVE_WORKSPACE_ID') ?: '3bb2f598-540e-4878-9d92-aad24a7d12ac');
        $this->boundaryDate = (string)(getenv('FINDESK_V2_CLAUDIA_Z_HISTORY_BOUNDARY_DATE') ?: '2025-12-31');
        $this->outputDir = dirname(__DIR__) . '/storage/imports/claudia-z-history-split';

        $this->parseLegacyImportRow = new ReflectionMethod(FinDeskV2Repository::class, 'parseLegacyImportRow');
        $this->parseLegacyImportRow->setAccessible(true);
        $this->existingLegacyEntryKeys = new ReflectionMethod(FinDeskV2Repository::class, 'existingLegacyEntryKeys');
        $this->existingLegacyEntryKeys->setAccessible(true);
    }

    public function run(): void
    {
        $archiveSeen = $this->existingKeys($this->archiveWorkspaceId);
        $currentSeen = $this->existingKeys($this->workspaceId);
        $sources = $this->archiveSources();
        $report = [
            'generated_at' => date(DATE_ATOM),
            'mode' => 'dry_run_no_db_writes',
            'boundary' => [
                'rule' => 'source report date <= boundary goes to Archive Raw History; newer source report date goes before existing Claudia Z current feed',
                'boundary_date' => $this->boundaryDate,
                'last_history_report_date' => null,
                'first_current_candidate_report_date' => null,
            ],
            'workspaces' => [
                'current' => $this->workspaceSummary($this->workspaceId),
                'archive_history' => $this->workspaceSummary($this->archiveWorkspaceId),
            ],
            'existing_duplicate_keys' => [
                'current' => count($currentSeen),
                'archive_history' => count($archiveSeen),
            ],
            'destinations' => [
                'archive_history' => $this->emptyDestination(),
                'current_prepend' => $this->emptyDestination(),
                'manual_review' => $this->emptyDestination(),
            ],
            'sources' => [],
            'flags' => [],
        ];

        foreach ($sources as $source) {
            $reportDate = $this->sourceReportDate($source);
            $destination = $this->destinationFor($reportDate);
            if ($destination === 'archive_history') {
                $report['boundary']['last_history_report_date'] = max((string)($report['boundary']['last_history_report_date'] ?? ''), (string)$reportDate);
            } elseif ($destination === 'current_prepend' && $reportDate !== null) {
                $first = $report['boundary']['first_current_candidate_report_date'];
                $report['boundary']['first_current_candidate_report_date'] = $first === null ? $reportDate : min((string)$first, $reportDate);
            }

            $seen =& $archiveSeen;
            if ($destination === 'current_prepend') {
                $seen =& $currentSeen;
            }
            $sourceReport = $this->analyzeSource($source, $reportDate, $destination, $seen);
            unset($seen);

            $report['sources'][] = $sourceReport;
            $this->accumulateDestination($report['destinations'][$destination], $sourceReport);
        }

        $report['flags'] = $this->flags($report);
        $path = $this->writeArtifact($report);
        $this->printSummary($report, $path);
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

    private function sourceReportDate(array $source): ?string
    {
        $sourceId = (string)($source['id'] ?? '');
        if ($sourceId !== '' && array_key_exists($sourceId, $this->sourceReportDateCache)) {
            return $this->sourceReportDateCache[$sourceId];
        }

        $fileName = (string)($source['file_name'] ?? '');
        $date = $this->filenameDate($fileName);
        if (is_string($date) && $date !== '') {
            if ($sourceId !== '') {
                $this->sourceReportDateCache[$sourceId] = $date;
            }
            return $date;
        }

        $date = $this->sourceFilenameDateContext($sourceId);
        if ($sourceId !== '') {
            $this->sourceReportDateCache[$sourceId] = $date;
        }

        return $date;
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

    private function destinationFor(?string $reportDate): string
    {
        if ($reportDate === null) {
            return 'manual_review';
        }

        return $reportDate <= $this->boundaryDate ? 'archive_history' : 'current_prepend';
    }

    /** @param array<string, bool> $seen */
    private function analyzeSource(array $source, ?string $reportDate, string $destination, array &$seen): array
    {
        $rows = $this->sourceRows((string)$source['id']);
        $summary = [
            'source_id' => (string)$source['id'],
            'file_name' => (string)($source['file_name'] ?? ''),
            'file_url' => $source['file_url'] === null ? null : (string)$source['file_url'],
            'report_date' => $reportDate,
            'destination' => $destination,
            'rows_total' => count($rows),
            'candidate_entries' => 0,
            'net_candidate_entries' => 0,
            'duplicates' => 0,
            'already_linked' => 0,
            'ignored' => 0,
            'summary_ignored' => 0,
            'unrecognized' => 0,
            'invalid_date_candidates' => 0,
            'opening_rows' => [],
            'date_range' => ['first' => null, 'last' => null],
            'totals' => $this->emptyTotals(),
            'net_totals' => $this->emptyTotals(),
            'duplicate_totals' => $this->emptyTotals(),
            'categories' => [],
            'samples' => [],
            'risks' => [],
        ];

        foreach ($rows as $row) {
            $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
            if (!is_array($raw)) {
                $raw = [];
            }

            $args = [$raw, $row, &$seen];
            /** @var array<string, mixed> $parsed */
            $parsed = $this->parseLegacyImportRow->invokeArgs($this->repo, $args);
            $status = (string)($parsed['parse_status'] ?? 'unrecognized');
            $entry = is_array($parsed['entry'] ?? null) ? $parsed['entry'] : null;
            $alreadyLinked = $row['entry_id'] !== null;

            if ($this->isOpeningRaw($raw)) {
                $summary['opening_rows'][] = $this->openingRow($raw, $row);
            }

            if ($status === 'summary_ignored') {
                $summary['summary_ignored']++;
            } elseif ($status === 'ignored') {
                $summary['ignored']++;
            } elseif ($status === 'unrecognized') {
                $summary['unrecognized']++;
            }

            if (!empty($parsed['duplicate_suspect'])) {
                $summary['duplicates']++;
            }
            if ($alreadyLinked) {
                $summary['already_linked']++;
            }

            if ($entry === null) {
                $this->maybeSample($summary['samples'], $row, $raw, $status, null);
                continue;
            }

            $date = (string)$entry['date'];
            if (!$this->dateInAccountingRange($date)) {
                $summary['invalid_date_candidates']++;
                $this->maybeSample($summary['samples'], $row, $raw, 'invalid_date_candidate', $entry);
                continue;
            }

            $summary['candidate_entries']++;
            $summary['date_range']['first'] = $summary['date_range']['first'] === null ? $date : min((string)$summary['date_range']['first'], $date);
            $summary['date_range']['last'] = $summary['date_range']['last'] === null ? $date : max((string)$summary['date_range']['last'], $date);
            $kind = (string)$entry['flow_type'] . '_' . (((string)$entry['raw_text'])[0] === '-' ? 'expense' : 'income');
            if (isset($summary['totals'][$kind])) {
                $summary['totals'][$kind] += (float)$entry['amount'];
                if (!empty($parsed['duplicate_suspect']) || $alreadyLinked) {
                    $summary['duplicate_totals'][$kind] += (float)$entry['amount'];
                } else {
                    $summary['net_totals'][$kind] += (float)$entry['amount'];
                }
            }
            if (empty($parsed['duplicate_suspect']) && !$alreadyLinked) {
                $summary['net_candidate_entries']++;
            }
            $category = (string)($entry['category_code'] ?? 'no_category');
            $summary['categories'][$category] = ($summary['categories'][$category] ?? 0) + 1;
            $this->maybeSample($summary['samples'], $row, $raw, $status, $entry);
        }

        ksort($summary['categories']);
        $this->addSourceRisks($summary);

        return $summary;
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

    private function isOpeningRaw(array $raw): bool
    {
        $description = mb_strtolower(trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? '')));
        return str_contains($description, 'остаток') || str_contains($description, 'переход');
    }

    private function openingRow(array $raw, array $row): array
    {
        return [
            'source_row_id' => (string)$row['id'],
            'sheet_name' => $row['sheet_name'] === null ? null : (string)$row['sheet_name'],
            'row_number' => $row['row_number'] === null ? null : (int)$row['row_number'],
            'description' => trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? '')),
            'date_context' => $raw['_date_context'] ?? null,
            'amounts' => [
                'cash_income' => $this->amount($raw['приход кеш'] ?? $raw['приход кэш'] ?? $raw['cash income'] ?? $raw['приход'] ?? null),
                'cash_expense' => $this->amount($raw['расход кеш'] ?? $raw['расход кэш'] ?? $raw['cash expense'] ?? $raw['расход'] ?? null),
                'card_income' => $this->amount($raw['приход карта'] ?? $raw['приход карты'] ?? $raw['card income'] ?? null),
                'card_expense' => $this->amount($raw['расход карта'] ?? $raw['расход карты'] ?? $raw['card expense'] ?? null),
            ],
        ];
    }

    private function amount($value): ?float
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $normalized = str_replace([' ', "\xc2\xa0"], '', $text);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? abs((float)$normalized) : null;
    }

    private function maybeSample(array &$samples, array $row, array $raw, string $status, ?array $entry): void
    {
        if (count($samples) >= 8 && $status !== 'unrecognized') {
            return;
        }
        if (count($samples) >= 16) {
            return;
        }
        if ($entry === null && !in_array($status, ['unrecognized', 'duplicate_suspect'], true)) {
            return;
        }

        $samples[] = [
            'source_row_id' => (string)$row['id'],
            'sheet_name' => $row['sheet_name'] === null ? null : (string)$row['sheet_name'],
            'row_number' => $row['row_number'] === null ? null : (int)$row['row_number'],
            'status' => $status,
            'description' => trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? '')),
            'entry_preview' => $entry,
        ];
    }

    private function addSourceRisks(array &$summary): void
    {
        if ($summary['report_date'] === null) {
            $summary['risks'][] = 'no_report_date_from_filename';
        }
        if ($summary['candidate_entries'] === 0 && $summary['rows_total'] > 0) {
            $summary['risks'][] = 'no_candidate_entries';
        }
        if ($summary['duplicates'] > 0) {
            $summary['risks'][] = 'has_duplicate_suspects';
        }
        if ($summary['invalid_date_candidates'] > 0) {
            $summary['risks'][] = 'has_invalid_date_candidates';
        }
        $first = $summary['date_range']['first'];
        $last = $summary['date_range']['last'];
        if (is_string($summary['report_date']) && is_string($first) && abs($this->monthsBetween($summary['report_date'], $first)) > 4) {
            $summary['risks'][] = 'entry_dates_far_before_report_date';
        }
        if (is_string($summary['report_date']) && is_string($last) && $last > date('Y-m-d', strtotime($summary['report_date'] . ' +45 days'))) {
            $summary['risks'][] = 'entry_dates_after_report_window';
        }
    }

    private function dateInAccountingRange(string $date): bool
    {
        return $date >= '2022-01-01' && $date <= date('Y-m-d', strtotime('+1 year'));
    }

    private function monthsBetween(string $a, string $b): int
    {
        $left = new DateTimeImmutable(substr($a, 0, 7) . '-01');
        $right = new DateTimeImmutable(substr($b, 0, 7) . '-01');

        return ((int)$left->format('Y') - (int)$right->format('Y')) * 12 + ((int)$left->format('n') - (int)$right->format('n'));
    }

    private function accumulateDestination(array &$destination, array $source): void
    {
        $destination['sources_count']++;
        $destination['rows_total'] += (int)$source['rows_total'];
        $destination['candidate_entries'] += (int)$source['candidate_entries'];
        $destination['net_candidate_entries'] += (int)$source['net_candidate_entries'];
        $destination['duplicates'] += (int)$source['duplicates'];
        $destination['already_linked'] += (int)$source['already_linked'];
        $destination['ignored'] += (int)$source['ignored'];
        $destination['summary_ignored'] += (int)$source['summary_ignored'];
        $destination['unrecognized'] += (int)$source['unrecognized'];
        $destination['invalid_date_candidates'] += (int)$source['invalid_date_candidates'];
        foreach ($source['totals'] as $key => $value) {
            $destination['totals'][$key] += (float)$value;
        }
        foreach ($source['net_totals'] as $key => $value) {
            $destination['net_totals'][$key] += (float)$value;
        }
        foreach ($source['duplicate_totals'] as $key => $value) {
            $destination['duplicate_totals'][$key] += (float)$value;
        }
        if ($source['date_range']['first'] !== null) {
            $destination['date_range']['first'] = $destination['date_range']['first'] === null
                ? $source['date_range']['first']
                : min((string)$destination['date_range']['first'], (string)$source['date_range']['first']);
            $destination['date_range']['last'] = $destination['date_range']['last'] === null
                ? $source['date_range']['last']
                : max((string)$destination['date_range']['last'], (string)$source['date_range']['last']);
        }
        $destination['sources'][] = [
            'file_name' => $source['file_name'],
            'report_date' => $source['report_date'],
            'candidate_entries' => $source['candidate_entries'],
            'net_candidate_entries' => $source['net_candidate_entries'],
            'duplicates' => $source['duplicates'],
            'already_linked' => $source['already_linked'],
            'invalid_date_candidates' => $source['invalid_date_candidates'],
            'date_range' => $source['date_range'],
            'totals' => $source['totals'],
            'net_totals' => $source['net_totals'],
            'duplicate_totals' => $source['duplicate_totals'],
            'risks' => $source['risks'],
            'first_opening_row' => $source['opening_rows'][0] ?? null,
        ];
    }

    private function emptyDestination(): array
    {
        return [
            'sources_count' => 0,
            'rows_total' => 0,
            'candidate_entries' => 0,
            'net_candidate_entries' => 0,
            'duplicates' => 0,
            'already_linked' => 0,
            'ignored' => 0,
            'summary_ignored' => 0,
            'unrecognized' => 0,
            'invalid_date_candidates' => 0,
            'date_range' => ['first' => null, 'last' => null],
            'totals' => $this->emptyTotals(),
            'net_totals' => $this->emptyTotals(),
            'duplicate_totals' => $this->emptyTotals(),
            'sources' => [],
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'cash_income' => 0.0,
            'cash_expense' => 0.0,
            'card_income' => 0.0,
            'card_expense' => 0.0,
        ];
    }

    private function workspaceSummary(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.id, w.name, w.type, COUNT(e.id) AS entries_count,
                   MIN(e.date) AS first_entry_date, MAX(e.date) AS last_entry_date,
                   MIN(e.created_seq) AS first_seq, MAX(e.created_seq) AS last_seq
            FROM v2_workspaces w
            LEFT JOIN v2_entries e ON e.workspace_id = w.id AND e.archived_at IS NULL
            WHERE w.id = ?
            GROUP BY w.id
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Workspace not found: {$workspaceId}");
        }

        return [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'entries_count' => (int)$row['entries_count'],
            'first_entry_date' => $row['first_entry_date'] === null ? null : (string)$row['first_entry_date'],
            'last_entry_date' => $row['last_entry_date'] === null ? null : (string)$row['last_entry_date'],
            'first_seq' => $row['first_seq'] === null ? null : (int)$row['first_seq'],
            'last_seq' => $row['last_seq'] === null ? null : (int)$row['last_seq'],
        ];
    }

    private function flags(array $report): array
    {
        $flags = [];
        $current = $report['destinations']['current_prepend'];
        if ($current['candidate_entries'] === 0) {
            $flags[] = 'No current-prepend candidates found after boundary.';
        }
        if ($current['duplicates'] > 0) {
            $flags[] = 'Current-prepend contains duplicate suspects against existing Claudia Z or within selected raw rows.';
        }
        foreach ($report['destinations'] as $name => $destination) {
            if ($destination['unrecognized'] > 0) {
                $flags[] = "{$name} has unrecognized rows; these need manual review before commit.";
            }
            if ($destination['invalid_date_candidates'] > 0) {
                $flags[] = "{$name} has invalid Excel date candidates; these are excluded from the dry-run totals.";
            }
        }

        return $flags;
    }

    private function writeArtifact(array $report): string
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException("Cannot create output dir: {$this->outputDir}");
        }
        $path = $this->outputDir . '/dry-run-' . date('Ymd-His') . '.json';
        file_put_contents($path, FinDeskV2Support::jsonEncode($report));

        return $path;
    }

    private function printSummary(array $report, string $path): void
    {
        echo "Claudia Z history split dry-run\n";
        echo "Artifact: {$path}\n";
        echo "Boundary date: {$report['boundary']['boundary_date']}\n";
        foreach ($report['destinations'] as $name => $destination) {
            echo "\n{$name}\n";
            echo "  sources: {$destination['sources_count']}\n";
            echo "  rows: {$destination['rows_total']}\n";
            echo "  candidates: {$destination['candidate_entries']}\n";
            echo "  net candidates: {$destination['net_candidate_entries']}\n";
            echo "  duplicates: {$destination['duplicates']}\n";
            echo "  already linked: {$destination['already_linked']}\n";
            echo "  unrecognized: {$destination['unrecognized']}\n";
            echo "  invalid dates excluded: {$destination['invalid_date_candidates']}\n";
            echo "  date range: " . ($destination['date_range']['first'] ?? '-') . " .. " . ($destination['date_range']['last'] ?? '-') . "\n";
            echo "  cash income/expense: " . number_format((float)$destination['totals']['cash_income'], 2, '.', '') . " / " . number_format((float)$destination['totals']['cash_expense'], 2, '.', '') . "\n";
            echo "  card income/expense: " . number_format((float)$destination['totals']['card_income'], 2, '.', '') . " / " . number_format((float)$destination['totals']['card_expense'], 2, '.', '') . "\n";
            echo "  net cash income/expense: " . number_format((float)$destination['net_totals']['cash_income'], 2, '.', '') . " / " . number_format((float)$destination['net_totals']['cash_expense'], 2, '.', '') . "\n";
        }
        if ($report['flags'] !== []) {
            echo "\nFlags:\n";
            foreach ($report['flags'] as $flag) {
                echo "  - {$flag}\n";
            }
        }
    }
}

(new ClaudiaZHistorySplitDryRun())->run();
