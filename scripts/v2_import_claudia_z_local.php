<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class ClaudiaZLocalImporter
{
    private string $sourceRoot;
    private string $stagingRoot;
    private PDO $db;
    private FinDeskV2Repository $repo;
    private int $userId;
    private string $workspaceId;
    private string $workspaceName = 'Claudia Z';
    private bool $rawOnly = false;
    /** @var array<string, bool> */
    private array $onlyFileNames = [];
    /** @var array<string, string> */
    private array $blockedFileNames = [];
    private ?string $openingCash = null;
    private string $mode = 'full';
    /** @var array<string, array<string, mixed>> */
    private array $flowsByType = [];
    /** @var array<string, bool> */
    private array $existingKeys = [];
    /** @var array<string, int> */
    private array $stats = [
        'files_seen' => 0,
        'files_included' => 0,
        'files_excluded' => 0,
        'files_review' => 0,
        'rows_scanned' => 0,
        'rows_created' => 0,
        'rows_duplicate' => 0,
        'rows_ignored' => 0,
        'rows_unrecognized' => 0,
        'rows_other_review' => 0,
    ];

    public function __construct(string $sourceRoot, string $stagingRoot, private readonly bool $reset = false, string $mode = 'full')
    {
        $this->sourceRoot = rtrim($sourceRoot, '/');
        $this->stagingRoot = rtrim($stagingRoot, '/');
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
        $this->configureMode($mode);
    }

    private function configureMode(string $mode): void
    {
        $mode = mb_strtolower(trim($mode));
        if (!in_array($mode, ['full', 'current', 'archive'], true)) {
            throw new RuntimeException("Unsupported mode: {$mode}");
        }

        $this->mode = $mode;
        if ($mode === 'current') {
            $this->workspaceName = 'Claudia Z';
            $this->openingCash = '2870.00';
            $this->onlyFileNames = array_fill_keys([
                '14.05.26+сервис.xlsx',
                '06.06.xlsm',
                '15.06.2026.xlsx',
            ], true);
            return;
        }

        if ($mode === 'archive') {
            $this->workspaceName = 'Claudia Z Archive Raw History';
            $this->rawOnly = true;
            $this->openingCash = '0.00';
            $this->blockedFileNames = [
                '19.02.24.xlsx' => 'google drive mount blocks while reading this file; resync/re-export before archive import',
                '01.09.25.xlsx' => 'google drive mount blocks while reading this file; resync/re-export before archive import',
            ];
            return;
        }

        $this->openingCash = '0.00';
    }

    public function run(): void
    {
        if (!is_dir($this->sourceRoot)) {
            throw new RuntimeException("Source folder not found: {$this->sourceRoot}");
        }
        if (!is_dir($this->stagingRoot) && !mkdir($this->stagingRoot, 0775, true) && !is_dir($this->stagingRoot)) {
            throw new RuntimeException("Cannot create staging folder: {$this->stagingRoot}");
        }

        $this->userId = $this->localUserId();
        $this->workspaceId = $this->workspaceId();
        if ($this->reset) {
            $this->resetWorkspaceData();
        }
        $this->applyOpeningBalances();
        $this->flowsByType = $this->flowsByType();
        $this->existingKeys = $this->existingEntryKeys();

        $inventory = $this->inventory();
        $manifest = [];
        foreach ($inventory as $file) {
            $this->stats['files_seen']++;
            $manifest[] = [
                'path' => $file['path'],
                'file_name' => $file['name'],
                'report_date' => $file['report_date'],
                'decision' => $file['decision'],
                'reason' => $file['reason'],
            ];
        }

        file_put_contents(
            $this->stagingRoot . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if ($this->onlyFileNames !== []) {
            $inventory = $this->filterCurrentOperationalFiles($inventory);
            $this->recountInventoryStats($inventory);
            $manifest = [];
            foreach ($inventory as $file) {
                $manifest[] = [
                    'path' => $file['path'],
                    'file_name' => $file['name'],
                    'report_date' => $file['report_date'],
                    'decision' => $file['decision'],
                    'reason' => $file['reason'],
                ];
            }
            file_put_contents(
                $this->stagingRoot . '/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        $included = array_values(array_filter($inventory, static fn (array $file): bool => $file['decision'] === 'include'));
        usort($included, static fn (array $a, array $b): int => strcmp((string)$a['report_date'], (string)$b['report_date']));

        $this->deleteEmptyInterruptedSources();
        foreach ($included as $file) {
            $this->importFile($file);
        }

        if ($this->mode === 'current') {
            $this->writeCurrentChainArtifact();
        }
        $this->writeReport($manifest);
        $this->validateModeGate($included);
        $this->printSummary();
    }

    private function localUserId(): int
    {
        $ownerEmail = mb_strtolower(trim((string)(getenv('FINDESK_V2_CLAUDIA_Z_OWNER_EMAIL') ?: 'vetus.nauta@gmail.com')));
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$ownerEmail]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid Claudia Z owner email.');
        }
        $this->db->prepare("
            INSERT INTO users (email, display_name, preferred_language, timezone, status)
            VALUES (?, 'Vetus Nauta', 'ru', 'Europe/Podgorica', 'active')
        ")->execute([$ownerEmail]);

        return (int)$this->db->lastInsertId();
    }

    private function workspaceId(): string
    {
        $stmt = $this->db->prepare("
            SELECT w.id
            FROM v2_workspaces w
            INNER JOIN v2_workspace_members m ON m.workspace_id = w.id
            WHERE w.name = ?
              AND w.archived_at IS NULL
              AND m.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$this->workspaceName, $this->userId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (string)$id;
        }

        $workspace = $this->repo->createWorkspace([
            'name' => $this->workspaceName,
            'type' => 'yacht',
            'currency' => 'EUR',
            'locale' => 'ru',
            'opening_cash' => $this->openingCash ?? '0',
        ], $this->userId);

        return (string)$workspace['id'];
    }

    /** @return array<string, array<string, mixed>> */
    private function flowsByType(): array
    {
        $flows = [];
        foreach ($this->repo->listFlows($this->workspaceId, $this->userId) as $flow) {
            $flows[(string)$flow['type']] = $flow;
        }

        return $flows;
    }

    /** @return array<string, bool> */
    private function existingEntryKeys(): array
    {
        $stmt = $this->db->prepare("
            SELECT e.date, f.type AS flow_type, e.sign, e.amount, e.raw_text
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            WHERE e.workspace_id = ? AND e.archived_at IS NULL
        ");
        $stmt->execute([$this->workspaceId]);
        $keys = [];
        foreach ($stmt->fetchAll() as $row) {
            $description = preg_replace('/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?\s*/u', '', (string)$row['raw_text']);
            $keys[$this->entryKey(
                (string)$row['date'],
                (string)$row['flow_type'],
                (string)$row['sign'],
                (float)$row['amount'],
                (string)$description
            )] = true;
        }

        return $keys;
    }

    /** @return array<int, array<string, mixed>> */
    private function inventory(): array
    {
        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sourceRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }
            $ext = mb_strtolower($item->getExtension());
            if (!in_array($ext, ['xls', 'xlsx', 'xlsm'], true)) {
                continue;
            }
            $paths[] = $item->getPathname();
        }
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        $candidates = [];
        foreach ($paths as $path) {
            $name = basename($path);
            $mtime = filemtime($path) ?: (int)$item->getMTime();
            $date = $this->filenameDate($name, $mtime);
            $exclude = $this->excludeReason($name);
            $titleQuality = $this->titleQuality($name);
            $decision = 'review';
            $reason = $titleQuality['reason'];
            if ($exclude !== null) {
                $decision = 'exclude';
                $reason = $exclude;
            } elseif ($date !== null && $titleQuality['accepted']) {
                $decision = 'candidate';
            }

            $candidates[] = [
                'path' => $path,
                'name' => $name,
                'ext' => mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                'mtime' => $mtime,
                'report_date' => $date,
                'priority' => $this->titlePriority($name),
                'decision' => $decision,
                'reason' => $reason,
            ];
        }

        $bestByDate = [];
        foreach ($candidates as $index => $file) {
            if ($file['decision'] !== 'candidate' || $file['report_date'] === null) {
                continue;
            }
            $key = (string)$file['report_date'];
            $current = $bestByDate[$key] ?? null;
            if ($current === null || $this->isBetterCandidate($file, $candidates[$current])) {
                $bestByDate[$key] = $index;
            }
        }

        $inventory = [];
        foreach ($candidates as $index => $file) {
            if ($file['decision'] === 'candidate') {
                if (($bestByDate[(string)$file['report_date']] ?? null) === $index) {
                    $file['decision'] = 'include';
                    $file['reason'] = 'selected clean report for date';
                } else {
                    $file['decision'] = 'exclude';
                    $file['reason'] = 'duplicate report date; cleaner/later candidate selected';
                }
            }
            if ($file['decision'] === 'include') {
                $this->stats['files_included']++;
            } elseif ($file['decision'] === 'exclude') {
                $this->stats['files_excluded']++;
            } else {
                $this->stats['files_review']++;
            }
            $inventory[] = $file;
        }

        return $inventory;
    }

    private function filterCurrentOperationalFiles(array $inventory): array
    {
        foreach ($inventory as &$file) {
            if (isset($this->onlyFileNames[(string)$file['name']])) {
                $file['decision'] = 'include';
                $file['reason'] = 'current operational balance chain';
            } else {
                $file['decision'] = 'exclude';
                $file['reason'] = 'not part of current operational balance chain';
            }
        }
        unset($file);

        return $inventory;
    }

    private function recountInventoryStats(array $inventory): void
    {
        $this->stats['files_seen'] = count($inventory);
        $this->stats['files_included'] = 0;
        $this->stats['files_excluded'] = 0;
        $this->stats['files_review'] = 0;
        foreach ($inventory as $file) {
            if ($file['decision'] === 'include') {
                $this->stats['files_included']++;
            } elseif ($file['decision'] === 'review') {
                $this->stats['files_review']++;
            } else {
                $this->stats['files_excluded']++;
            }
        }
    }

    private function isBetterCandidate(array $candidate, array $current): bool
    {
        if ((int)$candidate['priority'] !== (int)$current['priority']) {
            return (int)$candidate['priority'] > (int)$current['priority'];
        }
        if ($candidate['ext'] !== $current['ext']) {
            return $candidate['ext'] === 'xlsx';
        }

        return (int)$candidate['mtime'] > (int)$current['mtime'];
    }

    /** @return array{accepted: bool, reason: string} */
    private function titleQuality(string $fileName): array
    {
        $stem = preg_replace('/\.(xlsx|xlsm|xls)$/iu', '', $fileName) ?? $fileName;
        $text = mb_strtolower($stem);
        if (preg_match('/^[0-3]?[0-9][._-][01]?[0-9](?:[._-](?:[0-9]{2}|20[0-9]{2}))?$/u', $stem) === 1) {
            return ['accepted' => true, 'reason' => 'date-only title'];
        }
        if (preg_match('/^[0-3]?[0-9][._-][01]?[0-9][._-](?:[0-9]{2}|20[0-9]{2})\\+сервис$/u', $text) === 1) {
            return ['accepted' => true, 'reason' => 'service final title marker'];
        }
        if (str_contains($text, 'финальный')) {
            return ['accepted' => true, 'reason' => 'final title marker'];
        }

        return ['accepted' => false, 'reason' => 'not clean date-only/final title'];
    }

    private function titlePriority(string $fileName): int
    {
        $text = mb_strtolower($fileName);
        if (str_contains($text, 'финальный')) {
            return 30;
        }
        if (str_contains($text, '+сервис')) {
            return 28;
        }
        if (str_ends_with($text, '.xlsx')) {
            return 20;
        }
        if (str_ends_with($text, '.xls')) {
            return 10;
        }

        return 0;
    }

    private function excludeReason(string $fileName): ?string
    {
        if (isset($this->blockedFileNames[$fileName])) {
            return $this->blockedFileNames[$fileName];
        }

        $text = mb_strtolower($fileName);
        foreach (['не отправлял', 'не отправлено', 'не готово', 'не закончен', 'не закончено', 'не полный', 'неполный', 'черновик', 'draft', 'test'] as $marker) {
            if (str_contains($text, $marker)) {
                return "title marker: {$marker}";
            }
        }

        return null;
    }

    private function importFile(array $file): void
    {
        $xlsxPath = $this->xlsxPath($file['path']);
        $sourceId = FinDeskV2Support::uuid();
        $this->db->prepare("
            INSERT INTO v2_import_sources (
                id, workspace_id, source_type, file_name, file_url, file_id, status, include_decision, reason
            )
            VALUES (?, ?, 'excel', ?, ?, NULL, 'accepted', 'included', ?)
        ")->execute([
            $sourceId,
            $this->workspaceId,
            $file['name'],
            $file['path'],
            'local Claudia Z accounting import',
        ]);

        $sheets = (new FinDeskV2LegacyExcelImporter())->read((string)file_get_contents($xlsxPath));
        $sourceKeys = [];
        foreach ($sheets as $sheet) {
            $headers = [];
            $lastDate = null;
            foreach ($sheet['rows'] as $rowNumber => $cells) {
                if ($headers === []) {
                    $candidateHeaders = $this->headerMap($cells);
                    if (!$this->looksLikeHeader($candidateHeaders)) {
                        continue;
                    }
                    $headers = $candidateHeaders;
                    continue;
                }

                $raw = $this->rawRow($headers, $cells);
                if ($raw === []) {
                    continue;
                }

                $raw['_date_context'] = [
                    'inherited_previous_row_date' => $lastDate,
                    'filename_date' => $file['report_date'],
                    'file_updated_date' => date('Y-m-d', (int)$file['mtime']),
                ];
                $date = $this->rowDate($raw);
                if ($date !== null && ($raw['дата'] ?? '') !== '') {
                    $lastDate = $date;
                }
                $raw['_date_context']['inherited_previous_row_date'] = $lastDate;

                $rowId = FinDeskV2Support::uuid();
                $parsed = $this->parseRow($raw);
                $status = $parsed['status'];
                $entryId = null;

                if ($parsed['entry'] !== null && !$this->rawOnly) {
                    $entry = $parsed['entry'];
                    $key = $this->entryKey($entry['date'], $entry['flow_type'], $entry['sign'], $entry['amount'], $entry['description']);
                    if (isset($this->existingKeys[$key])) {
                        $status = 'duplicate_suspect';
                        $this->stats['rows_duplicate']++;
                    } else {
                        $created = $this->repo->createEntry($this->workspaceId, [
                            'flow_id' => $this->flowsByType[$entry['flow_type']]['id'],
                            'date' => $entry['date'],
                            'raw_text' => $entry['raw_text'],
                            'amount' => number_format($entry['amount'], 2, '.', ''),
                            'category_code' => $entry['category_code'],
                            'status' => $entry['status'],
                            'source_type' => 'import',
                            'source_id' => $sourceId,
                            'source_row_id' => $rowId,
                            'matched_rules' => $entry['matched_rules'],
                        ], $this->userId);
                        $entryId = (string)$created['id'];
                        $sourceKeys[$key] = true;
                        $this->stats['rows_created']++;
                        if ($entry['status'] === 'other_review') {
                            $this->stats['rows_other_review']++;
                        }
                    }
                } elseif ($parsed['entry'] !== null && $this->rawOnly) {
                    $status = 'parsed_raw';
                    if (($parsed['entry']['status'] ?? null) === 'other_review') {
                        $this->stats['rows_other_review']++;
                    }
                } elseif ($status === 'unrecognized') {
                    $this->stats['rows_unrecognized']++;
                } else {
                    $this->stats['rows_ignored']++;
                }

                $this->stats['rows_scanned']++;
                $this->db->prepare("
                    INSERT INTO v2_import_rows (id, import_source_id, sheet_name, `row_number`, raw_json, entry_id, parse_status, parse_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $rowId,
                    $sourceId,
                    $sheet['name'],
                    (int)$rowNumber,
                    FinDeskV2Support::jsonEncode($raw),
                    $entryId,
                    $status,
                    $parsed['notes'],
                ]);
            }
        }
        foreach ($sourceKeys as $key => $_) {
            $this->existingKeys[$key] = true;
        }
    }

    private function xlsxPath(string $path): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($path)) ?: basename($path);
        $baseDir = $this->stagingRoot . '/converted/' . sha1($path);
        $sourceDir = $baseDir . '/source';
        $targetDir = $baseDir . '/normalized';
        foreach ([$sourceDir, $targetDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Cannot create conversion folder: {$dir}");
            }
        }
        $copied = $sourceDir . '/' . $safeName;
        if (!is_file($copied) || filesize($copied) !== filesize($path)) {
            copy($path, $copied);
        }

        $expected = $targetDir . '/' . preg_replace('/\.(xls|xlsm)$/iu', '.xlsx', basename($copied));
        if (is_file($expected) && filemtime($expected) >= filemtime($copied)) {
            return $expected;
        }

        $command = [
            'libreoffice',
            '--headless',
            '--convert-to',
            'xlsx',
            '--outdir',
            $targetDir,
            $copied,
        ];
        $output = [];
        $code = 0;
        exec(implode(' ', array_map('escapeshellarg', $command)) . ' 2>&1', $output, $code);
        if ($code !== 0 || !is_file($expected)) {
            throw new RuntimeException("LibreOffice conversion failed for {$path}: " . implode("\n", $output));
        }

        return $expected;
    }

    private function deleteEmptyInterruptedSources(): void
    {
        $this->db->prepare("
            DELETE s
            FROM v2_import_sources s
            LEFT JOIN v2_import_rows r ON r.import_source_id = s.id
            WHERE s.workspace_id = ?
              AND s.reason = 'local Claudia Z accounting import'
              AND r.id IS NULL
        ")->execute([$this->workspaceId]);
    }

    private function applyOpeningBalances(): void
    {
        if ($this->openingCash === null) {
            return;
        }

        $this->db->prepare("
            UPDATE v2_flows
            SET opening_balance = CASE WHEN type = 'cash' THEN ? ELSE '0.00' END
            WHERE workspace_id = ?
        ")->execute([$this->openingCash, $this->workspaceId]);
    }

    /** @param array<int, string> $cells */
    private function headerMap(array $cells): array
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

    private function looksLikeHeader(array $headers): bool
    {
        $values = array_fill_keys(array_values($headers), true);
        $hasDescription = isset($values['описание платежа'])
            || isset($values['description'])
            || isset($values['описание']);
        $hasOldMoneyColumn = isset($values['приход кеш'])
            || isset($values['приход кэш'])
            || isset($values['расход кеш'])
            || isset($values['расход кэш'])
            || isset($values['приход карта'])
            || isset($values['приход карты'])
            || isset($values['расход карта'])
            || isset($values['расход карты']);
        $hasChronologyMoneyColumn = isset($values['приход']) || isset($values['расход']);

        return $hasDescription && ($hasOldMoneyColumn || $hasChronologyMoneyColumn);
    }

    private function rawRow(array $headers, array $cells): array
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

    private function parseRow(array $raw): array
    {
        $description = trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? ''));
        $text = mb_strtolower($description);
        $rowNumberCell = trim((string)($raw['№'] ?? $raw['#'] ?? ''));
        if ($rowNumberCell !== '' && preg_match('/^[0-9]+$/u', $rowNumberCell) !== 1) {
            return ['status' => 'ignored', 'entry' => null, 'notes' => 'non-operation footer/block row ignored'];
        }

        $date = $this->rowDate($raw);
        $amounts = [
            'cash_income' => $this->amount($raw['приход кеш'] ?? $raw['приход кэш'] ?? $raw['cash income'] ?? $raw['приход'] ?? null),
            'cash_expense' => $this->amount($raw['расход кеш'] ?? $raw['расход кэш'] ?? $raw['cash expense'] ?? $raw['расход'] ?? null),
            'card_income' => $this->amount($raw['приход карта'] ?? $raw['приход карты'] ?? $raw['card income'] ?? null),
            'card_expense' => $this->amount($raw['расход карта'] ?? $raw['расход карты'] ?? $raw['card expense'] ?? null),
        ];
        $nonZero = array_filter($amounts, static fn (?float $amount): bool => $amount !== null && abs($amount) > 0.0001);
        $isSummary = str_contains($text, 'свод')
            || str_contains($text, 'общий приход')
            || str_contains($text, 'общий расход')
            || str_contains($text, 'итоговый остаток')
            || str_contains($text, 'добавлено в этот пересчет')
            || str_contains($text, 'summary')
            || ($description === '' && $rowNumberCell === '' && isset($raw['сводные данные']));
        $isInfo = str_contains($text, 'информационная') || str_contains($text, 'не считается') || str_contains($text, 'comment') || str_contains($text, 'info');
        $isOpening = str_contains($text, 'начальный остаток')
            || str_contains($text, 'переходящий остаток')
            || str_contains($text, 'opening balance')
            || str_contains($text, 'balance brought forward')
            || ($nonZero === [] && (str_contains($text, 'остаток') || str_contains($text, 'переход')));

        if ($isSummary) {
            return ['status' => 'summary_ignored', 'entry' => null, 'notes' => 'summary row ignored'];
        }
        if ($isInfo || $isOpening) {
            return ['status' => 'ignored', 'entry' => null, 'notes' => $isOpening ? 'opening/carry row ignored' : 'info row ignored'];
        }
        if ($date === null || $nonZero === []) {
            return ['status' => 'unrecognized', 'entry' => null, 'notes' => 'missing date or amount'];
        }
        if (count($nonZero) > 1) {
            return ['status' => 'unrecognized', 'entry' => null, 'notes' => 'multiple money columns in one row'];
        }

        $kind = (string)array_key_first($nonZero);
        $amount = (float)$nonZero[$kind];
        $flowType = str_starts_with($kind, 'card_') ? 'card' : 'cash';
        $sign = str_ends_with($kind, '_expense') ? '-' : '+';
        $category = $this->classify($description, $flowType, $sign);
        $status = $category['category_code'] === 'other' ? 'other_review' : 'imported';
        $rawText = $sign . number_format($amount, 2, '.', '') . ($description === '' ? ' imported row' : ' ' . $description);

        return [
            'status' => 'parsed',
            'entry' => [
                'date' => $date,
                'flow_type' => $flowType,
                'sign' => $sign,
                'raw_text' => $rawText,
                'description' => $description,
                'amount' => $amount,
                'category_code' => $category['category_code'],
                'status' => $status,
                'matched_rules' => $category['matched_rules'],
            ],
            'notes' => null,
        ];
    }

    /** @return array{category_code: ?string, matched_rules: array<int, array<string, string>>} */
    private function classify(string $description, string $flowType, string $sign): array
    {
        $text = mb_strtolower($description);
        if (preg_match('/цоги\s*мар|цогимар|cogimar/u', $text) === 1 && $sign === '-') {
            return [
                'category_code' => 'other',
                'matched_rules' => [[
                    'source' => 'claudia_z_local_dictionary',
                    'pattern' => 'cogimar_review',
                    'category_code' => 'other',
                ]],
            ];
        }
        if (preg_match('/планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u', $text) === 1 && $sign === '-') {
            return [
                'category_code' => 'other',
                'matched_rules' => [[
                    'source' => 'claudia_z_local_dictionary',
                    'pattern' => 'unsortable_other_review',
                    'category_code' => 'other',
                ]],
            ];
        }
        $rules = [
            'cash_topup_from_card' => '/снял кеш|снял с карты|снятие с карты|банкомат|atm|cash withdrawal|card to cash/u',
            'commercial_income' => '/чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u',
            'dry_dock' => '/сухой док|антифоулинг|подъем|подъём|подьем|спуск|haul.?out|launch/u',
            'fuel' => '/заправ|топлив|дизел|бензин|fuel/u',
            'tender' => '/тузик|тендер|dinghy|tender|seabob|сибоб|сапы?|sup/u',
            'guest_trip_support' => '/айфон|iphone|самокат|скутер|параплан|музыкант|прогулк[а-я]* гост|нац парк|вход в музей|снаст|зарядк[а-я]* шефу|маски$|маски ласты|подводн[а-я]* маск|перья на сап|весло сап|набор для ныряния|отел[ьяеи]?|гостиниц/u',
            'guest_cash_issued' => '/^(?:[+-]?\s*\d+(?:[.,]\d+)?\s+)?(?:лв|леонид владимирович)$|расходы лв|общая потраченная сумма лв|игра лв|(?:передал|отдал|дал|выдал)\s+(?:лв|леонид владимирович|арику?|саше?|гост)/u',
            'media_comms' => '/netflix|нетфликс|apple|ivi|иви|старлинк|starlink|hipo|сим.?карт|интернет|инет|интенрнет|связ|картина.?тв|\bтв\b|телевиз|sonos|сонос|модем|роуминг|сайт[а-я]* клауди|домен|хостинг|платн[а-я]* погод|прогноз погод|прогнох погод|обновлен[а-я]* карт|hdmi|шнур[а-я]* телефон|чехол телефон/u',
            'admin_legal' => '/тур.?регистрац|тамож|дьюти|документ|печат[ьи]|налог|ндс|страхов|регистрац|виньет|лиценз|леценз|sanada|такса|такс[аы] банк перевод|траст компани|внж|крулист|crew.?list|виза|судебн[а-я]* перевод|открытие счета|обеспечение счета|берегов[а-я]* служб|морск[а-я]* сертиф[а-я]*|сертифиткат|разрешен[а-я]* на вход|флаг[а-я]* итали|флаг[а-я]* кайман|границ|просрочк[а-я]* нахождения/u',
            'berth' => '/стоянк|зимовк|склад|гараж|вода электричество|электричеств|муринг/u',
            'marina_ports' => '/марин|порт|паром|выход в море|переход коринф|проход через коринф|tepai|такс[аы] по вход/u',
            'crew' => '/зп|зарплат|капитан|хостесс|помощник|экипаж|работник в помощь|сотруднику|докеры|sailor|вова|волод|евгени|наталь|повар|чаев/u',
            'service_water' => '/сервис|обслуж|мастер|ремонт|репарац|механик|токарь|опреснител|спас.?плот|пересертифик|дайвер|водолаз|электрик|откачка серых вод|откачка черн[а-я]* танк|черн[а-я]* танк|откачк[а-я]* вод|откачк[а-я]* грязн[а-я]* вод|выкачк[а-я]* танк|замен|монтаж|варк|консервац|тест систем|огнетуш|(?:^|\s)то(?:\s|$)/u',
            'tech_parts' => '/аккумулятор|аккум|кабел|насос|мотор|детал|запчаст|инструмент|клей|навигац|шлиф|машинк|пылесос|шланг|сантехник|расходник|расходники|крюк|переходник|генератор|батаре[яи]|батарейк|безопа[сст]+ност[а-я]* плаван|материал[а-я]* по тику|пропитк[аеи]? тик[а]?|расходники? по тику|расходники? тик|расодники? тик|для тика|тик.?клинер|тик.?силер|тик.?вандер|силер для платформы|средств[ао] для тика|очистител[ья]* тика|пятновыводител[ья]* тик|дезинфектор тик|обработк[а-я]* тика|щетк[а-я]*.*тик|тик.*щетк|трюмн|помп|подрульк|пордрульк|лебедк|смазк[а-я]* для лебед|компрессор|диммер|гелькоут|кранц|кранец|швартов|веревк|регулятор давления|контрольк|конде[яй]?|подгонк[а-я]*.*контрол[её]к.*кондиц|блок управления туалет|петл[яи].*(?:холодильн|хододильн)|амортизатор[а-я]*.*люк|люк[иа].*танк|датчик.*танк|ролик[а-я]* цепи|маркер[а-я]* цепи|подстаканник|экран на флай|кругов[а-я]* огонь|фонар[а-я]* на корм|плоттер|навионикс|навион|удлинитель|хомут|адаптер|болт|крепеж|крепеж[а-я]* гайк|втулк[а-я]* под стапел|строительн[а-я]* фен|мультиметр|предохранитель|сикафлекс|sikaflex|шарнир[а-я]*|шуруп[а-я]*|чертеж[а-я]* для 3д/u',
            'cleaning' => '/хим|мойк|моющ[а-я]* средств[а-я]*|салф|тряпк|пена|полиров|clean|прачк|прачеч|полирол|пенообразователь|керхер|мусор|вывоз мусора|отбеливател|плесен|грибк|распылител|щетк[а-я]*(?: для лодк)?/u',
            'interior' => '/ковр|обувь|судоч|нож|посуд|игрушк|кухонн[^,.;]*принадлежн|кухонн[а-я]* расход|инвентарь по кухне|кухн[а-я]*.*интерьер|кухн[а-я]*.*обновлен|утварь.*кухн|перешив.*подуш|подушк|чехл|скатерт|нарды|шезлонг|кофе[\\s-]?машин|кофемашин|блендер|соковыжималк|микроволновк|печка|капучинатор|графин|пепельниц|жалюзи|одеял|наволочк|плед|комплект постельн|мешк[иа]|контейнер|замк[иа] на дверц|на кухню/u',
            'current_boat_expenses' => '/брендир|(?:^|[\s-])форм[а-я]*|одежд[аы]? экипаж|спец.?одеж|спецодеж|агент|магазин|хоз.?товар|принтер|(?:^|\s)инвентарь(?!\s+по\s+кухне)(?:\s|$)|банковск[а-я]* перевод|комисси[яи] банк|банковск[а-я]* комисс|банковск[а-я]* процент[а-я]*.*перевод|забрал свои|bank fee|bank commission/u',
            'transport_expenses' => '/такси|трансфер|аренда авто|арендованн[а-я]* авто|рентакар|билеты?|перел[её]т|авиа|поезд|автобус|самол[её]т|air serbia|логистик|забрал гостей|дорожн[а-я]* расход|запра[вк][а-я]* авто|парковк|курьер|доставк|почт[а-я]* в сербию|велосипед[а-я]* млет|перевозк[а-я]* гидроцикл|taxi|transfer|car rental|tickets|delivery/u',
            'representation_expenses' => '/представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u',
            'provisions' => '/продукт|продуукт|рыб|стейк|мяс|баранин|хлеб|фрукт|овощ|напит|вино|пиво|кола|сок|сироп|сладост|коктел|коктейл|устриц|скамп|шкамп|краб|кальмар|лангустин|осминог|лосось|тунец|салмон|сыр|морож|инжир|яйц|орех|мед|соус|острог|перекус|цветы|алкоголь|виски|водк|шампан|грей.?гус|моет|moet|вдова клико|аберлоу|ликер|кофе(?![\\s-]?машин)|холодн[а-я]* чай|рынок|клубник|монтефиш|обед|кафе|докупк[а-я]* необходим[а-я]* в поход|закупк[а-я]* в поход|косметик|гигиен|шампун|аптечк|аптек|лекарств|(?:^|\s)вода(?!\s+электричеств)(?:\s|$)|вода (?:на|в) лодк/u',
        ];

        foreach ($rules as $code => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                if ($code === 'fuel' && preg_match('/авто|машин|car/u', $text) === 1) {
                    continue;
                }
                if ($sign === '+' && !in_array($code, ['commercial_income', 'cash_topup_from_card'], true)) {
                    continue;
                }

                return [
                    'category_code' => $code,
                    'matched_rules' => [[
                        'source' => 'claudia_z_local_dictionary',
                        'pattern' => $pattern,
                        'category_code' => $code,
                    ]],
                ];
            }
        }

        if (
            $flowType === 'cash'
            && $sign === '+'
            && preg_match('/агентск|brokerage|agency fee|commission/u', $text) !== 1
        ) {
            return [
                'category_code' => 'non_commercial_income',
                'matched_rules' => [[
                    'source' => 'claudia_z_local_dictionary',
                    'pattern' => 'non_commercial_income',
                    'category_code' => 'non_commercial_income',
                ]],
            ];
        }

        if ($flowType === 'cash' && $sign === '-') {
            return [
                'category_code' => 'other',
                'matched_rules' => [[
                    'source' => 'claudia_z_local_dictionary',
                    'pattern' => 'fallback_expense_review',
                    'category_code' => 'other',
                ]],
            ];
        }

        return ['category_code' => null, 'matched_rules' => []];
    }

    private function rowDate(array $raw): ?string
    {
        $contextYear = null;
        foreach ([
            $raw['_date_context']['filename_date'] ?? null,
            $raw['_date_context']['file_updated_date'] ?? null,
        ] as $contextDate) {
            $normalized = $this->normalizeDate($contextDate);
            if ($normalized !== null) {
                $contextYear = (int)substr($normalized, 0, 4);
                break;
            }
        }

        $rowDate = $this->normalizeDate($raw['дата'] ?? $raw['date'] ?? null, $contextYear);
        if ($rowDate !== null) {
            return $rowDate;
        }

        foreach ([
            $raw['_date_context']['inherited_previous_row_date'] ?? null,
            $raw['_date_context']['filename_date'] ?? null,
            $raw['_date_context']['file_updated_date'] ?? null,
        ] as $value) {
            $date = $this->normalizeDate($value);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function filenameDate(string $fileName, ?int $mtime = null): ?string
    {
        if (preg_match('/(?:^|[^0-9])(20[0-9]{2})[-_. ]?([01]?[0-9])[-_. ]?([0-3]?[0-9])(?:[^0-9]|$)/', $fileName, $match) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$match[1]}-{$match[2]}-{$match[3]}");
            return $date ? $date->format('Y-m-d') : null;
        }

        if (preg_match('/(?:^|[^0-9])([0-3]?[0-9])[-_. ]([01]?[0-9])[-_. ](20[0-9]{2}|[0-9]{2})(?:[^0-9]|$)/', $fileName, $match) !== 1) {
            if (preg_match('/^([0-3]?[0-9])[-_. ]([01]?[0-9])(?:\\.[^.]+)?$/', $fileName, $shortMatch) !== 1 || $mtime === null) {
                return null;
            }

            $year = (int)date('Y', $mtime);
            $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-{$shortMatch[2]}-{$shortMatch[1]}");
            return $date ? $date->format('Y-m-d') : null;
        }

        $year = (int)$match[3];
        if ($year < 100) {
            $year += 2000;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-{$match[2]}-{$match[1]}");

        return $date ? $date->format('Y-m-d') : null;
    }

    private function normalizeDate($value, ?int $contextYear = null): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[0-9]+$/', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', '1899-12-30')
                ->modify('+' . (int)$value . ' days')
                ->format('Y-m-d');
            return $this->dateInAccountingRange($date) ? $date : null;
        }
        foreach (['!Y-m-d', '!d.m.Y', '!d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date) {
                $normalized = $date->format('Y-m-d');
                return $this->dateInAccountingRange($normalized) ? $normalized : null;
            }
        }
        if ($contextYear !== null && preg_match('/^([0-3]?[0-9])[.\\/-]([01]?[0-9])$/', $value, $match) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$contextYear}-{$match[2]}-{$match[1]}");
            if ($date) {
                $normalized = $date->format('Y-m-d');
                return $this->dateInAccountingRange($normalized) ? $normalized : null;
            }
        }

        return null;
    }

    private function dateInAccountingRange(string $date): bool
    {
        return $date >= '2022-01-01' && $date <= date('Y-m-d', strtotime('+1 year'));
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

    private function entryKey(string $date, string $flowType, string $sign, float $amount, string $description): string
    {
        $normalizedDescription = preg_replace('/\s+/u', ' ', mb_strtolower(trim($description))) ?? '';
        return implode('|', [$date, $flowType, $sign, number_format($amount, 2, '.', ''), $normalizedDescription]);
    }

    private function writeReport(array $manifest): void
    {
        $ambiguous = array_values(array_filter($manifest, static fn (array $row): bool => $row['decision'] === 'review'));
        $report = [
            'generated_at' => date(DATE_ATOM),
            'mode' => $this->mode,
            'source_root' => $this->sourceRoot,
            'workspace_id' => $this->workspaceId,
            'workspace_name' => $this->workspaceName,
            'user_id' => $this->userId,
            'opening_cash' => $this->openingCash,
            'stats' => $this->stats,
            'balances' => $this->flowBalances(),
            'ambiguous_files' => $ambiguous,
        ];
        file_put_contents(
            $this->stagingRoot . '/import-report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /** @return array<string, array{name: string, opening_balance: float, net: float, balance: float}> */
    private function flowBalances(): array
    {
        $stmt = $this->db->prepare("
            SELECT f.name, f.type, f.opening_balance,
                   COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0) AS net
            FROM v2_flows f
            LEFT JOIN v2_entries e ON e.flow_id = f.id AND e.archived_at IS NULL
            WHERE f.workspace_id = ?
            GROUP BY f.id
            ORDER BY FIELD(f.type, 'cash', 'card', 'assistant_journal'), f.name
        ");
        $stmt->execute([$this->workspaceId]);

        $balances = [];
        foreach ($stmt->fetchAll() as $row) {
            $opening = (float)$row['opening_balance'];
            $net = (float)$row['net'];
            $balances[(string)$row['type']] = [
                'name' => (string)$row['name'],
                'opening_balance' => $opening,
                'net' => $net,
                'balance' => round($opening + $net, 2),
            ];
        }

        return $balances;
    }

    private function printSummary(): void
    {
        echo "Claudia Z local import complete\n";
        echo "workspace_id={$this->workspaceId}\n";
        echo json_encode($this->stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        foreach ($this->flowBalances() as $type => $row) {
            echo "{$type} {$row['name']} balance=" . number_format((float)$row['balance'], 2, '.', '') . "\n";
        }
        echo "manifest={$this->stagingRoot}/manifest.json\n";
        echo "report={$this->stagingRoot}/import-report.json\n";
    }

    private function writeCurrentChainArtifact(): void
    {
        $chain = [
            'generated_at' => date(DATE_ATOM),
            'workspace_name' => $this->workspaceName,
            'workspace_id' => $this->workspaceId,
            'purpose' => 'Claudia Z current operational balance chain',
            'opening_cash' => 2870.00,
            'expected_final_cash_balance' => 15262.00,
            'rules' => [
                'apply_only_these_files_in_order',
                'treat opening_cash as flow opening balance, not an operational entry',
                'do not merge this chain with raw archive entries for live balance',
            ],
            'files' => [
                [
                    'file_name' => '14.05.26+сервис.xlsx',
                    'report_date' => '2026-05-14',
                    'opening_cash' => 2870.00,
                    'income' => 50520.00,
                    'expense' => 39676.00,
                    'net' => 10844.00,
                    'closing_cash' => 13714.00,
                ],
                [
                    'file_name' => '06.06.xlsm',
                    'report_date' => '2026-06-06',
                    'opening_cash' => 13714.00,
                    'income' => 5000.00,
                    'expense' => 14509.00,
                    'net' => -9509.00,
                    'closing_cash' => 4205.00,
                ],
                [
                    'file_name' => '15.06.2026.xlsx',
                    'report_date' => '2026-06-15',
                    'opening_cash' => 4205.00,
                    'source_income_including_opening' => 28605.00,
                    'operational_income' => 24400.00,
                    'expense' => 13343.00,
                    'operational_net' => 11057.00,
                    'closing_cash' => 15262.00,
                    'note' => 'Source report income includes 4205 opening/carry. Do not import that opening row as operational income.',
                ],
            ],
        ];

        $path = $this->stagingRoot . '/current-operational-chain.json';
        file_put_contents($path, json_encode($chain, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<int, array<string, mixed>> $included */
    private function validateModeGate(array $included): void
    {
        if ($this->mode === 'current') {
            $this->validateCurrentOperationalGate($included);
            return;
        }

        if ($this->mode === 'archive') {
            $this->validateArchiveRawGate();
        }
    }

    /** @param array<int, array<string, mixed>> $included */
    private function validateCurrentOperationalGate(array $included): void
    {
        $includedNames = array_map(static fn (array $file): string => (string)$file['name'], $included);
        sort($includedNames);
        $expectedNames = array_keys($this->onlyFileNames);
        sort($expectedNames);
        if ($includedNames !== $expectedNames) {
            throw new RuntimeException('Current import gate failed: included files do not match current operational balance chain.');
        }

        $balances = $this->flowBalances();
        $cash = $balances['cash'] ?? null;
        if ($cash === null
            || abs((float)$cash['opening_balance'] - 2870.00) > 0.001
            || abs((float)$cash['balance'] - 15262.00) > 0.001
        ) {
            throw new RuntimeException('Current import gate failed: cash opening/balance does not match 2870.00 -> 15262.00.');
        }

        if (($this->stats['rows_created'] ?? 0) !== 126) {
            throw new RuntimeException('Current import gate failed: expected 126 operational rows.');
        }

        $expectedByFile = [
            '14.05.26+сервис.xlsx' => ['income' => 50520.00, 'expense' => 39676.00, 'net' => 10844.00],
            '06.06.xlsm' => ['income' => 5000.00, 'expense' => 14509.00, 'net' => -9509.00],
            '15.06.2026.xlsx' => ['income' => 24400.00, 'expense' => 13343.00, 'net' => 11057.00],
        ];
        foreach ($expectedByFile as $fileName => $expected) {
            $actual = $this->cashTotalsForSourceFile($fileName);
            foreach ($expected as $key => $expectedAmount) {
                if (abs((float)$actual[$key] - $expectedAmount) > 0.001) {
                    throw new RuntimeException("Current import gate failed: {$fileName} {$key} does not match expected chain.");
                }
            }
        }

        if ($this->hasImportedCarryIncome('15.06.2026.xlsx', 4205.00)) {
            throw new RuntimeException('Current import gate failed: 4205.00 carry/opening row was imported as operational income.');
        }
    }

    /** @return array{income: float, expense: float, net: float} */
    private function cashTotalsForSourceFile(string $fileName): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN e.sign = '+' THEN e.amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN e.sign = '-' THEN e.amount ELSE 0 END), 0) AS expense
            FROM v2_import_sources s
            INNER JOIN v2_entries e ON e.source_id = s.id AND e.archived_at IS NULL
            INNER JOIN v2_flows f ON f.id = e.flow_id
            WHERE s.workspace_id = ?
              AND s.file_name = ?
              AND f.type = 'cash'
        ");
        $stmt->execute([$this->workspaceId, $fileName]);
        $row = $stmt->fetch() ?: ['income' => 0, 'expense' => 0];
        $income = (float)$row['income'];
        $expense = (float)$row['expense'];

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    private function hasImportedCarryIncome(string $fileName, float $amount): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM v2_import_sources s
            INNER JOIN v2_entries e ON e.source_id = s.id AND e.archived_at IS NULL
            INNER JOIN v2_flows f ON f.id = e.flow_id
            WHERE s.workspace_id = ?
              AND s.file_name = ?
              AND f.type = 'cash'
              AND e.sign = '+'
              AND ABS(e.amount - ?) < 0.001
        ");
        $stmt->execute([$this->workspaceId, $fileName, $amount]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function validateArchiveRawGate(): void
    {
        if (($this->stats['rows_created'] ?? 0) !== 0) {
            throw new RuntimeException('Archive import gate failed: raw archive created operational rows.');
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM v2_entries WHERE workspace_id = ? AND archived_at IS NULL");
        $stmt->execute([$this->workspaceId]);
        if ((int)$stmt->fetchColumn() !== 0) {
            throw new RuntimeException('Archive import gate failed: raw archive workspace has operational entries.');
        }

        foreach ($this->flowBalances() as $type => $row) {
            if (abs((float)$row['balance']) > 0.001) {
                throw new RuntimeException("Archive import gate failed: {$type} balance is not zero.");
            }
        }
    }

    private function resetWorkspaceData(): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                DELETE r
                FROM v2_import_rows r
                INNER JOIN v2_import_sources s ON s.id = r.import_source_id
                WHERE s.workspace_id = ?
            ")->execute([$this->workspaceId]);
            $this->db->prepare("DELETE FROM v2_import_sources WHERE workspace_id = ?")->execute([$this->workspaceId]);
            $this->db->prepare("DELETE FROM v2_entries WHERE workspace_id = ?")->execute([$this->workspaceId]);
            $this->db->prepare("DELETE FROM v2_audit_log WHERE workspace_id = ?")->execute([$this->workspaceId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}

$sourceRoot = $argv[1] ?? '/home/alexey/GoogleDrive/Claudia Z/Бухгалтерия/Бухгалтерия';
$stagingRoot = $argv[2] ?? dirname(__DIR__) . '/storage/imports/claudia-z-local';
$reset = in_array('--reset', $argv, true);
$mode = 'full';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--mode=')) {
        $mode = substr($arg, 7);
    }
}

(new ClaudiaZLocalImporter($sourceRoot, $stagingRoot, $reset, $mode))->run();
