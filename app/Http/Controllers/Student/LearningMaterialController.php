<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LearningMaterialController extends Controller
{
    public function show(Request $request, Subject $subject, LearningMaterial $material): View
    {
        $this->ensureMaterialIsAccessible($request, $subject, $material);

        $this->authorize('view', $material);

        $material->load([
            'subject:id,code,name',
            'class:id,name,code',
            'teacher:id,name',
        ]);

        return view('student.materials.show', [
            'subject' => $subject,
            'material' => $material,
        ]);
    }

    public function download(Request $request, Subject $subject, LearningMaterial $material): Response
    {
        $this->ensureMaterialIsAccessible($request, $subject, $material);

        $this->authorize('download', $material);

        abort_unless(
            $material->hasFile() && Storage::disk('local')->exists($material->file_path),
            404,
        );

        return $this->uploadedFileResponse($request, $material->file_path, $material->file_original_name);
    }

    private function ensureMaterialIsAccessible(Request $request, Subject $subject, LearningMaterial $material): void
    {
        $user = $request->user();
        $classId = $user->studentClassId();

        abort_unless(
            $classId !== null
                && Subject::query()->forClass($classId)->whereKey($subject->id)->exists(),
            404,
        );

        abort_unless((int) $material->subject_id === (int) $subject->id, 404);
    }
}
