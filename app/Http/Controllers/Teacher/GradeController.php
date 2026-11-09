<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\WritesSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\ReportCardSetting;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Support\GradeRecap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeController extends Controller
{
    use WritesSpreadsheetRows;

    private const int UNGRADED_LIST_LIMIT = 15;

    public function index(Request $request): View
    {
        $user = $request->user();

        $subjects = Subject::query()
            ->forTeacher((int) $user->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $user->id)
            ->inActiveYear()
            ->with(['subject:id,code,name', 'class:id,name,code', 'academicYear:id,name'])
            ->get()
            ->sortBy(fn (TeachingAssignment $assignment) => [$assignment->subject?->name, $assignment->class?->name])
            ->values();

        $ungraded = AssignmentSubmission::query()
            ->whereNull('score')
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $user->id))
            ->with(['assignment:id,title,subject_id,class_id', 'assignment.subject:id,name', 'assignment.class:id,name', 'student:id,name'])
            ->oldest('submitted_at');

        return view('teacher.grades.index', [
            'teacher' => $user->teacherProfile,
            'subjects' => $subjects,
            'assignments' => $assignments,
            'ungradedCount' => (clone $ungraded)->count(),
            'ungradedSubmissions' => $ungraded->limit(self::UNGRADED_LIST_LIMIT)->get(),
        ]);
    }

    public function show(Request $request, Subject $subject, Classes $class): View
    {
        $this->ensureTeaches($request, $subject, $class);

        $class->load('academicYear:id,name');
        $recap = GradeRecap::forClass($class, onlySubjectIds: [$subject->id]);

        return view('teacher.grades.show', [
            'subject' => $subject,
            'class' => $class,
            'students' => $recap['students'],
            'subjectRecap' => $recap['subjects']->first(),
        ]);
    }

    public function updateSettings(Request $request, Subject $subject, Classes $class): RedirectResponse
    {
        $this->ensureTeaches($request, $subject, $class);

        $validated = $request->validate([
            'assignment_weight' => ['required', 'integer', 'between:0,100'],
            'passing_score' => ['required', 'integer', 'between:1,100'],
        ], [], [
            'assignment_weight' => 'bobot tugas',
            'passing_score' => 'KKM',
        ]);

        $setting = ReportCardSetting::for($subject->id, $class->id);
        $setting->fill([
            'assignment_weight' => $validated['assignment_weight'],
            'quiz_weight' => 100 - $validated['assignment_weight'],
            'passing_score' => $validated['passing_score'],
        ])->save();

        return redirect()
            ->route('teacher.grades.show', [$subject, $class])
            ->with('success', 'Pengaturan nilai rapor berhasil disimpan.');
    }

    public function export(Request $request, Subject $subject, Classes $class): BinaryFileResponse
    {
        $this->ensureTeaches($request, $subject, $class);

        $class->load('academicYear:id,name');
        $recap = GradeRecap::forClass($class, onlySubjectIds: [$subject->id]);
        $subjectRecap = $recap['subjects']->first();
        $setting = $subjectRecap['setting'];

        $path = tempnam(sys_get_temp_dir(), 'report-card');
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $header = ['No', 'NIS', 'Nama Siswa', 'Rata-rata Tugas', 'Rata-rata Kuis', 'Nilai Rapor', 'Predikat', 'Keterangan'];
        $writer->getCurrentSheet()->setName('Nilai Rapor');
        $this->layoutSheet($writer->getCurrentSheet(), [6, 14, 32, 16, 16, 14, 11, 16], 3);
        $this->addTitleRow($writer, "Nilai Rapor {$subject->name} - {$class->name} (".($class->academicYear?->name ?? '-').')', count($header));
        $this->addNoteRow($writer, "Bobot: Tugas {$setting->assignment_weight}% · Kuis {$setting->quiz_weight}% · KKM {$setting->passing_score} · Tugas/kuis yang tidak dikerjakan dihitung 0", count($header));
        $writer->addRow($this->headerRow($header));

        foreach ($recap['students'] as $index => $student) {
            $report = $subjectRecap['students'][$student['id']]['report'];

            $writer->addRow($this->dataRow([
                $index + 1,
                $student['nis'] ?? '',
                $student['name'],
                $report['assignment_average'] ?? '-',
                $report['quiz_average'] ?? '-',
                $report['final'] ?? '-',
                $report['predicate'] ?? '-',
                match ($report['passed']) {
                    true => 'Tuntas',
                    false => 'Belum tuntas',
                    null => '-',
                },
            ], $index, self::GRADE_COLORS));
        }

        $this->addFilter($writer, 3, count($header));

        $detailHeader = ['No', 'NIS', 'Nama Siswa', ...$subjectRecap['assignments']->map(fn (object $column) => "{$column->title} (maks {$column->max_score})")];
        $detailSheet = $writer->addNewSheetAndMakeItCurrent()->setName('Rincian');
        $this->layoutSheet($detailSheet, [6, 14, 32, ...array_fill(0, max(0, count($detailHeader) - 3), 18)], 2);
        $this->addTitleRow($writer, "Rincian Nilai {$subject->name} - {$class->name}", max(4, count($detailHeader)));
        $writer->addRow($this->headerRow($detailHeader));

        foreach ($recap['students'] as $index => $student) {
            $cells = $subjectRecap['students'][$student['id']]['cells'];

            $writer->addRow($this->dataRow([
                $index + 1,
                $student['nis'] ?? '',
                $student['name'],
                ...$subjectRecap['assignments']->map(fn (object $column) => match ($cells[$column->key]['status']) {
                    'graded' => $cells[$column->key]['score'],
                    'ungraded' => 'Belum dinilai',
                    'missing' => 'Tidak mengumpulkan',
                    default => '-',
                }),
            ], $index, self::GRADE_COLORS));
        }

        $this->addFilter($writer, 2, count($detailHeader));

        $writer->close();

        return response()->download($path, 'nilai-rapor-'.Str::slug($subject->name).'-'.Str::slug($class->name).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Teachers only see report cards for the subjects they teach in that class.
     */
    private function ensureTeaches(Request $request, Subject $subject, Classes $class): void
    {
        abort_unless(
            TeachingAssignment::query()
                ->where('teacher_id', $request->user()->id)
                ->where('subject_id', $subject->id)
                ->where('class_id', $class->id)
                ->exists(),
            404,
        );
    }
}
