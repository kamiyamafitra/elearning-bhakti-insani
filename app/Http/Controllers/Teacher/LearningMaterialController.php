<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresLmsFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLearningMaterialRequest;
use App\Http\Requests\UpdateLearningMaterialRequest;
use App\Models\Classes;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningMaterialController extends Controller
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

        return view('teacher.materials.create', [
            'subject' => $subject,
            'classes' => $classes,
            'material' => new LearningMaterial(['class_id' => $selectedClassId]),
        ]);
    }

    public function store(StoreLearningMaterialRequest $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validated();
        $classId = (int) $validated['class_id'];

        $this->authorize('create', [LearningMaterial::class, $subject, $classId]);

        $storedFile = $request->hasFile('file')
            ? $this->storeLmsFile($request->file('file'), 'lms/materials')
            : null;

        $isPublished = $request->boolean('is_published');
        $publishedAt = $isPublished
            ? Carbon::parse($validated['published_at'] ?? now())
            : null;

        $attributes = [
            'subject_id' => $subject->id,
            'class_id' => $classId,
            'teacher_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'external_url' => $validated['external_url'] ?? null,
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ];

        if ($storedFile !== null) {
            $attributes = array_merge($attributes, [
                'file_path' => $storedFile['path'],
                'file_original_name' => $storedFile['name'],
                'file_mime' => $storedFile['mime'],
                'file_size' => $storedFile['size'],
            ]);
        }

        try {
            DB::transaction(fn () => LearningMaterial::create($attributes));
        } catch (\Throwable $exception) {
            if ($storedFile !== null) {
                $this->deleteLmsFile($storedFile['path']);
            }

            throw $exception;
        }

        return $this->redirectToMaterials($subject, 'Materi pelajaran berhasil ditambahkan.');
    }

    public function edit(Request $request, LearningMaterial $material): View
    {
        $this->authorize('update', $material);

        $material->load(['subject', 'class']);

        return view('teacher.materials.edit', [
            'material' => $material,
            'subject' => $material->subject,
            'classes' => $this->teacherClasses((int) $request->user()->id, (int) $material->subject_id),
        ]);
    }

    public function update(UpdateLearningMaterialRequest $request, LearningMaterial $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $validated = $request->validated();
        $classId = (int) $validated['class_id'];

        $this->authorize('create', [LearningMaterial::class, $material->subject, $classId]);

        $oldFilePath = $material->file_path;
        $newFile = $request->hasFile('file')
            ? $this->storeLmsFile($request->file('file'), 'lms/materials')
            : null;
        $removeFile = $request->boolean('remove_file') && $newFile === null;

        $isPublished = $request->boolean('is_published');
        $publishedAt = null;

        if ($isPublished) {
            $publishedAt = $validated['published_at'] ?? null;
            $publishedAt = $publishedAt !== null
                ? Carbon::parse($publishedAt)
                : ($material->published_at ?? now());
        }

        $attributes = [
            'class_id' => $classId,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'external_url' => $validated['external_url'] ?? null,
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ];

        if ($newFile !== null) {
            $attributes = array_merge($attributes, [
                'file_path' => $newFile['path'],
                'file_original_name' => $newFile['name'],
                'file_mime' => $newFile['mime'],
                'file_size' => $newFile['size'],
            ]);
        } elseif ($removeFile) {
            $attributes = array_merge($attributes, [
                'file_path' => null,
                'file_original_name' => null,
                'file_mime' => null,
                'file_size' => null,
            ]);
        }

        try {
            DB::transaction(fn () => $material->update($attributes));
        } catch (\Throwable $exception) {
            if ($newFile !== null) {
                $this->deleteLmsFile($newFile['path']);
            }

            throw $exception;
        }

        if (($newFile !== null || $removeFile) && filled($oldFilePath)) {
            $this->deleteLmsFile($oldFilePath);
        }

        return $this->redirectToMaterials($material->subject, 'Materi pelajaran berhasil diperbarui.');
    }

    public function destroy(LearningMaterial $material): RedirectResponse
    {
        $this->authorize('delete', $material);

        $subject = $material->subject;
        $filePath = $material->file_path;

        DB::transaction(fn () => $material->delete());
        $this->deleteLmsFile($filePath);

        return $this->redirectToMaterials($subject, 'Materi pelajaran berhasil dihapus.');
    }

    public function download(LearningMaterial $material): StreamedResponse
    {
        $this->authorize('download', $material);

        abort_unless(
            $material->hasFile() && Storage::disk('local')->exists($material->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $material->file_path,
            $material->file_original_name ?: basename($material->file_path),
        );
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

    private function redirectToMaterials(Subject $subject, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('teacher.subjects.show', ['subject' => $subject, 'tab' => 'materials']).'#materi')
            ->with('success', $message);
    }
}
