<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

const CLAUDIA_Z_WORKSPACE_ID = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';

function money(float|int|string|null $value): float
{
    return round((float)($value ?? 0), 2);
}

function ruDate(string $date): string
{
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
}

function periodLabel(array $report): string
{
    return ruDate($report['start_date']) . ' - ' . ruDate($report['end_date']);
}

function categoryLabel(array $entry): string
{
    $name = $entry['category_name'] ?? null;
    if (is_array($name) && isset($name['ru']) && trim((string)$name['ru']) !== '') {
        return (string)$name['ru'];
    }
    if (is_string($name) && trim($name) !== '') {
        return $name;
    }
    if (($entry['entry_type'] ?? '') === 'correction') {
        return 'Корректировки';
    }
    if (($entry['direction'] ?? '') === 'in') {
        return 'Поступления без отдельной категории';
    }
    return 'Прочее / проверка';
}

function normalizeEntry(array $entry): array
{
    $amount = money($entry['amount'] ?? 0);
    $direction = (string)($entry['direction'] ?? '');
    return [
        'row' => (int)($entry['fragment_row_number'] ?? $entry['row_number'] ?? 0),
        'date' => (string)($entry['date'] ?? ''),
        'text' => (string)($entry['raw_text'] ?? ''),
        'direction' => $direction,
        'income' => $direction === 'in' ? $amount : 0.0,
        'expense' => $direction === 'out' ? $amount : 0.0,
        'signed' => $direction === 'in' ? $amount : -$amount,
        'balance_after' => isset($entry['balance_after']) ? money($entry['balance_after']) : null,
        'category' => categoryLabel($entry),
        'category_code' => (string)($entry['category_code'] ?? ''),
        'status' => (string)($entry['status'] ?? ''),
    ];
}

function isServiceCorrectionEntry(array $entry): bool
{
    if (($entry['entry_type'] ?? '') !== 'correction') {
        return false;
    }
    foreach (($entry['matched_rules'] ?? []) as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $kind = (string)($rule['kind'] ?? '');
        $source = (string)($rule['source'] ?? '');
        if ($kind === 'visible_chain_seam' || in_array($source, ['month_correction', 'visible_chain_seam'], true)) {
            return true;
        }
    }
    return false;
}

function loadReports(PDO $db): array
{
    $stmt = $db->prepare("
        SELECT *
        FROM v2_report_batches
        WHERE workspace_id = ?
          AND status <> 'superseded'
        ORDER BY end_date DESC, generated_at DESC, created_at DESC
        LIMIT 3
    ");
    $stmt->execute([CLAUDIA_Z_WORKSPACE_ID]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    usort($reports, static fn (array $a, array $b): int => strcmp((string)$a['start_date'], (string)$b['start_date']));

    foreach ($reports as &$report) {
        $summary = json_decode((string)$report['summary_json'], true);
        if (!is_array($summary)) {
            throw new RuntimeException('Bad report summary JSON: ' . $report['id']);
        }
        $entries = array_map('normalizeEntry', array_values(array_filter($summary['entries'] ?? [], static fn (array $entry): bool => !isServiceCorrectionEntry($entry))));
        usort($entries, static fn (array $a, array $b): int => ($a['row'] <=> $b['row']) ?: strcmp($a['date'], $b['date']));
        $report['summary'] = $summary;
        $report['entries'] = $entries;
    }
    unset($report);

    return $reports;
}

function loadHistoricalBridgeReport(PDO $db): ?array
{
    $stmt = $db->prepare("
        SELECT *
        FROM v2_report_batches
        WHERE workspace_id = ?
          AND title LIKE 'Исторический отчет-смычка%'
        ORDER BY generated_at DESC, created_at DESC
        LIMIT 1
    ");
    $stmt->execute([CLAUDIA_Z_WORKSPACE_ID]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        return null;
    }

    $summary = json_decode((string)$report['summary_json'], true);
    if (!is_array($summary)) {
        throw new RuntimeException('Bad report summary JSON: ' . $report['id']);
    }
    $entries = array_map('normalizeEntry', array_values(array_filter($summary['entries'] ?? [], static fn (array $entry): bool => !isServiceCorrectionEntry($entry))));
    usort($entries, static fn (array $a, array $b): int => ($a['row'] <=> $b['row']) ?: strcmp($a['date'], $b['date']));
    $report['summary'] = $summary;
    $report['entries'] = $entries;
    return $report;
}

function groupReportEntries(array $entries): array
{
    $groups = [];
    foreach ($entries as $entry) {
        $label = $entry['category'];
        if (!isset($groups[$label])) {
            $groups[$label] = [
                'category' => $label,
                'income' => 0.0,
                'expense' => 0.0,
                'net' => 0.0,
                'count' => 0,
                'entries' => [],
            ];
        }
        $groups[$label]['income'] += $entry['income'];
        $groups[$label]['expense'] += $entry['expense'];
        $groups[$label]['net'] += $entry['signed'];
        $groups[$label]['count']++;
        $groups[$label]['entries'][] = $entry;
    }

    uasort($groups, static function (array $a, array $b): int {
        $total = ($b['income'] + $b['expense']) <=> ($a['income'] + $a['expense']);
        return $total ?: strcmp($a['category'], $b['category']);
    });

    return array_values($groups);
}

function nonCommercialIncome(array $report): array
{
    return array_values(array_filter($report['entries'], static function (array $entry): bool {
        return $entry['direction'] === 'in'
            && $entry['category_code'] === 'non_commercial_income';
    }));
}

function cell(string|float|int|null $value, int $style = 0, string $type = 'auto'): array
{
    return ['v' => $value, 's' => $style, 't' => $type];
}

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function colName(int $index): string
{
    $name = '';
    while ($index >= 0) {
        $name = chr(($index % 26) + 65) . $name;
        $index = intdiv($index, 26) - 1;
    }
    return $name;
}

function sheetXml(array $rows, array $widths = [], int $frozenRows = 0): string
{
    $out = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'];
    $out[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $out[] = '<sheetPr><outlinePr summaryBelow="0"/></sheetPr>';
    if ($frozenRows > 0) {
        $out[] = '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $frozenRows . '" topLeftCell="A' . ($frozenRows + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    }
    if ($widths) {
        $out[] = '<cols>';
        foreach ($widths as $index => $width) {
            $col = $index + 1;
            $out[] = '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
        }
        $out[] = '</cols>';
    }
    $out[] = '<sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $attrs = ['r="' . ($rowIndex + 1) . '"'];
        if (($row['_outline'] ?? 0) > 0) {
            $attrs[] = 'outlineLevel="' . (int)$row['_outline'] . '"';
        }
        if (($row['_hidden'] ?? false) === true) {
            $attrs[] = 'hidden="1"';
        }
        if (($row['_height'] ?? 0) > 0) {
            $attrs[] = 'ht="' . (float)$row['_height'] . '" customHeight="1"';
        }
        $out[] = '<row ' . implode(' ', $attrs) . '>';
        $cells = $row['cells'] ?? $row;
        foreach ($cells as $columnIndex => $cell) {
            if (!is_array($cell)) {
                $cell = cell($cell);
            }
            $value = $cell['v'] ?? null;
            $style = (int)($cell['s'] ?? 0);
            $ref = colName((int)$columnIndex) . ($rowIndex + 1);
            if ($value === null || $value === '') {
                $out[] = '<c r="' . $ref . '" s="' . $style . '"/>';
            } elseif (is_int($value) || is_float($value)) {
                $out[] = '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
            } else {
                $out[] = '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t>' . xml((string)$value) . '</t></is></c>';
            }
        }
        $out[] = '</row>';
    }
    $out[] = '</sheetData>';
    $out[] = '</worksheet>';
    return implode('', $out);
}

function workbookXml(array $sheets): string
{
    $items = [];
    foreach ($sheets as $index => $sheet) {
        $items[] = '<sheet name="' . xml($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . implode('', $items) . '</sheets></workbook>';
}

function workbookRelsXml(array $sheets): string
{
    $rels = [];
    foreach ($sheets as $index => $_sheet) {
        $rels[] = '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
    }
    $rels[] = '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . implode('', $rels) . '</Relationships>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00 &quot;€&quot;"/></numFmts>'
        . '<fonts count="4"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font><font><b/><sz val="14"/><name val="Arial"/></font><font><b/><color rgb="FF00856F"/><sz val="10"/><name val="Arial"/></font></fonts>'
        . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEFF3F8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F6F3"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD5DEE8"/></left><right style="thin"><color rgb="FFD5DEE8"/></right><top style="thin"><color rgb="FFD5DEE8"/></top><bottom style="thin"><color rgb="FFD5DEE8"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function contentTypesXml(array $sheets): string
{
    $overrides = [
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>',
    ];
    foreach ($sheets as $index => $_sheet) {
        $overrides[] = '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' . implode('', $overrides) . '</Types>';
}

function rootRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
}

function createXlsx(string $path, array $sheets): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create xlsx: ' . $path);
    }
    $zip->addFromString('[Content_Types].xml', contentTypesXml($sheets));
    $zip->addFromString('_rels/.rels', rootRelsXml());
    $zip->addFromString('xl/workbook.xml', workbookXml($sheets));
    $zip->addFromString('xl/_rels/workbook.xml.rels', workbookRelsXml($sheets));
    $zip->addFromString('xl/styles.xml', stylesXml());
    foreach ($sheets as $index => $sheet) {
        $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', sheetXml($sheet['rows'], $sheet['widths'] ?? [], $sheet['frozen_rows'] ?? 0));
    }
    $zip->close();
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}

$db = ql_db();
$reports = loadReports($db);
$historicalBridgeReport = loadHistoricalBridgeReport($db);
$bridgeEndDate = $historicalBridgeReport ? (string)$historicalBridgeReport['end_date'] : null;
$nonOverlappingReports = array_values(array_filter($reports, static function (array $report) use ($bridgeEndDate): bool {
    if ($bridgeEndDate === null) {
        return true;
    }
    return strcmp((string)$report['start_date'], $bridgeEndDate) > 0;
}));
$sheetReports = array_values(array_filter(array_merge([$historicalBridgeReport], $nonOverlappingReports)));
$overviewRows = [
    ['cells' => [cell('Claudia Z: отчет с апреля 2025', 2)], '_height' => 24],
    ['cells' => [cell('Сформировано'), cell(date('d.m.Y H:i'))]],
    ['cells' => []],
    ['cells' => [cell('Период', 1), cell('Было', 1), cell('Некоммерческие поступления', 1), cell('Коммерческие', 1), cell('Расход', 1), cell('Стало', 1), cell('Записей', 1)]],
];

if ($historicalBridgeReport) {
    $totals = $historicalBridgeReport['summary']['totals'];
    $nonCommercialIncome = array_sum(array_map(static fn (array $entry): float => $entry['income'], nonCommercialIncome($historicalBridgeReport)));
    $overviewRows[] = ['cells' => [
        cell(periodLabel($historicalBridgeReport) . ' · исторический блок', 6),
        cell(money($totals['opening_cash'] ?? 0), 3),
        cell($nonCommercialIncome, 3),
        cell(money($totals['commercial_income'] ?? 0), 3),
        cell(money($totals['cash_expense'] ?? 0), 3),
        cell(money($totals['ending_cash'] ?? 0), 3),
        cell(count($historicalBridgeReport['entries']), 6),
    ]];
}

foreach ($nonOverlappingReports as $report) {
    $totals = $report['summary']['totals'];
    $nonCommercialIncome = array_sum(array_map(static fn (array $entry): float => $entry['income'], nonCommercialIncome($report)));
    $overviewRows[] = ['cells' => [
        cell(periodLabel($report), 6),
        cell(money($totals['opening_cash'] ?? 0), 3),
        cell($nonCommercialIncome, 3),
        cell(money($totals['commercial_income'] ?? 0), 3),
        cell(money($totals['cash_expense'] ?? 0), 3),
        cell(money($totals['ending_cash'] ?? 0), 3),
        cell(count($report['entries']), 6),
    ]];
}

$overviewRows[] = ['cells' => []];
$overviewRows[] = ['cells' => [cell('Коммерческие поступления', 2)]];
$overviewRows[] = ['cells' => [cell('Период', 1), cell('Дата', 1), cell('Запись', 1), cell('Сумма', 1), cell('Статус', 1)]];
foreach ($sheetReports as $report) {
    foreach ($report['entries'] as $entry) {
        if ($entry['direction'] !== 'in' || $entry['category_code'] !== 'commercial_income') {
            continue;
        }
        $overviewRows[] = ['cells' => [
            cell(periodLabel($report), 6),
            cell(ruDate($entry['date']), 6),
            cell($entry['text'], 6),
            cell($entry['income'], 3),
            cell($entry['status'], 6),
        ]];
    }
}

$overviewRows[] = ['cells' => []];
$overviewRows[] = ['cells' => [cell('Некоммерческие поступления', 2)]];
$overviewRows[] = ['cells' => [cell('Период', 1), cell('Дата', 1), cell('Запись', 1), cell('Сумма', 1), cell('Статус', 1)]];
foreach ($sheetReports as $report) {
    foreach (nonCommercialIncome($report) as $entry) {
        $overviewRows[] = ['cells' => [
            cell(periodLabel($report), 6),
            cell(ruDate($entry['date']), 6),
            cell($entry['text'], 6),
            cell($entry['income'], 3),
            cell($entry['status'], 6),
        ]];
    }
}

$sheets = [[
    'name' => 'Было стало',
    'rows' => $overviewRows,
    'widths' => [24, 14, 22, 16, 14, 16, 14, 10],
    'frozen_rows' => 4,
]];

foreach ($sheetReports as $report) {
    $totals = $report['summary']['totals'];
    $rows = [
        ['cells' => [cell('Отчет: ' . periodLabel($report), 2)], '_height' => 24],
        ['cells' => [cell('Было'), cell(money($totals['opening_cash'] ?? 0), 3), cell('Стало'), cell(money($totals['ending_cash'] ?? 0), 3), cell('Записей'), cell((int)$report['entry_count'])]],
        ['cells' => []],
        ['cells' => [cell('Категория', 1), cell('Поступления', 1), cell('Расход', 1), cell('Итого', 1), cell('Записей', 1), cell('Контекст', 1), cell('Остаток после строки', 1)]],
    ];
    foreach (groupReportEntries($report['entries']) as $group) {
        $rows[] = ['cells' => [
            cell($group['category'], 5),
            cell(money($group['income']), 4),
            cell(money($group['expense']), 4),
            cell(money($group['net']), 4),
            cell((int)$group['count'], 5),
            cell('раскрыть строки слева', 5),
            cell('', 5),
        ]];
        foreach ($group['entries'] as $entry) {
            $rows[] = [
                '_outline' => 1,
                '_hidden' => true,
                'cells' => [
                    cell('  #' . $entry['row'] . ' · ' . ruDate($entry['date']), 6),
                    cell($entry['income'] ?: '', 3),
                    cell($entry['expense'] ?: '', 3),
                    cell($entry['signed'], 3),
                    cell('', 6),
                    cell($entry['text'], 6),
                    cell($entry['balance_after'], 3),
                ],
            ];
        }
    }
    $sheets[] = [
        'name' => str_replace(' ', '', str_replace(' - ', '-', periodLabel($report))),
        'rows' => $rows,
        'widths' => [24, 14, 14, 14, 10, 58, 18],
        'frozen_rows' => 4,
    ];
}

$outDir = dirname(__DIR__) . '/storage/production-audits/google-sheets';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    throw new RuntimeException('Cannot create output dir: ' . $outDir);
}
$path = $outDir . '/claudia-z-three-latest-reports-' . date('Ymd-His') . '.xlsx';
createXlsx($path, $sheets);

echo json_encode([
    'ok' => true,
    'path' => $path,
    'reports' => array_map(static fn (array $report): array => [
        'id' => $report['id'],
        'period' => periodLabel($report),
        'entries' => count($report['entries']),
        'opening' => money($report['summary']['totals']['opening_cash'] ?? 0),
        'ending' => money($report['summary']['totals']['ending_cash'] ?? 0),
        'commercial_income' => money($report['summary']['totals']['commercial_income'] ?? 0),
        'non_commercial_income' => array_sum(array_map(static fn (array $entry): float => $entry['income'], nonCommercialIncome($report))),
    ], $sheetReports),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
