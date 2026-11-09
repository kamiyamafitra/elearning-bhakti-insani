<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\StoresLmsFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AssignmentController extends Controller
{
    use StoresLmsFiles;

    public function show(Request $request, Subject $subject, Assignment $assignment): View
    {
        $this->ensureAssignmentIsAccessible($request, $subject, $assignment);

        $this->authorize('view', $assignment);

        $assignment->load([
            'subject:id,code,name',
            'class:id,name,code',
            'teacher:id,name',
        ]);

        $submission = $assignment->submissions()
            ->where('student_id', $request->user()->id)
            ->first();

        if ($submission !== null) {
            $submission->setRelation('assignment', $assignment);
        }

        return view('student.assignments.show', [
            'subject' => $subject,
            'assignment' => $assignment,
            'submission' => $submission,
        ]);
    }

    public function submit(SubmitAssignmentRequest $request, Subject $subject, Assignment $assignment): RedirectResponse
    {
        $this->ensureAssignmentIsAccessible($request, $subject, $assignment);

        $this->authorize('submit', $assignment);

        $validated = $request->validated();
        $user = $request->user();

        $textResponse = filled($validated['text_response'] ?? null)
            ? $validated['text_response']
            : null;

        if (! $assignment->allows_text_response && $textResponse !== null) {
            return back()
                ->withErrors(['text_response' => 'Tugas ini tidak menerima jawaban teks.'])
                ->withInput();
        }

        $existing = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $user->id)
            ->first();

        if ($existing !== null && ! $assignment->allows_resubmission) {
            return back()
                ->with('error', 'Tugas ini tidak mengizinkan pengumpulan ulang.')
                ->withInput();
        }

        if ($existing !== null && $existing->isGraded()) {
            return back()
                ->with('error', 'Tugas yang sudah dinilai tidak dapat dikumpulkan ulang.')
                ->withInput();
        }

        $hasExistingContent = $existing !== null
            && ($existing->hasFile() || filled($existing->text_response));

        if (! $request->hasFile('file') && $textResponse === null && ! $hasExistingContent) {
            return back()
                ->withErrors(['file' => 'Unggah berkas atau isi jawaban teks sebelum mengumpulkan tugas.'])
                ->withInput();
        }

        $oldFilePath = $existing?->file_path;
        $storedFile = $request->hasFile('file')
            ? $this->storeLmsFile($request->file('file'), 'lms/submissions')
            : null;

        try {
            DB::transaction(function () use ($assignment, $existing, $user, $storedFile, $textResponse) {
                $attributes = [
                    'text_response' => $textResponse,
                    'submitted_at' => now(),
                    'score' => null,
                    'feedback' => null,
                    'graded_at' => null,
                    'graded_by' => null,
                ];

                if ($storedFile !== null) {
                    $attributes = array_merge($attributes, [
                        'file_path' => $storedFile['path'],
                        'file_original_name' => $storedFile['name'],
                        'file_mime' => $storedFile['mime'],
                        'file_size' => $storedFile['size'],
                    ]);
                }

                if ($existing !== null) {
                    $existing->update($attributes);

                    return;
                }

                AssignmentSubmission::create(array_merge($attributes, [
                    'assignment_id' => $assignment->id,
                    'student_id' => $user->id,
                ]));
            });
        } catch (\Throwable $exception) {
            if ($storedFile !== null) {
                $this->deleteLmsFile($storedFile['path']);
            }

            throw $exception;
        }

        if ($storedFile !== null && filled($oldFilePath)) {
            $this->deleteLmsFile($oldFilePath);
        }

        return redirect()
            ->route('student.assignments.show', [$subject, $assignment])
            ->with('success', 'Tugas berhasil dikumpulkan.');
    }

    public function downloadAttachment(Request $request, Subject $subject, Assignment $assignment): Response
    {
        $this->ensureAssignmentIsAccessible($request, $subject, $assignment);

        $this->authorize('downloadAttachment', $assignment);

        abort_unless(
            $assignment->hasAttachment() && Storage::disk('local')->exists($assignment->attachment_path),
            404,
        );

        return $this->uploadedFileResponse($request, $assignment->attachment_path, $assignment->attachment_original_name);
    }

    public function downloadSubmission(Request $request, Subject $subject, Assignment $assignment): Response
    {
        $this->ensureAssignmentIsAccessible($request, $subject, $assignment);

        $submission = $assignment->submissions()
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('downloadSubmission', [$assignment, $submission]);

        abort_unless(
            $submission->hasFile() && Storage::disk('local')->exists($submission->file_path),
            404,
        );

        return $this->uploadedFileResponse($request, $submission->file_path, $submission->file_original_name);
    }

    private function ensureAssignmentIsAccessible(Request $request, Subject $subject, Assignment $assignment): void
    {
        $user = $request->user();
        $classId = $user->studentClassId();

        abort_unless(
            $classId !== null
                && Subject::query()->forClass($classId)->whereKey($subject->id)->exists(),
            404,
        );

        abort_unless((int) $assignment->subject_id === (int) $subject->id, 404);
    }
}
