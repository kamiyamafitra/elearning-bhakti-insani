<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.class');
        $classId = $user->studentClassId();

        $subjects = Subject::query()
            ->when(
                $classId !== null,
                fn ($query) => $query->forClass($classId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('student.grades.index', [
            'student' => $user->studentProfile,
            'classroom' => $user->studentProfile?->class,
            'subjects' => $subjects,
        ]);
    }
}
