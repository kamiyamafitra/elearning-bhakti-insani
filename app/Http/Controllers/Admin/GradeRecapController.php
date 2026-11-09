<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\WritesSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Support\GradeRecap;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeRecapController extends Controller
{
    use WritesSpreadsheetRows;

    public function index(Request $request): View
    {
        $classes = Classes::query()
            ->join('academic_years', 'academic_years.id', '=', 'classes.academic_year_id')
            ->select('classes.*')
            ->with('academicYear:id,name,is_active')
            ->orderByDesc('academic_years.start_date')
            ->orderBy('classes.name')
            ->get();

        $class = $request->filled('class_id')
            ? $classes->firstWhere('id', $request->integer('class_id'))
            : null;

        if ($class === null) {
            return view('admin.grades.index', ['classes' => $classes, 'class' => null]);
        }

        $recap = GradeRecap::forClass($class);
        $subject = $request->filled('subject_id')
            ? $recap['subjects']->first(fn (array $subjectRecap) => $subjectRecap['subject']->id === $request->integer('subject_id'))
            : null;

        return view('admin.grades.index', [
            'classes' => $classes,
            'class' => $class,
            'students' => $recap['students'],
            'subjects' => $recap['subjects'],
            'selectedSubject' => $subject,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']]);

        $class = Classes::with('academicYear:id,name')->findOrFail($request->integer('class_id'));
        $recap = GradeRecap::forClass($class);

        $path = tempnam(sys_get_temp_dir(), 'grade-recap');
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Ringkasan');
        $summaryHeader = ['No', 'NIS', 'Nama Siswa', ...$recap['subjects']->map(fn (array $subjectRecap) => $subjectRecap['subject']->name), 'Rata-rata', 'Tidak Mengumpulkan'];
        $this->layoutSheet($writer->getCurrentSheet(), $this->columnWidths(count($summaryHeader)), 2);
        $this->addTitleRow($writer, "Rekap Nilai {$class->name} - Tahun ajaran ".($class->academicYear?->name ?? '-'), count($summaryHeader));
        $writer->addRow($this->headerRow($summaryHeader));

        foreach ($recap['students'] as $index => $student) {
            $writer->addRow($this->dataRow([
                $index + 1,
                $student['nis'] ?? '',
                $student['name'],
                ...$recap['subjects']->map(fn (array $subjectRecap) => $subjectRecap['students'][$student['id']]['average'] ?? '-'),
                $student['average'] ?? '-',
                $student['missing'],
            ], $index));
        }

        $this->addFilter($writer, 2, count($summaryHeader));

        // Report card grades: one column per subject with the weighted final grade and its letter.
        $reportHeader = ['No', 'NIS', 'Nama Siswa', ...$recap['subjects']->map(fn (array $subjectRecap) => $subjectRecap['subject']->name), 'Mapel Belum Tuntas'];
        $reportSheet = $writer->addNewSheetAndMakeItCurrent()->setName('Nilai Rapor');
        $this->layoutSheet($reportSheet, $this->columnWidths(count($reportHeader)), 2);
        $this->addTitleRow($writer, "Nilai Rapor {$class->name} - Tahun ajaran ".($class->academicYear?->name ?? '-'), count($reportHeader));
        $writer->addRow($this->headerRow($reportHeader));

        foreach ($recap['students'] as $index => $student) {
            $reports = $recap['subjects']->map(fn (array $subjectRecap) => $subjectRecap['students'][$student['id']]['report']);

            $writer->addRow($this->dataRow([
                $index + 1,
                $student['nis'] ?? '',
                $student['name'],
                ...$reports->map(fn (array $report) => $report['final'] !== null ? number_format($report['final'], 1, ',', '.')." ({$report['predicate']})" : '-'),
                $reports->whereStrict('passed', false)->count(),
            ], $index, self::GRADE_COLORS));
        }

        $this->addFilter($writer, 2, count($reportHeader));

        $usedSheetNames = ['ringkasan' => true, 'nilai rapor' => true];

        foreach ($recap['subjects'] as $subjectRecap) {
            $subjectHeader = [
                'No', 'NIS', 'Nama Siswa',
                ...$subjectRecap['assignments']->map(fn (object $column) => "{$column->title} (maks {$column->max_score})"),
                'Rata-rata', 'Tidak Mengumpulkan', 'Nilai Rapor', 'Predikat', 'Keterangan',
            ];
            $sheet = $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName($subjectRecap['subject']->name, $usedSheetNames));
            $this->layoutSheet($sheet, $this->columnWidths(count($subjectHeader)), 2);
            $this->addTitleRow($writer, "{$subjectRecap['subject']->name} - {$class->name}", count($subjectHeader));
            $writer->addRow($this->headerRow($subjectHeader));

            foreach ($recap['students'] as $index => $student) {
                $studentRecap = $subjectRecap['students'][$student['id']];
                $report = $studentRecap['report'];

                $writer->addRow($this->dataRow([
                    $index + 1,
                    $student['nis'] ?? '',
                    $student['name'],
                    ...$subjectRecap['assignments']->map(fn (object $column) => $this->cellLabel($studentRecap['cells'][$column->key])),
                    $studentRecap['average'] ?? '-',
                    $studentRecap['missing'],
                    $report['final'] ?? '-',
                    $report['predicate'] ?? '-',
                    $this->passedLabel($report['passed']),
                ], $index, self::GRADE_COLORS));
            }

            $this->addFilter($writer, 2, count($subjectHeader));
        }

        $writer->close();

        return response()->download($path, 'rekap-nilai-'.Str::slug($class->name).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @param  array{status: string, score: int|float|null}  $cell
     */
    private function cellLabel(array $cell): string|int|float
    {
        return match ($cell['status']) {
            'graded' => $cell['score'],
            'ungraded' => 'Belum dinilai',
            'missing' => 'Tidak mengumpulkan',
            default => '-',
        };
    }

    private function passedLabel(?bool $passed): string
    {
        return match ($passed) {
            true => 'Tuntas',
            false => 'Belum tuntas',
            null => '-',
        };
    }

    /**
     * @return array<int, int>
     */
    private function columnWidths(int $columnCount): array
    {
        return [6, 14, 32, ...array_fill(0, $columnCount - 3, 18)];
    }

    /**
     * Excel sheet names are limited to 31 characters, must be unique, and cannot contain \ / ? * : [ ].
     *
     * @param  array<string, bool>  $usedSheetNames
     */
    private function sheetName(string $subjectName, array &$usedSheetNames): string
    {
        $base = Str::limit(trim(preg_replace('/[\\\\\/?*:\[\]]/', ' ', $subjectName)), 28, '') ?: 'Mapel';
        $name = $base;
        $counter = 2;

        while (isset($usedSheetNames[Str::lower($name)])) {
            $name = "{$base} {$counter}";
            $counter++;
        }

        $usedSheetNames[Str::lower($name)] = true;

        return $name;
    }
}
