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
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

trait WritesSpreadsheetRows
{
    private const string TITLE_COLOR = '1E40AF';

    private const string HEADER_COLOR = '2563EB';

    private const string STRIPE_COLOR = 'EEF3FB';

    private const string BORDER_COLOR = 'D1D5DB';

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
     * Write a blue banner across the given number of columns of the current sheet.
     */
    protected function addTitleRow(XlsxWriter $writer, string $title, int $columnCount): void
    {
        $style = $this->baseStyle()
            ->withFontBold(true)
            ->withFontSize(14)
            ->withFontColor('FFFFFF')
            ->withBackgroundColor(self::TITLE_COLOR);

        $writer->addRow($this->textRow([$title], $style)->withHeight(30));

        $sheet = $writer->getCurrentSheet();
        $titleRow = $sheet->getWrittenRowCount();
        $writer->getOptions()->mergeCells(0, $titleRow, $columnCount - 1, $titleRow, $sheet->getIndex());
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function headerRow(array $values): Row
    {
        $style = $this->baseStyle()
            ->withFontBold(true)
            ->withFontColor('FFFFFF')
            ->withBackgroundColor(self::HEADER_COLOR)
            ->withCellAlignment(CellAlignment::CENTER)
            ->withShouldWrapText(true)
            ->withBorder($this->cellBorder());

        return $this->textRow($values, $style)->withHeight(48);
    }

    /**
     * Build a bordered body row with alternating stripes; numbers and dashes are centered.
     *
     * @param  array<int, string|int|float|null>  $values
     * @param  array<string, array{background: string, font: string}>  $valueColors  Colors for cells holding these exact values.
     */
    protected function dataRow(array $values, int $rowIndex, array $valueColors = []): Row
    {
        $style = $this->baseStyle()->withBorder($this->cellBorder());

        if ($rowIndex % 2 === 1) {
            $style = $style->withBackgroundColor(self::STRIPE_COLOR);
        }

        return new Row(array_map(function (string|int|float|null $value) use ($style, $valueColors): Cell {
            $cellStyle = is_numeric($value) && ! is_string($value) || $value === '-'
                ? $style->withCellAlignment(CellAlignment::CENTER)
                : $style;

            if (is_string($value) && isset($valueColors[$value])) {
                $cellStyle = $cellStyle
                    ->withBackgroundColor($valueColors[$value]['background'])
                    ->withFontColor($valueColors[$value]['font'])
                    ->withCellAlignment(CellAlignment::CENTER);
            }

            return $this->textCell($value, $cellStyle);
        }, array_values($values)));
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

    private function textCell(string|int|float|null $value, ?Style $style): Cell
    {
        return is_string($value) && $value !== ''
            ? new Cell\StringCell($value, $style)
            : Cell::fromValue($value, $style);
    }

    private function baseStyle(): Style
    {
        return (new Style(fontName: 'Calibri'))->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    private function cellBorder(): Border
    {
        return new Border(...array_map(
            fn (BorderName $name): BorderPart => new BorderPart($name, self::BORDER_COLOR, BorderWidth::THIN),
            BorderName::cases(),
        ));
    }
}
