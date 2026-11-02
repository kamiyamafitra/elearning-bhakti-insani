<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\WritesSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\ClassMembership;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeRecapController extends Controller
{
    use WritesSpreadsheetRows;

    /**
     * Status text that stands out in the exported workbook.
     *
     * @var array<string, array{background: string, font: string}>
     */
    private const array EXPORT_STATUS_COLORS = [
        'Tidak mengumpulkan' => ['background' => 'FEE2E2', 'font' => 'B91C1C'],
        'Belum dinilai' => ['background' => 'FEF3C7', 'font' => '92400E'],
    ];

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

        $recap = $this->buildRecap($class);
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
        $recap = $this->buildRecap($class);

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

        $usedSheetNames = ['ringkasan' => true];

        foreach ($recap['subjects'] as $subjectRecap) {
            $subjectHeader = [
                'No', 'NIS', 'Nama Siswa',
                ...$subjectRecap['assignments']->map(fn (object $column) => "{$column->title} (maks {$column->max_score})"),
                'Rata-rata', 'Tidak Mengumpulkan',
            ];
            $sheet = $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName($subjectRecap['subject']->name, $usedSheetNames));
            $this->layoutSheet($sheet, $this->columnWidths(count($subjectHeader)), 1);
            $writer->addRow($this->headerRow($subjectHeader));

            foreach ($recap['students'] as $index => $student) {
                $studentRecap = $subjectRecap['students'][$student['id']];

                $writer->addRow($this->dataRow([
                    $index + 1,
                    $student['nis'] ?? '',
                    $student['name'],
                    ...$subjectRecap['assignments']->map(fn (object $column) => $this->cellLabel($studentRecap['cells'][$column->key])),
                    $studentRecap['average'] ?? '-',
                    $studentRecap['missing'],
                ], $index, self::EXPORT_STATUS_COLORS));
            }
        }

        $writer->close();

        return response()->download($path, 'rekap-nilai-'.Str::slug($class->name).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Collect the class's students and, per subject, every published assignment and quiz with each student's result.
     *
     * Averages are scaled to 0-100 and only include graded work; missed deadlines are counted separately.
     *
     * @return array{students: Collection<int, array{id: int, name: string, nis: ?string, average: ?float, missing: int}>, subjects: Collection<int, array<string, mixed>>}
     */
    private function buildRecap(Classes $class): array
    {
        $studentIds = StudentProfile::where('class_id', $class->id)->pluck('user_id')
            ->merge(ClassMembership::where('class_id', $class->id)->pluck('student_id'))
            ->merge(AssignmentSubmission::whereIn('assignment_id', Assignment::where('class_id', $class->id)->select('id'))->pluck('student_id'))
            ->merge(QuizAttempt::whereIn('quiz_id', Quiz::where('class_id', $class->id)->select('id'))->pluck('student_id'))
            ->unique();

        $students = User::with('studentProfile:id,user_id,nis')
            ->whereIn('id', $studentIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $assignments = Assignment::published()
            ->where('class_id', $class->id)
            ->orderBy('due_at')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'title', 'due_at', 'max_score']);

        $submissions = AssignmentSubmission::whereIn('assignment_id', $assignments->pluck('id'))
            ->get(['assignment_id', 'student_id', 'score', 'submitted_at'])
            ->keyBy(fn (AssignmentSubmission $submission) => "{$submission->assignment_id}-{$submission->student_id}");

        $quizzes = Quiz::published()
            ->where('class_id', $class->id)
            ->get(['id', 'subject_id', 'title', 'closes_at', 'status']);

        QuizAttempt::finalizeExpired($quizzes->pluck('id'));

        $attempts = QuizAttempt::whereIn('quiz_id', $quizzes->pluck('id'))
            ->get(['quiz_id', 'student_id', 'score', 'submitted_at'])
            ->keyBy(fn (QuizAttempt $attempt) => "{$attempt->quiz_id}-{$attempt->student_id}");

        $columns = $assignments
            ->map(fn (Assignment $assignment) => (object) [
                'key' => "assignment-{$assignment->id}",
                'subject_id' => $assignment->subject_id,
                'title' => $assignment->title,
                'due_at' => $assignment->due_at,
                'max_score' => $assignment->max_score,
                'source' => $assignment,
            ])
            ->toBase()
            ->concat($quizzes->map(fn (Quiz $quiz) => (object) [
                'key' => "quiz-{$quiz->id}",
                'subject_id' => $quiz->subject_id,
                'title' => "Kuis: {$quiz->title}",
                'due_at' => $quiz->closes_at,
                'max_score' => Quiz::MAX_SCORE,
                'source' => $quiz,
            ]))
            ->sortBy('due_at')
            ->values();

        $subjectIds = $columns->pluck('subject_id')
            ->merge(TeachingAssignment::where('class_id', $class->id)->pluck('subject_id'))
            ->unique();

        $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'code', 'name'])
            ->map(function (Subject $subject) use ($columns, $students, $submissions, $attempts): array {
                $subjectColumns = $columns->where('subject_id', $subject->id)->values();

                return [
                    'subject' => $subject,
                    'assignments' => $subjectColumns,
                    'students' => $students->mapWithKeys(function (User $student) use ($subjectColumns, $submissions, $attempts): array {
                        $cells = $subjectColumns->mapWithKeys(fn (object $column) => [
                            $column->key => $column->source instanceof Quiz
                                ? $this->quizResultFor($column->source, $attempts->get("{$column->source->id}-{$student->id}"))
                                : $this->resultFor($column->source, $submissions->get("{$column->source->id}-{$student->id}")),
                        ]);

                        return [$student->id => [
                            'cells' => $cells,
                            'average' => $this->averageOf($cells),
                            'missing' => $cells->where('status', 'missing')->count(),
                        ]];
                    }),
                ];
            });

        $studentSummaries = $students->map(function (User $student) use ($subjects): array {
            $allCells = $subjects->flatMap(fn (array $subjectRecap) => $subjectRecap['students'][$student->id]['cells']->values());

            return [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->studentProfile?->nis,
                'average' => $this->averageOf($allCells),
                'missing' => $allCells->where('status', 'missing')->count(),
            ];
        });

        return ['students' => $studentSummaries, 'subjects' => $subjects];
    }

    /**
     * @return array{status: string, score: ?int, max: int, percent: ?float}
     */
    private function resultFor(Assignment $assignment, ?AssignmentSubmission $submission): array
    {
        $status = match (true) {
            $submission?->score !== null => 'graded',
            $submission !== null => 'ungraded',
            $assignment->isPastDue() => 'missing',
            default => 'pending',
        };

        $maxScore = max(1, (int) $assignment->max_score);

        return [
            'status' => $status,
            'score' => $submission?->score,
            'max' => $maxScore,
            'percent' => $status === 'graded' ? $submission->score / $maxScore * 100 : null,
        ];
    }

    /**
     * @return array{status: string, score: ?float, max: int, percent: ?float}
     */
    private function quizResultFor(Quiz $quiz, ?QuizAttempt $attempt): array
    {
        $status = match (true) {
            $attempt?->submitted_at !== null => 'graded',
            $attempt === null && $quiz->hasClosed() => 'missing',
            default => 'pending',
        };

        return [
            'status' => $status,
            'score' => $status === 'graded' ? $attempt->score : null,
            'max' => Quiz::MAX_SCORE,
            'percent' => $status === 'graded' ? $attempt->score : null,
        ];
    }

    /**
     * @param  Collection<int, array{percent: ?float}>  $cells
     */
    private function averageOf(Collection $cells): ?float
    {
        $percents = $cells->pluck('percent')->filter(fn (?float $percent) => $percent !== null);

        return $percents->isEmpty() ? null : round($percents->avg(), 1);
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
