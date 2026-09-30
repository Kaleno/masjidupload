<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Minimal .xlsx builder so exports need no extra Composer package on shared hosting.
 */
final class XlsxWriter
{
    public const Plain = 0;

    public const Header = 1;

    public const Cell = 2;

    public const Holiday = 3;

    public const Title = 4;

    public const Muted = 5;

    public const Warning = 6;

    /**
     * @var list<array{name: string, rows: list<list<mixed>>, widths: array<int, float>, freeze: ?string, merges: list<string>}>
     */
    private array $sheets = [];

    /**
     * Cells are scalars (Cell style) or ['value' => ..., 'style' => self::*].
     *
     * @param  list<list<mixed>>  $rows
     * @param  array{widths?: array<int, float>, freeze?: string|null, merges?: list<string>}  $options
     */
    public function addSheet(string $name, array $rows, array $options = []): self
    {
        $this->sheets[] = [
            'name' => $this->uniqueSheetName($name),
            'rows' => $rows,
            'widths' => $options['widths'] ?? [],
            'freeze' => $options['freeze'] ?? null,
            'merges' => $options['merges'] ?? [],
        ];

        return $this;
    }

    public function download(string $filename): BinaryFileResponse
    {
        return response()
            ->download($this->save(), $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    public function save(?string $path = null): string
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1', []);
        }

        $path ??= tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak bisa membuat file Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet));
        }

        $zip->close();

        return $path;
    }

    public static function column(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $letters;
    }

    private function uniqueSheetName(string $name): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name)) ?: 'Sheet', 0, 28);
        $candidate = $base;
        $taken = array_column($this->sheets, 'name');
        $suffix = 2;

        while (in_array($candidate, $taken, true)) {
            $candidate = $base.' '.$suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array{name: string, rows: list<list<mixed>>, widths: array<int, float>, freeze: ?string, merges: list<string>}  $sheet
     */
    private function worksheet(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        if ($sheet['freeze'] !== null) {
            [$col, $row] = $this->splitReference($sheet['freeze']);
            $pane = '<pane'
                .($col > 0 ? ' xSplit="'.$col.'"' : '')
                .($row > 0 ? ' ySplit="'.$row.'"' : '')
                .' topLeftCell="'.$sheet['freeze'].'" activePane="bottomRight" state="frozen"/>';
            $xml .= '<sheetViews><sheetView workbookViewId="0">'.$pane.'</sheetView></sheetViews>';
        }

        if ($sheet['widths'] !== []) {
            $xml .= '<cols>';
            foreach ($sheet['widths'] as $index => $width) {
                $n = $index + 1;
                $xml .= '<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $r => $cells) {
            $rowNumber = $r + 1;
            $xml .= '<row r="'.$rowNumber.'">';

            foreach (array_values($cells) as $c => $cell) {
                $style = self::Cell;
                $value = $cell;

                if (is_array($cell)) {
                    $style = $cell['style'] ?? self::Cell;
                    $value = $cell['value'] ?? null;
                }

                $ref = self::column($c).$rowNumber;

                if ($value === null || $value === '') {
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"/>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                        .$this->escape((string) $value).'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($sheet['merges'] !== []) {
            $xml .= '<mergeCells count="'.count($sheet['merges']).'">';
            foreach ($sheet['merges'] as $range) {
                $xml .= '<mergeCell ref="'.$range.'"/>';
            }
            $xml .= '</mergeCells>';
        }

        return $xml.'</worksheet>';
    }

    /**
     * @return array{0: int, 1: int} zero-based column count and row count before the reference
     */
    private function splitReference(string $reference): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $reference, $m);
        $col = 0;
        foreach (str_split($m[1] ?? 'A') as $char) {
            $col = $col * 26 + (ord($char) - 64);
        }

        return [$col - 1, ((int) ($m[2] ?? 1)) - 1];
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        $sheets = '';
        foreach (array_keys($this->sheets) as $index) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $n = $index + 1;
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach (array_keys($this->sheets) as $index) {
            $n = $index + 1;
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }

        $stylesId = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId'.$stylesId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    /**
     * Order of cellXfs must match the style constants.
     */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'<font><sz val="11"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="5">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFCCFBF1"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFECACA"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
