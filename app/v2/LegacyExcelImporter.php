<?php

declare(strict_types=1);

final class FinDeskV2LegacyExcelImporter
{
    public function read(string $bytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'findesk-v2-xlsx-');
        if ($tmp === false) {
            throw new FinDeskV2HttpError(500, 'xlsx_temp_failed');
        }

        file_put_contents($tmp, $bytes);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new FinDeskV2HttpError(422, 'invalid_xlsx');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $workbook = $this->workbook($zip);
            $relations = $this->workbookRelations($zip);
            $sheets = [];

            foreach ($workbook as $sheet) {
                $target = $relations[$sheet['relation_id']] ?? null;
                if ($target === null) {
                    continue;
                }

                $path = 'xl/' . ltrim($target, '/');
                $rows = $this->sheetRows($zip, $path, $sharedStrings);
                $sheets[] = [
                    'name' => $sheet['name'],
                    'rows' => $rows,
                ];
            }

            return $sheets;
        } finally {
            $zip->close();
            @unlink($tmp);
        }
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if (!$doc) {
            return [];
        }

        $strings = [];
        foreach ($doc->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string)$item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string)$run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function workbook(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/workbook.xml');
        if ($xml === false) {
            throw new FinDeskV2HttpError(422, 'xlsx_workbook_missing');
        }

        $doc = simplexml_load_string($xml);
        if (!$doc) {
            throw new FinDeskV2HttpError(422, 'xlsx_workbook_invalid');
        }

        $sheets = [];
        foreach ($doc->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes();
            $relAttrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheets[] = [
                'name' => (string)$attrs['name'],
                'relation_id' => (string)$relAttrs['id'],
            ];
        }

        return $sheets;
    }

    private function workbookRelations(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($xml === false) {
            throw new FinDeskV2HttpError(422, 'xlsx_relations_missing');
        }

        $doc = simplexml_load_string($xml);
        if (!$doc) {
            throw new FinDeskV2HttpError(422, 'xlsx_relations_invalid');
        }

        $relations = [];
        foreach ($doc->Relationship as $rel) {
            $attrs = $rel->attributes();
            $relations[(string)$attrs['Id']] = (string)$attrs['Target'];
        }

        return $relations;
    }

    private function sheetRows(ZipArchive $zip, string $path, array $sharedStrings): array
    {
        $xml = $zip->getFromName($path);
        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if (!$doc) {
            return [];
        }

        $rows = [];
        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $attrs = $cell->attributes();
                $ref = (string)($attrs['r'] ?? '');
                $type = (string)($attrs['t'] ?? '');
                $column = preg_replace('/[0-9]/', '', $ref) ?: '';
                $value = isset($cell->v) ? (string)$cell->v : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string)$cell->is->t : '';
                }

                if ($column !== '') {
                    $cells[$this->columnIndex($column)] = trim($value);
                }
            }

            if ($cells !== []) {
                ksort($cells);
                $rows[(int)$row['r']] = $cells;
            }
        }

        return $rows;
    }

    private function columnIndex(string $column): int
    {
        $index = 0;
        $chars = str_split(strtoupper($column));
        foreach ($chars as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index - 1;
    }
}
