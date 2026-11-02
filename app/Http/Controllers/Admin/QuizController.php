<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $classId = $request->integer('class_id') ?: null;

        $quizzes = Quiz::query()
            ->with(['subject:id,name', 'class:id,name', 'teacher:id,name'])
            ->withCount('questions')
            ->withCount(['attempts as submitted_count' => fn ($query) => $query->whereNotNull('submitted_at')])
            ->withAvg(['attempts as average_score' => fn ($query) => $query->whereNotNull('submitted_at')], 'score')
            ->when($classId, fn ($query) => $query->where('class_id', $classId))
            ->latest('opens_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.quizzes.index', [
            'quizzes' => $quizzes,
            'classes' => Classes::orderBy('name')->get(['id', 'name']),
            'classId' => $classId,
        ]);
    }
}
