<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\ClassMembership;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReportCardSetting;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every published assignment and quiz of one class with each student's result, per subject,
 * plus the report card grade that the teacher's weights and KKM turn those results into.
 *
 * Shared by the admin recap, the teacher's report card page and the student's grades page, so all three agree.
 */
class GradeRecap
{
    /**
     * @param  iterable<int>|null  $onlyStudentIds  limit to these students (e.g. the student looking at their own grades)
     * @param  iterable<int>|null  $onlySubjectIds  limit to these subjects (e.g. the subject a teacher opened)
     * @return array{students: Collection<int, array{id: int, name: string, nis: ?string, average: ?float, missing: int}>, subjects: Collection<int, array<string, mixed>>}
     */
    public static function forClass(Classes $class, ?iterable $onlyStudentIds = null, ?iterable $onlySubjectIds = null): array
    {
        return (new self)->build($class, $onlyStudentIds, $onlySubjectIds);
    }

    /**
     * Averages are scaled to 0-100. The plain average only includes graded work and counts missed deadlines separately;
     * the report card grade counts missed work as 0. Work that is not due yet or not graded yet is left out of both.
     */
    private function build(Classes $class, ?iterable $onlyStudentIds, ?iterable $onlySubjectIds): array
    {
        $studentIds = StudentProfile::where('class_id', $class->id)->pluck('user_id')
            ->merge(ClassMembership::where('class_id', $class->id)->pluck('student_id'))
            ->merge(AssignmentSubmission::whereIn('assignment_id', Assignment::where('class_id', $class->id)->select('id'))->pluck('student_id'))
            ->merge(QuizAttempt::whereIn('quiz_id', Quiz::where('class_id', $class->id)->select('id'))->pluck('student_id'))
            ->unique();

        if ($onlyStudentIds !== null) {
            $studentIds = $studentIds->intersect(collect($onlyStudentIds));
        }

        $students = User::with('studentProfile:id,user_id,nis')
            ->whereIn('id', $studentIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $assignments = Assignment::published()
            ->where('class_id', $class->id)
            ->when($onlySubjectIds !== null, fn ($query) => $query->whereIn('subject_id', collect($onlySubjectIds)))
            ->orderBy('due_at')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'title', 'due_at', 'max_score']);

        $submissions = AssignmentSubmission::whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['assignment_id', 'student_id', 'score', 'feedback', 'submitted_at', 'graded_at'])
            ->keyBy(fn (AssignmentSubmission $submission) => "{$submission->assignment_id}-{$submission->student_id}");

        $quizzes = Quiz::published()
            ->where('class_id', $class->id)
            ->when($onlySubjectIds !== null, fn ($query) => $query->whereIn('subject_id', collect($onlySubjectIds)))
            ->get(['id', 'subject_id', 'title', 'closes_at', 'status']);

        QuizAttempt::finalizeExpired($quizzes->pluck('id'));

        $attempts = QuizAttempt::whereIn('quiz_id', $quizzes->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['quiz_id', 'student_id', 'score', 'submitted_at'])
            ->keyBy(fn (QuizAttempt $attempt) => "{$attempt->quiz_id}-{$attempt->student_id}");

        $columns = $assignments
            ->map(fn (Assignment $assignment) => (object) [
                'key' => "assignment-{$assignment->id}",
                'type' => 'assignment',
                'subject_id' => $assignment->subject_id,
                'title' => $assignment->title,
                'due_at' => $assignment->due_at,
                'max_score' => $assignment->max_score,
                'source' => $assignment,
            ])
            ->toBase()
            ->concat($quizzes->map(fn (Quiz $quiz) => (object) [
                'key' => "quiz-{$quiz->id}",
                'type' => 'quiz',
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

        if ($onlySubjectIds !== null) {
            $subjectIds = $subjectIds->intersect(collect($onlySubjectIds));
        }

        $settings = ReportCardSetting::forClass($class->id);

        $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'code', 'name'])
            ->map(function (Subject $subject) use ($class, $columns, $students, $submissions, $attempts, $settings): array {
                $subjectColumns = $columns->where('subject_id', $subject->id)->values();
                $setting = $settings->get($subject->id) ?? new ReportCardSetting(['subject_id' => $subject->id, 'class_id' => $class->id]);

                return [
                    'subject' => $subject,
                    'assignments' => $subjectColumns,
                    'setting' => $setting,
                    'students' => $students->mapWithKeys(function (User $student) use ($subjectColumns, $submissions, $attempts, $setting): array {
                        $cells = $subjectColumns->mapWithKeys(fn (object $column) => [
                            $column->key => $column->source instanceof Quiz
                                ? $this->quizResultFor($column->source, $attempts->get("{$column->source->id}-{$student->id}"))
                                : $this->resultFor($column->source, $submissions->get("{$column->source->id}-{$student->id}")),
                        ]);

                        return [$student->id => [
                            'cells' => $cells,
                            'average' => $this->averageOf($cells),
                            'missing' => $cells->where('status', 'missing')->count(),
                            'report' => $this->reportFor($subjectColumns, $cells, $setting),
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
     * @return array{status: string, score: ?int, max: int, percent: ?float, feedback: ?string, date: mixed}
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
            'feedback' => $submission?->feedback,
            'date' => $submission?->graded_at ?? $submission?->submitted_at,
        ];
    }

    /**
     * @return array{status: string, score: ?float, max: int, percent: ?float, feedback: null, date: mixed}
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
            'feedback' => null,
            'date' => $attempt?->submitted_at,
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
     * Report card grade for one student in one subject: missed work counts as 0, work not due or not graded yet is left out.
     *
     * @param  Collection<int, object>  $columns
     * @param  Collection<string, array{status: string, percent: ?float}>  $cells
     * @return array{assignment_average: ?float, quiz_average: ?float, final: ?float, predicate: ?string, passed: ?bool, ungraded: int}
     */
    private function reportFor(Collection $columns, Collection $cells, ReportCardSetting $setting): array
    {
        $categoryAverage = function (string $type) use ($columns, $cells): ?float {
            $percents = $columns->where('type', $type)
                ->map(fn (object $column) => $cells[$column->key])
                ->map(fn (array $cell) => match ($cell['status']) {
                    'graded' => $cell['percent'],
                    'missing' => 0.0,
                    default => null,
                })
                ->filter(fn (?float $percent) => $percent !== null);

            return $percents->isEmpty() ? null : round($percents->avg(), 1);
        };

        $assignmentAverage = $categoryAverage('assignment');
        $quizAverage = $categoryAverage('quiz');
        $final = $setting->finalScore($assignmentAverage, $quizAverage);

        return [
            'assignment_average' => $assignmentAverage,
            'quiz_average' => $quizAverage,
            'final' => $final,
            'predicate' => $final !== null ? $setting->predicate($final) : null,
            'passed' => $final !== null ? $final >= $setting->passing_score : null,
            'ungraded' => $cells->where('status', 'ungraded')->count(),
        ];
    }
}
