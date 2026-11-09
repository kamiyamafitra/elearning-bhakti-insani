<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\WritesSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeacherReportController extends Controller
{
    use WritesSpreadsheetRows;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $teachers = $this->buildReport($filters);

        return view('admin.reports.teachers', [
            'filters' => $filters,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'is_active']),
            'teachers' => $teachers,
            'totals' => [
                'teachers' => $teachers->count(),
                'inactiveTeachers' => $teachers->where('materials', 0)->where('assignments', 0)->count(),
                'materials' => $teachers->sum('materials'),
                'assignments' => $teachers->sum('assignments'),
                'submissions' => $teachers->sum('submissions'),
                'graded' => $teachers->sum('graded'),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $teachers = $this->buildReport($filters);

        $path = tempnam(sys_get_temp_dir(), 'teacher-report');
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Ringkasan');
        $this->layoutSheet($writer->getCurrentSheet(), [30, 22, 12, 12, 12, 12, 14, 12, 20], 3);
        $this->addTitleRow($writer, 'Laporan Aktivitas Guru - '.$this->periodLabel($filters), 9);
        $this->addNoteRow($writer, 'Diunduh pada '.now()->format('d-m-Y H:i'), 9);
        $writer->addRow($this->headerRow(['Guru', 'NIP', 'Kelas & Mapel', 'Materi', 'Materi Terbit', 'Tugas', 'Pengumpulan', 'Sudah Dinilai', 'Aktivitas Terakhir']));

        foreach ($teachers->values() as $index => $teacher) {
            $writer->addRow($this->dataRow([
                $teacher['name'],
                $teacher['nip'] ?? '',
                $teacher['rows']->count(),
                $teacher['materials'],
                $teacher['published_materials'],
                $teacher['assignments'],
                $teacher['submissions'],
                $teacher['graded'],
                $teacher['last_activity']?->format('d-m-Y H:i') ?? '-',
            ], $index));
        }

        $this->addFilter($writer, 3, 9);

        $sheet = $writer->addNewSheetAndMakeItCurrent()->setName('Rincian');
        $this->layoutSheet($sheet, [30, 22, 16, 26, 12, 12, 12, 14, 12, 20], 1);
        $writer->addRow($this->headerRow(['Guru', 'NIP', 'Kelas', 'Mata Pelajaran', 'Materi', 'Materi Terbit', 'Tugas', 'Pengumpulan', 'Sudah Dinilai', 'Aktivitas Terakhir']));
        $detailRowIndex = 0;

        foreach ($teachers as $teacher) {
            foreach ($teacher['rows'] as $row) {
                $writer->addRow($this->dataRow([
                    $teacher['name'],
                    $teacher['nip'] ?? '',
                    $row['class'],
                    $row['subject'],
                    $row['materials'],
                    $row['published_materials'],
                    $row['assignments'],
                    $row['submissions'],
                    $row['graded'],
                    $row['last_activity']?->format('d-m-Y H:i') ?? '-',
                ], $detailRowIndex++));
            }
        }

        $this->addFilter($writer, 1, 10);

        $writer->close();

        $fileName = 'laporan-aktivitas-guru-'.Str::slug($filters['academicYear']?->name ?? 'semua').'.xlsx';

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @return array{academicYear: ?AcademicYear, from: ?CarbonImmutable, to: ?CarbonImmutable, search: string}
     */
    private function filters(Request $request): array
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $academicYear = $request->filled('academic_year_id')
            ? AcademicYear::find($request->integer('academic_year_id'))
            : AcademicYear::where('is_active', true)->first();

        return [
            'academicYear' => $academicYear,
            'from' => $request->filled('from') ? CarbonImmutable::parse($request->input('from'))->startOfDay() : null,
            'to' => $request->filled('to') ? CarbonImmutable::parse($request->input('to'))->endOfDay() : null,
            'search' => trim((string) $request->input('q', '')),
        ];
    }

    private function periodLabel(array $filters): string
    {
        $label = 'Tahun ajaran '.($filters['academicYear']?->name ?? 'semua');

        if ($filters['from'] || $filters['to']) {
            $label .= ', '.($filters['from']?->format('d-m-Y') ?? 'awal').' s.d. '.($filters['to']?->format('d-m-Y') ?? 'sekarang');
        }

        return $label;
    }

    /**
     * Combine teaching assignments with material, assignment, and submission counts per teacher, class, and subject.
     *
     * @param  array{academicYear: ?AcademicYear, from: ?CarbonImmutable, to: ?CarbonImmutable, search: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildReport(array $filters): Collection
    {
        $classIds = Classes::query()
            ->when($filters['academicYear'], fn ($query, AcademicYear $year) => $query->where('academic_year_id', $year->id))
            ->pluck('id');

        $inPeriod = function (Builder $query, string $column) use ($filters): Builder {
            return $query
                ->when($filters['from'], fn ($query, $from) => $query->where($column, '>=', $from))
                ->when($filters['to'], fn ($query, $to) => $query->where($column, '<=', $to));
        };

        $materials = $inPeriod(LearningMaterial::query(), 'created_at')
            ->whereIn('class_id', $classIds)
            ->groupBy('teacher_id', 'class_id', 'subject_id')
            ->selectRaw('teacher_id, class_id, subject_id, COUNT(*) as total, SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published, MAX(created_at) as last_at')
            ->toBase()
            ->get();

        $assignments = $inPeriod(Assignment::query(), 'created_at')
            ->whereIn('class_id', $classIds)
            ->groupBy('teacher_id', 'class_id', 'subject_id')
            ->selectRaw('teacher_id, class_id, subject_id, COUNT(*) as total, MAX(created_at) as last_at')
            ->toBase()
            ->get();

        $submissions = $inPeriod(AssignmentSubmission::query(), 'assignment_submissions.submitted_at')
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->whereIn('assignments.class_id', $classIds)
            ->groupBy('assignments.teacher_id', 'assignments.class_id', 'assignments.subject_id')
            ->selectRaw('assignments.teacher_id, assignments.class_id, assignments.subject_id, COUNT(*) as total, SUM(CASE WHEN assignment_submissions.score IS NOT NULL THEN 1 ELSE 0 END) as graded')
            ->toBase()
            ->get();

        $teachingAssignments = TeachingAssignment::query()
            ->whereIn('class_id', $classIds)
            ->get(['teacher_id', 'class_id', 'subject_id']);

        $rows = [];
        $emptyRow = ['materials' => 0, 'published_materials' => 0, 'assignments' => 0, 'submissions' => 0, 'graded' => 0, 'last_activity' => null];
        $rowFor = function (object $record) use (&$rows, $emptyRow): array {
            $key = "{$record->teacher_id}-{$record->class_id}-{$record->subject_id}";
            $rows[$key] ??= ['teacher_id' => (int) $record->teacher_id, 'class_id' => (int) $record->class_id, 'subject_id' => (int) $record->subject_id] + $emptyRow;

            return [$key, $rows[$key]];
        };
        $latest = fn (?CarbonImmutable $current, ?string $value) => $value === null ? $current : max($current, CarbonImmutable::parse($value));

        foreach ($teachingAssignments as $teachingAssignment) {
            $rowFor($teachingAssignment);
        }

        foreach ($materials as $record) {
            [$key] = $rowFor($record);
            $rows[$key]['materials'] += (int) $record->total;
            $rows[$key]['published_materials'] += (int) $record->published;
            $rows[$key]['last_activity'] = $latest($rows[$key]['last_activity'], $record->last_at);
        }

        foreach ($assignments as $record) {
            [$key] = $rowFor($record);
            $rows[$key]['assignments'] += (int) $record->total;
            $rows[$key]['last_activity'] = $latest($rows[$key]['last_activity'], $record->last_at);
        }

        foreach ($submissions as $record) {
            [$key] = $rowFor($record);
            $rows[$key]['submissions'] += (int) $record->total;
            $rows[$key]['graded'] += (int) $record->graded;
        }

        $rows = collect($rows);
        $teachers = User::with('teacherProfile:id,user_id,nip')
            ->whereIn('id', $rows->pluck('teacher_id')->unique())
            ->when($filters['search'] !== '', fn ($query) => $query->where('name', 'like', "%{$filters['search']}%"))
            ->get(['id', 'name', 'is_active'])
            ->keyBy('id');
        $classNames = Classes::whereIn('id', $rows->pluck('class_id')->unique())->pluck('name', 'id');
        $subjectNames = Subject::whereIn('id', $rows->pluck('subject_id')->unique())->pluck('name', 'id');

        return $rows
            ->filter(fn (array $row) => $teachers->has($row['teacher_id']))
            ->map(fn (array $row) => $row + [
                'class' => $classNames[$row['class_id']] ?? '-',
                'subject' => $subjectNames[$row['subject_id']] ?? '-',
            ])
            ->groupBy('teacher_id')
            ->map(function (Collection $teacherRows, int $teacherId) use ($teachers): array {
                $teacher = $teachers[$teacherId];

                return [
                    'teacher' => $teacher,
                    'name' => $teacher->name,
                    'nip' => $teacher->teacherProfile?->nip,
                    'materials' => $teacherRows->sum('materials'),
                    'published_materials' => $teacherRows->sum('published_materials'),
                    'assignments' => $teacherRows->sum('assignments'),
                    'submissions' => $teacherRows->sum('submissions'),
                    'graded' => $teacherRows->sum('graded'),
                    'last_activity' => $teacherRows->pluck('last_activity')->filter()->max(),
                    'rows' => $teacherRows->sortBy(['class', 'subject'])->values(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
