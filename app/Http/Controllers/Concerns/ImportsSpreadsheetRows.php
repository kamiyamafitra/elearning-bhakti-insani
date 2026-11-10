<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Dom\HTMLDocument;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

trait ImportsSpreadsheetRows
{
    protected int $maxImportRows = 1000;

    /**
     * Bcrypt cost for initial passwords: they are the student's NIS or teacher's NIP and must be changed at first login,
     * so a lower cost loses no real security and keeps a full import within the request time limit.
     */
    private int $importHashRounds = 8;

    /**
     * Excel only keeps 15 significant digits, so a longer number in a non-text cell has already been rounded.
     */
    private float $largestSafeSpreadsheetNumber = 1e15;

    /**
     * Validate the uploaded import file and return its rows keyed by spreadsheet row number.
     *
     * Returns a redirect response instead when the file is unreadable, empty, too large, or missing required columns.
     *
     * @param  list<string>  $requiredColumns
     * @param  array<string, string>  $columnAliases
     * @param  list<string>  $identifierColumns
     * @return array<int, array<string, string>>|RedirectResponse
     */
    protected function readImportRows(Request $request, array $requiredColumns, array $columnAliases = [], array $identifierColumns = []): array|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,txt', 'mimes:xlsx,xls,html,csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Pilih file yang akan diimpor.',
            'file.extensions' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.mimes' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) === 'xls' && ! $this->isHtmlTableFile($file->getRealPath())) {
            return back()->with('error', 'File .xls versi lama tidak dapat dibaca. Buka file di Excel, pilih Simpan Sebagai, lalu simpan sebagai .xlsx.');
        }

        $identifierErrors = [];

        try {
            $rows = $this->readSpreadsheet($file, $columnAliases, $identifierColumns, $identifierErrors);
        } catch (Throwable) {
            return back()->with('error', 'File tidak dapat dibaca. Pastikan file berformat .xlsx, .xls, atau .csv yang valid.');
        }

        if ($rows === []) {
            return back()->with('error', 'File tidak berisi data. Isi data mulai dari baris kedua, di bawah judul kolom.');
        }

        if (count($rows) > $this->maxImportRows) {
            return back()->with('error', "Maksimal {$this->maxImportRows} baris per file. Bagi data menjadi beberapa file.");
        }

        $missingColumns = array_diff($requiredColumns, array_keys(reset($rows)));

        if ($missingColumns !== []) {
            return back()->with('error', 'Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'. Gunakan template yang disediakan.');
        }

        if ($identifierErrors !== []) {
            return $this->redirectWithImportErrors($identifierErrors);
        }

        return $rows;
    }

    /**
     * Read the first sheet of an .xlsx, .xls (school-system export) or .csv file, using the first row as column names.
     *
     * @param  array<string, string>  $columnAliases
     * @param  list<string>  $identifierColumns
     * @param  array<int, list<string>>  $identifierErrors
     * @return array<int, array<string, string>>
     */
    private function readSpreadsheet(UploadedFile $file, array $columnAliases, array $identifierColumns, array &$identifierErrors): array
    {
        $headers = null;
        $rows = [];

        foreach ($this->spreadsheetRows($file) as $rowNumber => $rawValues) {
            $values = array_map(fn ($value) => $this->normalizeCellValue($value), $rawValues);

            if ($headers === null) {
                $headers = array_map(fn (string $header) => $this->normalizeColumnName($header, $columnAliases), $values);

                continue;
            }

            if (implode('', $values) === '') {
                continue;
            }

            $record = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $record[$header] = $values[$index] ?? '';
                }

                $rawValue = $rawValues[$index] ?? null;

                if (in_array($header, $identifierColumns, true) && (is_int($rawValue) || is_float($rawValue)) && abs($rawValue) >= $this->largestSafeSpreadsheetNumber) {
                    $identifierErrors[$rowNumber][] = Str::upper($header).' sudah dibulatkan oleh Excel karena tersimpan sebagai angka. Ubah format selnya menjadi Text, lalu ketik ulang.';
                }
            }

            $rows[$rowNumber] = $record;
        }

        return $rows;
    }

    /**
     * Yield each row's cell values keyed by row number, from the first sheet only.
     *
     * @return Generator<int, list<mixed>>
     */
    private function spreadsheetRows(UploadedFile $file): Generator
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xls') {
            yield from $this->htmlTableRows($path);

            return;
        }

        $reader = $extension === 'xlsx'
            ? new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true))
            : new CsvReader(new CsvOptions(
                SHOULD_PRESERVE_EMPTY_ROWS: true,
                FIELD_DELIMITER: $this->detectCsvDelimiter($path),
            ));

        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    yield $rowNumber => $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * School information systems often export an HTML table saved with an .xls name, which Excel opens as a sheet.
     */
    private function isHtmlTableFile(string $path): bool
    {
        $start = (string) file_get_contents($path, length: 4096);

        return stripos($start, '<table') !== false || stripos($start, '<html') !== false || stripos($start, '<!doctype html') !== false;
    }

    /**
     * Yield the rows of the first table in an HTML export, numbered in the order the rows appear.
     *
     * @return Generator<int, list<string>>
     */
    private function htmlTableRows(string $path): Generator
    {
        $html = (string) file_get_contents($path);
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, mb_check_encoding($html, 'UTF-8') ? 'UTF-8' : 'Windows-1252');
        $table = $document->querySelector('table');

        if ($table === null) {
            return;
        }

        foreach ($table->querySelectorAll('tr') as $index => $tableRow) {
            $values = [];

            foreach ($tableRow->children as $cell) {
                if (! in_array($cell->localName, ['td', 'th'], true)) {
                    continue;
                }

                $values[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent));

                for ($extra = (int) $cell->getAttribute('colspan') - 1; $extra > 0; $extra--) {
                    $values[] = '';
                }
            }

            yield $index + 1 => $values;
        }
    }

    /**
     * Excel saves CSV files with ";" on Indonesian-locale computers, so pick whichever delimiter the header uses most.
     */
    private function detectCsvDelimiter(string $path): string
    {
        $firstLine = (string) fgets($handle = fopen($path, 'r'));
        fclose($handle);

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function normalizeCellValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, string>  $columnAliases
     */
    private function normalizeColumnName(string $header, array $columnAliases): string
    {
        $column = Str::of($header)
            ->replace("\u{FEFF}", '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return $columnAliases[$column] ?? $column;
    }

    /**
     * Convert a date written as YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, or an Excel serial number to YYYY-MM-DD, or null when unrecognised.
     */
    protected function parseImportDate(string $value): ?string
    {
        if (ctype_digit($value) && (int) $value > 0 && (int) $value < 100000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Record a value as seen and return the row where it first appeared, if it is a duplicate within the file.
     *
     * @param  array<string, int>  $seenValues
     */
    protected function findDuplicateRow(array &$seenValues, ?string $value, int $rowNumber): ?int
    {
        if (blank($value)) {
            return null;
        }

        $key = Str::lower($value);

        if (isset($seenValues[$key])) {
            return $seenValues[$key];
        }

        $seenValues[$key] = $rowNumber;

        return null;
    }

    /**
     * Find which of the given values already exist, so a whole file needs one query instead of one per row.
     *
     * @param  list<?string>  $values
     * @return array<string, true>
     */
    protected function existingValues(Builder $query, string $column, array $values): array
    {
        return $query->whereIn($column, array_filter($values))
            ->pluck($column)
            ->mapWithKeys(fn (string $value) => [Str::lower($value) => true])
            ->all();
    }

    /**
     * Hash the random temporary passwords of a whole import. A lower bcrypt cost keeps large imports fast;
     * that is acceptable because these passwords are random and must be replaced at the first sign-in.
     *
     * @param  list<string>  $passwords
     * @return list<string>
     */
    protected function hashInitialPasswords(array $passwords): array
    {
        $rounds = min($this->importHashRounds, (int) config('hashing.bcrypt.rounds'));

        return array_map(fn (string $password) => Hash::make($password, ['rounds' => $rounds]), $passwords);
    }

    /**
     * Send the admin back to the import form with every row error listed.
     *
     * @param  array<int, list<string>>  $errorsByRow
     */
    protected function redirectWithImportErrors(array $errorsByRow): RedirectResponse
    {
        $messages = [];

        foreach ($errorsByRow as $rowNumber => $rowErrors) {
            foreach ($rowErrors as $error) {
                $messages[] = "Baris {$rowNumber}: {$error}";
            }
        }

        return back()
            ->with('error', 'Impor dibatalkan. Tidak ada data yang disimpan karena '.count($errorsByRow).' baris bermasalah. Perbaiki lalu unggah ulang file.')
            ->with('import_errors', $messages);
    }

    /**
     * Build an .xlsx template whose cells are formatted as text so Excel keeps leading zeros in NIS, NISN, and NIP.
     *
     * @param  list<string>  $columns
     * @param  list<list<string>>  $exampleRows
     */
    protected function downloadImportTemplate(string $fileName, array $columns, array $exampleRows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'import-template');
        $textStyle = (new Style)->withFormat('@');

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        // Same soft indigo header as the exported reports; the header stays visible while scrolling.
        $sheet = $writer->getCurrentSheet();
        foreach (array_keys($columns) as $index) {
            $sheet->setColumnWidth(22, $index + 1);
        }
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));

        $writer->addRow(Row::fromValuesWithStyle($columns, $textStyle->withFontBold(true)->withFontColor('1E1B4B')->withBackgroundColor('E0E7FF'))->withHeight(28));

        foreach ($exampleRows as $exampleRow) {
            $writer->addRow(Row::fromValuesWithStyle($exampleRow, $textStyle));
        }

        $emptyRow = new Row(array_map(fn () => new StringCell('', $textStyle), $columns));

        for ($row = 0; $row < $this->maxImportRows; $row++) {
            $writer->addRow($emptyRow);
        }

        $writer->close();

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
