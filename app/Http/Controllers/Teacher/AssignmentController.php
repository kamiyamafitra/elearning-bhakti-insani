<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresLmsFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeSubmissionRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AssignmentController extends Controller
{
    use StoresLmsFiles;

    public function create(Request $request, Subject $subject): View
    {
        $classes = $this->teacherClasses((int) $request->user()->id, (int) $subject->id);

        abort_if($classes->isEmpty(), 404);

        $requestedClassId = (int) $request->query('class_id', 0);
        $selectedClassId = $classes->contains('id', $requestedClassId)
            ? $requestedClassId
            : (int) $classes->first()->id;

        return view('teacher.assignments.create', [
            'subject' => $subject,
            'classes' => $classes,
            'assignment' => new Assignment([
                'class_id' => $selectedClassId,
                'max_score' => 100,
                'allows_text_response' => true,
                'status' => Assignment::STATUS_DRAFT,
            ]),
        ]);
    }

    public function store(StoreAssignmentRequest $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validated();
        $classId = (int) $validated['class_id'];

        $this->authorize('create', [Assignment::class, $subject, $classId]);

        $storedFile = $request->hasFile('attachment')
            ? $this->storeLmsFile($request->file('attachment'), 'lms/assignments')
            : null;

        $status = $validated['status'];

        $attributes = [
            'subject_id' => $subject->id,
            'class_id' => $classId,
            'teacher_id' => $request->user()->id,
            'title' => $validated['title'],
            'instructions' => $validated['instructions'],
            'due_at' => Carbon::parse($validated['due_at']),
            'max_score' => (int) $validated['max_score'],
            'status' => $status,
            'allows_text_response' => $request->boolean('allows_text_response'),
            'allows_resubmission' => $request->boolean('allows_resubmission'),
            'published_at' => $status === Assignment::STATUS_PUBLISHED ? now() : null,
        ];

        if ($storedFile !== null) {
            $attributes = array_merge($attributes, [
                'attachment_path' => $storedFile['path'],
                'attachment_original_name' => $storedFile['name'],
                'attachment_mime' => $storedFile['mime'],
                'attachment_size' => $storedFile['size'],
            ]);
        }

        try {
            DB::transaction(fn () => Assignment::create($attributes));
        } catch (\Throwable $exception) {
            if ($storedFile !== null) {
                $this->deleteLmsFile($storedFile['path']);
            }

            throw $exception;
        }

        return $this->redirectToAssignments($subject, 'Tugas berhasil dibuat.');
    }

    public function show(Assignment $assignment): View
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'subject:id,code,name',
            'class:id,name,code',
            'teacher:id,name',
        ]);

        $students = $assignment->class?->students()
            ->with('user:id,name,email')
            ->get() ?? collect();

        $submissions = $assignment->submissions()
            ->with('grader:id,name')
            ->get()
            ->keyBy('student_id');

        $submissions->each(fn (AssignmentSubmission $submission) => $submission->setRelation('assignment', $assignment));

        $stats = [
            'submitted' => $submissions->count(),
            'not_submitted' => max(0, $students->count() - $submissions->count()),
            'pending' => $submissions->filter(fn (AssignmentSubmission $submission) => ! $submission->isGraded())->count(),
            'graded' => $submissions->filter(fn (AssignmentSubmission $submission) => $submission->isGraded())->count(),
            'late' => $submissions->filter(fn (AssignmentSubmission $submission) => $submission->statusKey() === AssignmentSubmission::STATUS_LATE)->count(),
        ];

        return view('teacher.assignments.show', [
            'assignment' => $assignment,
            'students' => $students,
            'submissions' => $submissions,
            'stats' => $stats,
        ]);
    }

    public function edit(Request $request, Assignment $assignment): View
    {
        $this->authorize('update', $assignment);

        $assignment->load(['subject', 'class']);

        return view('teacher.assignments.edit', [
            'assignment' => $assignment,
            'subject' => $assignment->subject,
            'classes' => $this->teacherClasses((int) $request->user()->id, (int) $assignment->subject_id),
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $validated = $request->validated();
        $classId = (int) $validated['class_id'];

        $this->authorize('create', [Assignment::class, $assignment->subject, $classId]);

        $oldAttachment = $assignment->attachment_path;
        $newAttachment = $request->hasFile('attachment')
            ? $this->storeLmsFile($request->file('attachment'), 'lms/assignments')
            : null;
        $removeAttachment = $request->boolean('remove_attachment') && $newAttachment === null;

        $status = $validated['status'];
        $publishedAt = $status === Assignment::STATUS_PUBLISHED
            ? ($assignment->published_at ?? now())
            : null;

        $attributes = [
            'class_id' => $classId,
            'title' => $validated['title'],
            'instructions' => $validated['instructions'],
            'due_at' => Carbon::parse($validated['due_at']),
            'max_score' => (int) $validated['max_score'],
            'status' => $status,
            'allows_text_response' => $request->boolean('allows_text_response'),
            'allows_resubmission' => $request->boolean('allows_resubmission'),
            'published_at' => $publishedAt,
        ];

        if ($newAttachment !== null) {
            $attributes = array_merge($attributes, [
                'attachment_path' => $newAttachment['path'],
                'attachment_original_name' => $newAttachment['name'],
                'attachment_mime' => $newAttachment['mime'],
                'attachment_size' => $newAttachment['size'],
            ]);
        } elseif ($removeAttachment) {
            $attributes = array_merge($attributes, [
                'attachment_path' => null,
                'attachment_original_name' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
            ]);
        }

        try {
            DB::transaction(fn () => $assignment->update($attributes));
        } catch (\Throwable $exception) {
            if ($newAttachment !== null) {
                $this->deleteLmsFile($newAttachment['path']);
            }

            throw $exception;
        }

        if (($newAttachment !== null || $removeAttachment) && filled($oldAttachment)) {
            $this->deleteLmsFile($oldAttachment);
        }

        return $this->redirectToAssignments($assignment->subject, 'Tugas berhasil diperbarui.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        $subject = $assignment->subject;
        $attachment = $assignment->attachment_path;
        $submissionFiles = $assignment->submissions()->pluck('file_path')->filter()->all();

        DB::transaction(fn () => $assignment->delete());

        $this->deleteLmsFile($attachment);

        foreach ($submissionFiles as $submissionFile) {
            $this->deleteLmsFile($submissionFile);
        }

        return $this->redirectToAssignments($subject, 'Tugas berhasil dihapus.');
    }

    public function downloadAttachment(Request $request, Assignment $assignment): Response
    {
        $this->authorize('downloadAttachment', $assignment);

        abort_unless(
            $assignment->hasAttachment() && Storage::disk('local')->exists($assignment->attachment_path),
            404,
        );

        return $this->uploadedFileResponse($request, $assignment->attachment_path, $assignment->attachment_original_name);
    }

    public function downloadSubmission(Request $request, Assignment $assignment, AssignmentSubmission $submission): Response
    {
        $this->authorize('downloadSubmission', [$assignment, $submission]);

        abort_unless(
            $submission->hasFile() && Storage::disk('local')->exists($submission->file_path),
            404,
        );

        return $this->uploadedFileResponse($request, $submission->file_path, $submission->file_original_name);
    }

    public function grade(GradeSubmissionRequest $request, Assignment $assignment, AssignmentSubmission $submission): RedirectResponse
    {
        $this->authorize('grade', $assignment);
        $this->authorize('viewSubmission', [$assignment, $submission]);

        $validated = $request->validated();

        DB::transaction(function () use ($submission, $request, $validated) {
            $submission->update([
                'score' => (int) $validated['score'],
                'feedback' => $validated['feedback'] ?? null,
                'graded_at' => now(),
                'graded_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('teacher.assignments.show', $assignment)
            ->with('success', 'Nilai dan umpan balik berhasil disimpan.');
    }

    /**
     * @return Collection<int, Classes>
     */
    private function teacherClasses(int $teacherId, int $subjectId): Collection
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->inActiveYear()
            ->with('class:id,name,code')
            ->get()
            ->pluck('class')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function redirectToAssignments(Subject $subject, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'assignments']).'#tugas')
            ->with('success', $message);
    }
}
