<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Student\AnnouncementController as StudentAnnouncementController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\DiscussionController as StudentDiscussionController;
use App\Http\Controllers\Student\GradeController as StudentGradeController;
use App\Http\Controllers\Student\LearningMaterialController as StudentLearningMaterialController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Student\SubjectController as StudentSubjectController;
use App\Http\Controllers\Teacher\AnnouncementController as TeacherAnnouncementController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\DiscussionController as TeacherDiscussionController;
use App\Http\Controllers\Teacher\GradeController as TeacherGradeController;
use App\Http\Controllers\Teacher\LearningMaterialController as TeacherLearningMaterialController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Teacher\QuizController as TeacherQuizController;
use App\Http\Controllers\Teacher\SubjectController as TeacherSubjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (method_exists($user, 'isTeacher') && $user->isTeacher()) {
            return redirect()->route('teacher.dashboard');
        }

        if (method_exists($user, 'isStudent') && $user->isStudent()) {
            return redirect()->route('student.dashboard');
        }
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('users', UserController::class);
        Route::resource('students', StudentController::class);
        Route::resource('teachers', TeacherController::class);
        Route::resource('classes', ClassController::class);
        Route::get('classes/{class}/assign-students', [ClassController::class, 'assignStudents'])->name('classes.assign-students');
        Route::post('classes/{class}/assign-students', [ClassController::class, 'storeAssignStudents'])->name('classes.assign-students.store');
        Route::resource('subjects', SubjectController::class);
        Route::resource('announcements', AnnouncementController::class);
    });

    Route::middleware('role:teacher')->prefix('guru')->name('teacher.')->group(function () {
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');

        Route::get('/subjects', [TeacherSubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/{subject}', [TeacherSubjectController::class, 'show'])->name('subjects.show');

        Route::get('/subjects/{subject}/materials/create', [TeacherLearningMaterialController::class, 'create'])->name('materials.create');
        Route::post('/subjects/{subject}/materials', [TeacherLearningMaterialController::class, 'store'])->name('materials.store');
        Route::get('/materials/{material}/edit', [TeacherLearningMaterialController::class, 'edit'])->name('materials.edit');
        Route::put('/materials/{material}', [TeacherLearningMaterialController::class, 'update'])->name('materials.update');
        Route::delete('/materials/{material}', [TeacherLearningMaterialController::class, 'destroy'])->name('materials.destroy');
        Route::get('/materials/{material}/download', [TeacherLearningMaterialController::class, 'download'])->name('materials.download');

        Route::get('/subjects/{subject}/assignments/create', [TeacherAssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/subjects/{subject}/assignments', [TeacherAssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/assignments/{assignment}', [TeacherAssignmentController::class, 'show'])->name('assignments.show');
        Route::get('/assignments/{assignment}/edit', [TeacherAssignmentController::class, 'edit'])->name('assignments.edit');
        Route::put('/assignments/{assignment}', [TeacherAssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('/assignments/{assignment}', [TeacherAssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::get('/assignments/{assignment}/download', [TeacherAssignmentController::class, 'downloadAttachment'])->name('assignments.download');
        Route::get('/assignments/{assignment}/submissions/{submission}/download', [TeacherAssignmentController::class, 'downloadSubmission'])->name('submissions.download');
        Route::put('/assignments/{assignment}/submissions/{submission}/grade', [TeacherAssignmentController::class, 'grade'])->name('submissions.grade');

        Route::get('/quizzes', [TeacherQuizController::class, 'index'])->name('quizzes.index');
        Route::get('/grades', [TeacherGradeController::class, 'index'])->name('grades.index');
        Route::get('/discussions', [TeacherDiscussionController::class, 'index'])->name('discussions.index');

        Route::get('/announcements', [TeacherAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{announcement}', [TeacherAnnouncementController::class, 'show'])->name('announcements.show');

        Route::get('/profile', [TeacherProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [TeacherProfileController::class, 'update'])->name('profile.update');
    });

    Route::middleware('role:student')->prefix('siswa')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');

        Route::get('/subjects', [StudentSubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/{subject}', [StudentSubjectController::class, 'show'])->name('subjects.show');

        Route::get('/subjects/{subject}/materials/{material}', [StudentLearningMaterialController::class, 'show'])->name('materials.show');
        Route::get('/subjects/{subject}/materials/{material}/download', [StudentLearningMaterialController::class, 'download'])->name('materials.download');

        Route::get('/subjects/{subject}/assignments/{assignment}', [StudentAssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/subjects/{subject}/assignments/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->name('assignments.submit');
        Route::get('/subjects/{subject}/assignments/{assignment}/attachment', [StudentAssignmentController::class, 'downloadAttachment'])->name('assignments.download');
        Route::get('/subjects/{subject}/assignments/{assignment}/submission', [StudentAssignmentController::class, 'downloadSubmission'])->name('submissions.download');

        Route::get('/quizzes', [StudentQuizController::class, 'index'])->name('quizzes.index');
        Route::get('/grades', [StudentGradeController::class, 'index'])->name('grades.index');
        Route::get('/discussions', [StudentDiscussionController::class, 'index'])->name('discussions.index');

        Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{announcement}', [StudentAnnouncementController::class, 'show'])->name('announcements.show');

        Route::get('/profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [StudentProfileController::class, 'update'])->name('profile.update');
    });
});
