<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/v2/Repository.php';

$db = FinDeskV2Database::pdo();
$repo = new FinDeskV2Repository($db);

$userId = 1;
$claudiaWorkspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
$archiveWorkspaceId = '3bb2f598-540e-4878-9d92-aad24a7d12ac';
$title = 'Исторический отчет от 31.03.2025 · период 2025-02-01 - 2025-03-22';
$entrySeqFrom = 17896;
$entrySeqTo = 17904;
$stamp = date('Ymd-His');

function json_out($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function invoke_private(object $object, string $method, array $args)
{
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($object, $args);
}

function xlsx_col(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xlsx_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xlsx_sheet_xml(array $rows, array $widths = [], array $merges = []): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
    if ($widths !== []) {
        $xml .= '<cols>';
        foreach ($widths as $i => $width) {
            $col = $i + 1;
            $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . (float)$width . '" customWidth="1"/>';
        }
        $xml .= '</cols>';
    }
    $xml .= '<sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $r = $rowIndex + 1;
        $xml .= '<row r="' . $r . '">';
        foreach ($row as $colIndex => $cell) {
            $ref = xlsx_col($colIndex + 1) . $r;
            $style = '';
            if (is_array($cell)) {
                $style = isset($cell['s']) ? ' s="' . (int)$cell['s'] . '"' : '';
                $cell = $cell['v'] ?? '';
            }
            if (is_int($cell) || is_float($cell)) {
                $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $cell . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t>' . xlsx_escape((string)$cell) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData>';
    if ($merges !== []) {
        $xml .= '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $range) {
            $xml .= '<mergeCell ref="' . xlsx_escape((string)$range) . '"/>';
        }
        $xml .= '</mergeCells>';
    }
    $xml .= '<pageMargins left="0.35" right="0.35" top="0.45" bottom="0.45" header="0.2" footer="0.2"/>'
        . '<pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="1"/>';
    return $xml . '</worksheet>';
}

function write_xlsx(string $path, array $sheets): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('xlsx_open_failed');
    }

    $sheetOverrides = [];
    $workbookSheets = [];
    $rels = [];
    foreach (array_keys($sheets) as $index => $name) {
        $sheetId = $index + 1;
        $sheetOverrides[] = '<Override PartName="/xl/worksheets/sheet' . $sheetId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbookSheets[] = '<sheet name="' . xlsx_escape((string)$name) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        $rels[] = '<Relationship Id="rId' . $sheetId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . implode('', $sheetOverrides)
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . implode('', $workbookSheets) . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $rels)
        . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="6">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="12"/><color rgb="FF111827"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF475467"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="12"/><color rgb="FF047857"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="12"/><color rgb="FFB42318"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="6">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1769E0"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF7F2"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border/>'
        . '<border><left style="thin"><color rgb="FFD8E0EA"/></left><right style="thin"><color rgb="FFD8E0EA"/></right><top style="thin"><color rgb="FFD8E0EA"/></top><bottom style="thin"><color rgb="FFD8E0EA"/></bottom></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="8">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
        . '</cellXfs>'
        . '</styleSheet>');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>FinDesk credit audit</dc:title><dc:creator>FinDesk v2</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('c') . '</dcterms:created>'
        . '</cp:coreProperties>');
    $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>FinDesk v2</Application></Properties>');

    $index = 1;
    foreach ($sheets as $sheet) {
        $zip->addFromString(
            'xl/worksheets/sheet' . $index . '.xml',
            xlsx_sheet_xml($sheet['rows'], $sheet['widths'] ?? [], $sheet['merges'] ?? [])
        );
        $index++;
    }
    $zip->close();
}

$backupDir = __DIR__ . '/../storage/production-audits/historical-march-2025-report-' . $stamp;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}
$backup = [];
foreach (['v2_report_batches', 'v2_report_batch_entries', 'v2_report_batch_html_snapshots', 'v2_audit_log'] as $table) {
    $backup[$table] = $db->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
}
file_put_contents($backupDir . '/report-tables-before.json', json_out($backup));

$existing = $db->prepare("
    SELECT *
    FROM v2_report_batches
    WHERE workspace_id = ?
      AND title = ?
      AND batch_type = 'operational_fragment'
    LIMIT 1
");
$existing->execute([$claudiaWorkspaceId, $title]);
$existingReport = $existing->fetch(PDO::FETCH_ASSOC);

$entryStmt = $db->prepare("
    SELECT id
    FROM v2_entries
    WHERE workspace_id = ?
      AND created_seq BETWEEN ? AND ?
    ORDER BY date, created_seq
");
$entryStmt->execute([$archiveWorkspaceId, $entrySeqFrom, $entrySeqTo]);
$entryIds = array_map('strval', $entryStmt->fetchAll(PDO::FETCH_COLUMN));
if (count($entryIds) !== 9) {
    throw new RuntimeException('historical_entry_slice_not_found');
}

if (!$existingReport) {
    $created = $repo->createOperationalReportFragment($archiveWorkspaceId, [
        'entry_ids' => $entryIds,
        'title' => $title,
        'status' => 'created',
        'closed_date' => '2025-03-31',
        'allow_locked_entries' => true,
    ], $userId);

    $batchId = (string)$created['id'];
    $batch = $repo->getOperationalReportFragment($archiveWorkspaceId, $batchId, $userId);
    $summary = $batch['summary'];
    $sourceTrace = $batch['source_trace'];
    $summary['header']['workspace'] = [
        'id' => $claudiaWorkspaceId,
        'name' => 'Claudia Z',
        'type' => 'yacht',
    ];
    $summary['header']['historical_source_workspace'] = [
        'id' => $archiveWorkspaceId,
        'name' => 'Claudia Z Archive Raw History',
    ];
    $summary['source_trace']['historical_source_workspace'] = $summary['header']['historical_source_workspace'];
    $sourceTrace['historical_source_workspace'] = $summary['header']['historical_source_workspace'];
    $summary['header']['closed_date'] = '2025-03-31';

    $htmlFilename = 'storage/v2/report-batches/' . $claudiaWorkspaceId . '/' . $batchId . '.html';
    $contentPayload = [
        'report_type' => 'operational_fragment',
        'workspace_id' => $claudiaWorkspaceId,
        'batch_id' => $batchId,
        'title' => $title,
        'status' => $batch['status'],
        'closed_at' => $batch['closed_at'],
        'summary' => $summary,
        'source_entry_ids' => $batch['source_entry_ids'],
        'entry_snapshot' => $batch['entry_snapshot'],
    ];
    $contentHash = hash('sha256', FinDeskV2Support::jsonEncode($contentPayload));

    $db->prepare("
        UPDATE v2_report_batches
        SET workspace_id = ?,
            title = ?,
            html_filename = ?,
            summary_json = ?,
            source_trace_json = ?,
            content_hash = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
        LIMIT 1
    ")->execute([
        $claudiaWorkspaceId,
        $title,
        $htmlFilename,
        FinDeskV2Support::jsonEncode($summary),
        FinDeskV2Support::jsonEncode($sourceTrace),
        $contentHash,
        $batchId,
    ]);

    $db->prepare("DELETE FROM v2_report_batch_html_snapshots WHERE batch_id = ?")->execute([$batchId]);

    $batch = $repo->getOperationalReportFragment($claudiaWorkspaceId, $batchId, $userId);
    $workspace = $repo->getWorkspace($claudiaWorkspaceId, $userId);
    invoke_private($repo, 'writeOperationalReportHtmlFile', [$batch, $workspace]);
    invoke_private($repo, 'storeOperationalReportFragmentHtmlSnapshot', [
        $claudiaWorkspaceId,
        $batch,
        $workspace,
        $userId,
        'stored',
        'Historical March 2025 report attached to Claudia Z from archive source rows',
        false,
    ]);

    $db->prepare("
        INSERT INTO v2_audit_log (id, workspace_id, entity_type, entity_id, action, before_json, after_json, performed_by)
        VALUES (?, ?, 'report_batch', ?, 'historical_report_attach_to_claudia_z', NULL, ?, ?)
    ")->execute([
        FinDeskV2Support::uuid(),
        $claudiaWorkspaceId,
        $batchId,
        FinDeskV2Support::jsonEncode([
            'title' => $title,
            'source_workspace_id' => $archiveWorkspaceId,
            'source_entry_created_seq' => [$entrySeqFrom, $entrySeqTo],
            'entry_ids' => $entryIds,
        ]),
        $userId,
    ]);
} else {
    $batchId = (string)$existingReport['id'];
}

$creditUsed = 7000;
$confirmedReturn = 3500;
$paymentRows = [
    [['v' => 'Дата', 's' => 3], ['v' => 'Операция', 's' => 3], ['v' => 'Запись', 's' => 3], ['v' => 'Взял', 's' => 3], ['v' => 'Вернул', 's' => 3], ['v' => 'Остаток', 's' => 3]],
    [['v' => '07.11.2024', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'мой кредит', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '1 000 €', 's' => 7]],
    [['v' => '31.01.2025', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'мой кредит', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '2 000 €', 's' => 7]],
    [['v' => '01.02.2025', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'мой кредит 02.25', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '3 000 €', 's' => 7]],
    [['v' => '22.03.2025', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'мой кредит 03.25', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '4 000 €', 's' => 7]],
    [['v' => '10.04.2025', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'кредит себе', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '5 000 €', 's' => 7]],
    [['v' => '05.05.2025', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'моя часть кредита', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '6 000 €', 's' => 7]],
    [['v' => '16.06.2025', 's' => 4], ['v' => 'взял', 's' => 4], ['v' => 'последний кредит июль', 's' => 4], ['v' => '1 000 €', 's' => 6], ['v' => '', 's' => 4], ['v' => '7 000 €', 's' => 7]],
    [['v' => '08.07.2025', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'возврат моего долга 07.25', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '6 500 €', 's' => 7]],
    [['v' => '16.07.2025', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'долг за август', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '6 000 €', 's' => 7]],
    [['v' => '12.09.2025', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'мой долг', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '5 500 €', 's' => 7]],
    [['v' => '13.10.2025', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'мой долг', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '5 000 €', 's' => 7]],
    [['v' => '11.12.2025', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'мой долг', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '4 500 €', 's' => 7]],
    [['v' => '20.01.2026', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'мой долг', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '4 000 €', 's' => 7]],
    [['v' => '23.04.2026', 's' => 4], ['v' => 'вернул', 's' => 4], ['v' => 'мой долг', 's' => 4], ['v' => '', 's' => 4], ['v' => '500 €', 's' => 5], ['v' => '3 500 €', 's' => 7]],
    [['v' => 'Итого', 's' => 3], ['v' => '', 's' => 3], ['v' => '', 's' => 3], ['v' => '7 000 €', 's' => 6], ['v' => '3 500 €', 's' => 5], ['v' => '3 500 €', 's' => 7]],
];
$sheets = [
    'Кредит' => [
        'widths' => [13, 14, 31, 13, 13, 15],
        'merges' => ['A1:F1', 'A2:F2', 'A4:B4', 'C4:D4', 'E4:F4', 'A6:F6', 'A24:F24'],
        'rows' => [
            [['v' => 'Claudia Z · расчет личного кредита', 's' => 1], '', '', '', '', ''],
            [['v' => 'По найденным платежам: взято 7 000 €, возвращено 3 500 €, остаток 3 500 €', 's' => 2], '', '', '', '', ''],
            ['', '', '', '', '', ''],
            [['v' => 'Всего взято: 7 000 €', 's' => 6], '', ['v' => 'Всего возвращено: 3 500 €', 's' => 5], '', ['v' => 'Остаток: 3 500 €', 's' => 7], ''],
            ['', '', '', '', '', ''],
            [['v' => 'Хронология платежей', 's' => 2], '', '', '', '', ''],
            ...$paymentRows,
            ['', '', '', '', '', ''],
            [['v' => 'Примечание: прямой строки “взял кредит” в базе не найдено. Расчет составлен по платежам “мой кредит / кредит себе / моя часть кредита / последний кредит” и возвратам “мой долг / долг за август”.', 's' => 4], '', '', '', '', ''],
        ],
    ],
];
$xlsxPath = __DIR__ . '/../storage/exports/claudia-z-credit-audit-2024-2026.xlsx';
write_xlsx($xlsxPath, $sheets);

$finalReport = $repo->getOperationalReportFragment($claudiaWorkspaceId, $batchId, $userId);
echo json_out([
    'report_id' => $batchId,
    'report_workspace_id' => $claudiaWorkspaceId,
    'report_title' => $finalReport['title'],
    'report_dates' => [$finalReport['start_date'], $finalReport['end_date']],
    'report_entries' => $finalReport['entry_count'],
    'report_html' => $finalReport['html_filename'],
    'backup' => substr($backupDir, strlen(dirname(__DIR__)) + 1) . '/report-tables-before.json',
    'credit_xlsx' => substr($xlsxPath, strlen(dirname(__DIR__)) + 1),
    'credit_remaining' => $creditUsed - $confirmedReturn,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
