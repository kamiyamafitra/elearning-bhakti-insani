<?php

namespace App\Http\Controllers\Concerns;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * One calm look for every exported workbook, matching the site's indigo theme:
 * an indigo title banner, soft lavender headers, light zebra rows and colour-coded statuses.
 */
trait WritesSpreadsheetRows
{
    private const string TITLE_COLOR = '3730A3';

    private const string NOTE_BACKGROUND = 'EEF0FF';

    private const string NOTE_FONT = '3730A3';

    private const string HEADER_BACKGROUND = 'E0E7FF';

    private const string HEADER_FONT = '1E1B4B';

    private const string HEADER_RULE_COLOR = '818CF8';

    private const string STRIPE_COLOR = 'F7F8FC';

    private const string BORDER_COLOR = 'E2E5EF';

    private const string BODY_FONT = '1F2937';

    /**
     * Status and letter-grade colours shared by every grade export: green is good, amber needs attention, red is a problem.
     *
     * @var array<string, array{background: string, font: string}>
     */
    protected const array GRADE_COLORS = [
        'Tuntas' => ['background' => 'DCFCE7', 'font' => '166534'],
        'Belum tuntas' => ['background' => 'FEE2E2', 'font' => 'B91C1C'],
        'Tidak mengumpulkan' => ['background' => 'FEE2E2', 'font' => 'B91C1C'],
        'Belum dinilai' => ['background' => 'FEF3C7', 'font' => '92400E'],
        'A' => ['background' => 'DCFCE7', 'font' => '166534'],
        'B' => ['background' => 'E0E7FF', 'font' => '3730A3'],
        'C' => ['background' => 'FEF3C7', 'font' => '92400E'],
        'D' => ['background' => 'FEE2E2', 'font' => 'B91C1C'],
    ];

    /**
     * Build a row that always writes strings as plain text, because OpenSpout turns any value starting with "=" into a live formula.
     *
     * @param  array<int, string|int|float|null>  $values
     */
    protected function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            fn (string|int|float|null $value): Cell => $this->textCell($value, $style),
            array_values($values),
        ));
    }

    /**
     * Write an indigo banner across the given number of columns of the current sheet.
     */
    protected function addTitleRow(XlsxWriter $writer, string $title, int $columnCount): void
    {
        $style = $this->baseStyle()
            ->withFontBold(true)
            ->withFontSize(14)
            ->withFontColor('FFFFFF')
            ->withBackgroundColor(self::TITLE_COLOR);

        $this->addMergedRow($writer, $this->textRow([$title], $style)->withHeight(32), $columnCount);
    }

    /**
     * Write a soft lavender line under the title, for things like the period or the grading weights.
     */
    protected function addNoteRow(XlsxWriter $writer, string $note, int $columnCount): void
    {
        $style = $this->baseStyle()
            ->withFontItalic(true)
            ->withFontColor(self::NOTE_FONT)
            ->withBackgroundColor(self::NOTE_BACKGROUND);

        $this->addMergedRow($writer, $this->textRow([$note], $style)->withHeight(22), $columnCount);
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function headerRow(array $values): Row
    {
        $rule = new BorderPart(BorderName::BOTTOM, self::HEADER_RULE_COLOR, BorderWidth::MEDIUM);
        $style = $this->baseStyle()
            ->withFontBold(true)
            ->withFontColor(self::HEADER_FONT)
            ->withBackgroundColor(self::HEADER_BACKGROUND)
            ->withCellAlignment(CellAlignment::CENTER)
            ->withShouldWrapText(true)
            ->withBorder($this->cellBorder($rule));

        return $this->textRow($values, $style)->withHeight(40);
    }

    /**
     * Build a bordered body row with alternating stripes; numbers and dashes are centered, decimals show one place.
     *
     * Cells whose value (or the letter in a "85 (B)" style grade) appears in $valueColors get that colour.
     *
     * @param  array<int, string|int|float|null>  $values
     * @param  array<string, array{background: string, font: string}>  $valueColors
     */
    protected function dataRow(array $values, int $rowIndex, array $valueColors = []): Row
    {
        $style = $this->baseStyle()->withFontColor(self::BODY_FONT)->withBorder($this->cellBorder());

        if ($rowIndex % 2 === 1) {
            $style = $style->withBackgroundColor(self::STRIPE_COLOR);
        }

        return new Row(array_map(function (string|int|float|null $value) use ($style, $valueColors): Cell {
            $cellStyle = is_numeric($value) && ! is_string($value) || $value === '-'
                ? $style->withCellAlignment(CellAlignment::CENTER)
                : $style;

            if (is_float($value)) {
                $cellStyle = $cellStyle->withFormat('0.0');
            }

            $colors = $this->colorsFor($value, $valueColors);

            if ($colors !== null) {
                $cellStyle = $cellStyle
                    ->withBackgroundColor($colors['background'])
                    ->withFontColor($colors['font'])
                    ->withFontBold(true)
                    ->withCellAlignment(CellAlignment::CENTER);
            }

            return $this->textCell($value, $cellStyle);
        }, array_values($values)))->withHeight(20);
    }

    /**
     * Set the column widths, in order from column A, and keep the top rows visible while scrolling.
     *
     * @param  array<int, int>  $columnWidths
     */
    protected function layoutSheet(Sheet $sheet, array $columnWidths, int $frozenRows): void
    {
        foreach (array_values($columnWidths) as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }

        $sheet->setSheetView((new SheetView)->withFreezeRow($frozenRows + 1));
    }

    /**
     * Add filter buttons to the header row (1-based) of the current sheet, covering every row written so far.
     */
    protected function addFilter(XlsxWriter $writer, int $headerRow, int $columnCount): void
    {
        $sheet = $writer->getCurrentSheet();

        if ($columnCount < 1 || $sheet->getWrittenRowCount() <= $headerRow) {
            return;
        }

        $sheet->setAutoFilter(new AutoFilter(0, $headerRow, $columnCount - 1, $sheet->getWrittenRowCount()));
    }

    private function addMergedRow(XlsxWriter $writer, Row $row, int $columnCount): void
    {
        $writer->addRow($row);

        $sheet = $writer->getCurrentSheet();
        $rowNumber = $sheet->getWrittenRowCount();
        $writer->getOptions()->mergeCells(0, $rowNumber, max(0, $columnCount - 1), $rowNumber, $sheet->getIndex());
    }

    /**
     * @param  array<string, array{background: string, font: string}>  $valueColors
     * @return array{background: string, font: string}|null
     */
    private function colorsFor(string|int|float|null $value, array $valueColors): ?array
    {
        if (! is_string($value) || $valueColors === []) {
            return null;
        }

        if (isset($valueColors[$value])) {
            return $valueColors[$value];
        }

        // Grades written as "85 (B)" take the colour of their letter.
        if (preg_match('/^\d+(?:[.,]\d+)? \(([A-D])\)$/', $value, $match) === 1) {
            return $valueColors[$match[1]] ?? null;
        }

        return null;
    }

    private function textCell(string|int|float|null $value, ?Style $style): Cell
    {
        return is_string($value) && $value !== ''
            ? new Cell\StringCell($value, $style)
            : Cell::fromValue($value, $style);
    }

    private function baseStyle(): Style
    {
        return (new Style(fontName: 'Calibri', fontSize: 11))->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    private function cellBorder(?BorderPart $override = null): Border
    {
        $parts = array_map(
            fn (BorderName $name): BorderPart => $override?->name === $name
                ? $override
                : new BorderPart($name, self::BORDER_COLOR, BorderWidth::THIN),
            BorderName::cases(),
        );

        return new Border(...$parts);
    }
}
