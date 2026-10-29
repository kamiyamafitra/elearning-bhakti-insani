<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $subjects = Subject::query()
            ->forTeacher((int) $user->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $user->id)
            ->with(['subject:id,code,name', 'class:id,name,code'])
            ->get();

        return view('teacher.grades.index', [
            'teacher' => $user->teacherProfile,
            'subjects' => $subjects,
            'assignments' => $assignments,
        ]);
    }
}
