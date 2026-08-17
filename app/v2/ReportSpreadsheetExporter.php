<?php

declare(strict_types=1);

final class FinDeskV2ReportSpreadsheetExporter
{
    public static function writeOperationalSummaryXlsx(string $path, array $workspace, array $reports, ?string $title = null): void
    {
        $normalizedReports = array_values(array_map([self::class, 'normalizeReport'], $reports));
        usort($normalizedReports, static function (array $a, array $b): int {
            $date = strcmp((string)$a['start_date'], (string)$b['start_date']);
            return $date !== 0 ? $date : strcmp((string)$a['title'], (string)$b['title']);
        });
        if ($normalizedReports === []) {
            throw new RuntimeException('No reports to export');
        }

        $title = $title ?: ((string)($workspace['name'] ?? 'FinDesk') . ': сводный отчет');
        $overviewRows = [
            ['cells' => [self::cell($title, 2)], '_height' => 24],
            ['cells' => [self::cell('Сформировано'), self::cell(date('d.m.Y H:i'))]],
            ['cells' => []],
            ['cells' => [
                self::cell('Период', 1),
                self::cell('Было', 1),
                self::cell('Некоммерческие поступления', 1),
                self::cell('Коммерческие', 1),
                self::cell('Расход', 1),
                self::cell('Стало', 1),
                self::cell('Записей', 1),
            ]],
        ];

        foreach ($normalizedReports as $report) {
            $overviewRows[] = ['cells' => [
                self::cell(self::periodLabel($report), 6),
                self::cell(self::money($report['totals']['opening_cash'] ?? 0), 3),
                self::cell(self::sumNonCommercialIncome($report), 3),
                self::cell(self::money($report['totals']['commercial_income'] ?? 0), 3),
                self::cell(self::money($report['totals']['cash_expense'] ?? 0), 3),
                self::cell(self::money($report['totals']['ending_cash'] ?? 0), 3),
                self::cell((int)$report['entry_count'], 6),
            ]];
        }

        $overviewRows[] = ['cells' => []];
        $overviewRows[] = ['cells' => [self::cell('Коммерческие поступления', 2)]];
        $overviewRows[] = ['cells' => [self::cell('Период', 1), self::cell('Дата', 1), self::cell('Запись', 1), self::cell('Сумма', 1), self::cell('Статус', 1)]];
        foreach ($normalizedReports as $report) {
            foreach ($report['entries'] as $entry) {
                if (($entry['direction'] ?? '') !== 'in' || ($entry['category_code'] ?? '') !== 'commercial_income') {
                    continue;
                }
                $overviewRows[] = ['cells' => [
                    self::cell(self::periodLabel($report), 6),
                    self::cell(self::ruDate((string)$entry['date']), 6),
                    self::cell((string)$entry['text'], 6),
                    self::cell(self::money($entry['income']), 3),
                    self::cell((string)$entry['status'], 6),
                ]];
            }
        }

        $overviewRows[] = ['cells' => []];
        $overviewRows[] = ['cells' => [self::cell('Некоммерческие поступления', 2)]];
        $overviewRows[] = ['cells' => [self::cell('Период', 1), self::cell('Дата', 1), self::cell('Запись', 1), self::cell('Сумма', 1), self::cell('Статус', 1)]];
        foreach ($normalizedReports as $report) {
            foreach (self::nonCommercialIncome($report) as $entry) {
                $overviewRows[] = ['cells' => [
                    self::cell(self::periodLabel($report), 6),
                    self::cell(self::ruDate((string)$entry['date']), 6),
                    self::cell((string)$entry['text'], 6),
                    self::cell(self::money($entry['income']), 3),
                    self::cell((string)$entry['status'], 6),
                ]];
            }
        }

        $sheets = [[
            'name' => 'Было стало',
            'rows' => $overviewRows,
            'widths' => [24, 14, 22, 16, 14, 14, 10],
            'frozen_rows' => 4,
        ]];

        foreach ($normalizedReports as $report) {
            $rows = [
                ['cells' => [self::cell('Отчет: ' . self::periodLabel($report), 2)], '_height' => 24],
                ['cells' => [
                    self::cell('Было'),
                    self::cell(self::money($report['totals']['opening_cash'] ?? 0), 3),
                    self::cell('Стало'),
                    self::cell(self::money($report['totals']['ending_cash'] ?? 0), 3),
                    self::cell('Записей'),
                    self::cell((int)$report['entry_count']),
                ]],
                ['cells' => []],
                ['cells' => [self::cell('Категория', 1), self::cell('Поступления', 1), self::cell('Расход', 1), self::cell('Итого', 1), self::cell('Записей', 1), self::cell('Контекст', 1), self::cell('Остаток после строки', 1)]],
            ];
            foreach (self::groupEntries($report['entries']) as $group) {
                $rows[] = ['cells' => [
                    self::cell((string)$group['category'], 5),
                    self::cell(self::money($group['income']), 4),
                    self::cell(self::money($group['expense']), 4),
                    self::cell(self::money($group['net']), 4),
                    self::cell((int)$group['count'], 5),
                    self::cell('раскрыть строки', 5),
                    self::cell('', 5),
                ]];
                foreach ($group['entries'] as $entry) {
                    $rows[] = [
                        '_outline' => 1,
                        '_hidden' => true,
                        'cells' => [
                            self::cell('  #' . $entry['row'] . ' · ' . self::ruDate((string)$entry['date']), 6),
                            self::cell($entry['income'] ?: '', 3),
                            self::cell($entry['expense'] ?: '', 3),
                            self::cell($entry['signed'], 3),
                            self::cell('', 6),
                            self::cell((string)$entry['text'], 6),
                            self::cell($entry['balance_after'], 3),
                        ],
                    ];
                }
            }
            $sheets[] = [
                'name' => self::sheetName(self::periodLabel($report)),
                'rows' => $rows,
                'widths' => [24, 14, 14, 14, 10, 58, 18],
                'frozen_rows' => 4,
            ];
        }

        self::createXlsx($path, $sheets);
    }

    public static function downloadFileName(array $workspace, array $reports): string
    {
        $normalizedReports = array_values(array_map([self::class, 'normalizeReport'], $reports));
        usort($normalizedReports, static fn (array $a, array $b): int => strcmp((string)$a['start_date'], (string)$b['start_date']));
        $first = $normalizedReports[0] ?? [];
        $last = $normalizedReports[count($normalizedReports) - 1] ?? $first;
        $name = (string)($workspace['name'] ?? 'findesk');
        $period = ((string)($first['start_date'] ?? 'report')) . '-' . ((string)($last['end_date'] ?? ''));
        $fileName = strtolower($name . '-' . $period . '-summary.xlsx');
        $fileName = preg_replace('/[^a-z0-9а-яё._-]+/ui', '-', $fileName) ?: 'findesk-summary.xlsx';
        return trim($fileName, '-.') ?: 'findesk-summary.xlsx';
    }

    private static function normalizeReport(array $report): array
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $header = is_array($summary['header'] ?? null) ? $summary['header'] : [];
        $entries = $report['entry_snapshot'] ?? $report['entries'] ?? $summary['entries'] ?? [];
        $entries = is_array($entries) ? array_values($entries) : [];
        $normalizedEntries = [];
        foreach ($entries as $index => $entry) {
            if (is_array($entry) && !self::isServiceCorrectionEntry($entry)) {
                $normalizedEntries[] = self::normalizeEntry($entry, $index);
            }
        }
        usort($normalizedEntries, static fn (array $a, array $b): int => ($a['row'] <=> $b['row']) ?: strcmp((string)$a['date'], (string)$b['date']));

        return [
            'id' => (string)($report['id'] ?? ''),
            'title' => (string)($report['title'] ?? ''),
            'start_date' => (string)($report['start_date'] ?? $report['period']['from'] ?? $header['start_date'] ?? ''),
            'end_date' => (string)($report['end_date'] ?? $report['period']['to'] ?? $header['end_date'] ?? ''),
            'entry_count' => count($normalizedEntries),
            'totals' => is_array($summary['totals'] ?? null) ? $summary['totals'] : [],
            'entries' => $normalizedEntries,
        ];
    }

    private static function isServiceCorrectionEntry(array $entry): bool
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

    private static function normalizeEntry(array $entry, int $index): array
    {
        $amount = self::money($entry['amount'] ?? 0);
        $direction = (string)($entry['direction'] ?? '');
        return [
            'row' => (int)($entry['fragment_row_number'] ?? $entry['row_number'] ?? ($index + 1)),
            'date' => (string)($entry['date'] ?? ''),
            'text' => (string)($entry['raw_text'] ?? $entry['text'] ?? ''),
            'direction' => $direction,
            'income' => $direction === 'in' ? $amount : 0.0,
            'expense' => $direction === 'out' ? $amount : 0.0,
            'signed' => $direction === 'in' ? $amount : -$amount,
            'balance_after' => isset($entry['balance_after']) ? self::money($entry['balance_after']) : null,
            'category' => self::categoryLabel($entry),
            'category_code' => (string)($entry['category_code'] ?? ''),
            'status' => (string)($entry['status'] ?? ''),
        ];
    }

    private static function categoryLabel(array $entry): string
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

    private static function groupEntries(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $label = (string)$entry['category'];
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
            $groups[$label]['income'] += (float)$entry['income'];
            $groups[$label]['expense'] += (float)$entry['expense'];
            $groups[$label]['net'] += (float)$entry['signed'];
            $groups[$label]['count']++;
            $groups[$label]['entries'][] = $entry;
        }
        uasort($groups, static function (array $a, array $b): int {
            $total = (($b['income'] + $b['expense']) <=> ($a['income'] + $a['expense']));
            return $total ?: strcmp((string)$a['category'], (string)$b['category']);
        });
        return array_values($groups);
    }

    private static function nonCommercialIncome(array $report): array
    {
        return array_values(array_filter($report['entries'], static function (array $entry): bool {
            return ($entry['direction'] ?? '') === 'in'
                && ($entry['category_code'] ?? '') === 'non_commercial_income';
        }));
    }

    private static function sumNonCommercialIncome(array $report): float
    {
        return array_sum(array_map(static fn (array $entry): float => (float)$entry['income'], self::nonCommercialIncome($report)));
    }

    private static function money(float|int|string|null $value): float
    {
        return round((float)($value ?? 0), 2);
    }

    private static function ruDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? date('d.m.Y', $ts) : $date;
    }

    private static function periodLabel(array $report): string
    {
        return self::ruDate((string)$report['start_date']) . ' - ' . self::ruDate((string)$report['end_date']);
    }

    private static function plainNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }

    private static function sheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\:\*\?\/\\\\]+/u', '-', $name) ?: 'Отчет';
        $name = str_replace(' ', '', $name);
        return function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
    }

    private static function cell(string|float|int|null $value, int $style = 0): array
    {
        return ['v' => $value, 's' => $style];
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function colName(int $index): string
    {
        $name = '';
        while ($index >= 0) {
            $name = chr(($index % 26) + 65) . $name;
            $index = intdiv($index, 26) - 1;
        }
        return $name;
    }

    private static function sheetXml(array $rows, array $widths = [], int $frozenRows = 0): string
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
                $out[] = '<col min="' . $col . '" max="' . $col . '" width="' . (float)$width . '" customWidth="1"/>';
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
                    $cell = self::cell($cell);
                }
                $value = $cell['v'] ?? null;
                $style = (int)($cell['s'] ?? 0);
                $ref = self::colName((int)$columnIndex) . ($rowIndex + 1);
                if ($value === null || $value === '') {
                    $out[] = '<c r="' . $ref . '" s="' . $style . '"/>';
                } elseif (is_int($value) || is_float($value)) {
                    $out[] = '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
                } else {
                    $out[] = '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t>' . self::xml((string)$value) . '</t></is></c>';
                }
            }
            $out[] = '</row>';
        }
        $out[] = '</sheetData></worksheet>';
        return implode('', $out);
    }

    private static function workbookXml(array $sheets): string
    {
        $items = [];
        foreach ($sheets as $index => $sheet) {
            $items[] = '<sheet name="' . self::xml($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . implode('', $items) . '</sheets></workbook>';
    }

    private static function workbookRelsXml(array $sheets): string
    {
        $rels = [];
        foreach ($sheets as $index => $_sheet) {
            $rels[] = '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }
        $rels[] = '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . implode('', $rels) . '</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00 &quot;€&quot;"/></numFmts>'
            . '<fonts count="4"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font><font><b/><sz val="14"/><name val="Arial"/></font><font><b/><color rgb="FF00856F"/><sz val="10"/><name val="Arial"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEFF3F8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F6F3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD5DEE8"/></left><right style="thin"><color rgb="FFD5DEE8"/></right><top style="thin"><color rgb="FFD5DEE8"/></top><bottom style="thin"><color rgb="FFD5DEE8"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function contentTypesXml(array $sheets): string
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

    private static function createXlsx(string $path, array $sheets): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create xlsx dir: ' . $dir);
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create xlsx: ' . $path);
        }
        $zip->addFromString('[Content_Types].xml', self::contentTypesXml($sheets));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml($sheets));
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        foreach ($sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', self::sheetXml($sheet['rows'], $sheet['widths'] ?? [], $sheet['frozen_rows'] ?? 0));
        }
        $zip->close();
    }
}
