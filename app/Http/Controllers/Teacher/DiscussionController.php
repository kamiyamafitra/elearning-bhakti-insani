<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscussionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $subjects = Subject::query()
            ->forTeacher((int) $user->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('teacher.discussions.index', [
            'teacher' => $user->teacherProfile,
            'subjects' => $subjects,
        ]);
    }
}
