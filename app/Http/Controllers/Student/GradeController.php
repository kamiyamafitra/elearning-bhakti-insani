<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\GradeRecap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.class.academicYear');
        $classroom = $user->studentProfile?->class;

        // Only the student's own results; subjects with nothing assessed yet are left out.
        $subjects = $classroom === null
            ? collect()
            : GradeRecap::forClass($classroom, onlyStudentIds: [$user->id])['subjects']
                ->map(fn (array $subjectRecap) => [
                    'subject' => $subjectRecap['subject'],
                    'setting' => $subjectRecap['setting'],
                    'columns' => $subjectRecap['assignments'],
                    'result' => $subjectRecap['students'][$user->id] ?? null,
                ])
                ->filter(fn (array $subjectRecap) => $subjectRecap['result'] !== null && $subjectRecap['columns']->isNotEmpty())
                ->values();

        return view('student.grades.index', [
            'student' => $user->studentProfile,
            'classroom' => $classroom,
            'subjects' => $subjects,
        ]);
    }
}
