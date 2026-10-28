<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard.index', [
            'totalUsers' => User::count(),
            'totalTeachers' => TeacherProfile::count(),
            'totalStudents' => StudentProfile::count(),
            'totalClasses' => Classes::count(),
            'totalSubjects' => Subject::count(),
        ]);
    }
}
