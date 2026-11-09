<?php

use App\Console\Commands\BackupDatabase;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AccountStatusController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\ClassCopyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GradeRecapController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Admin\SchoolSettingController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeacherImportController;
use App\Http\Controllers\Admin\TeacherReportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\PdfConverterController;
use App\Http\Controllers\Student\AnnouncementController as StudentAnnouncementController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\GradeController as StudentGradeController;
use App\Http\Controllers\Student\LearningMaterialController as StudentLearningMaterialController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Student\SubjectController as StudentSubjectController;
use App\Http\Controllers\Teacher\AnnouncementController as TeacherAnnouncementController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\GradeController as TeacherGradeController;
use App\Http\Controllers\Teacher\LearningMaterialController as TeacherLearningMaterialController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Teacher\QuizController as TeacherQuizController;
use App\Http\Controllers\Teacher\QuizQuestionController as TeacherQuizQuestionController;
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

Route::get('/school-logo', [SchoolSettingController::class, 'logo'])->name('school-logo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware(['role:admin', 'password.changed'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');

        Route::get('students/import', [StudentImportController::class, 'create'])->name('students.import');
        Route::post('students/import', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::resource('students', StudentController::class);
        Route::post('students/{student}/reset-password', [PasswordResetController::class, 'student'])->name('students.reset-password');
        Route::patch('students/{student}/toggle-active', [AccountStatusController::class, 'student'])->name('students.toggle-active');
        Route::post('teachers/{teacher}/reset-password', [PasswordResetController::class, 'teacher'])->name('teachers.reset-password');
        Route::patch('teachers/{teacher}/toggle-active', [AccountStatusController::class, 'teacher'])->name('teachers.toggle-active');
        Route::post('classes/{class}/reset-passwords', [PasswordResetController::class, 'classStudents'])->name('classes.reset-passwords');
        Route::get('teachers/import', [TeacherImportController::class, 'create'])->name('teachers.import');
        Route::post('teachers/import', [TeacherImportController::class, 'store'])->name('teachers.import.store');
        Route::get('teachers/import/template', [TeacherImportController::class, 'template'])->name('teachers.import.template');
        Route::resource('teachers', TeacherController::class);
        Route::resource('academic-years', AcademicYearController::class)->except('show');
        Route::patch('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
        Route::get('academic-years/{academic_year}/copy-classes', [ClassCopyController::class, 'create'])->name('academic-years.copy-classes');
        Route::post('academic-years/{academic_year}/copy-classes', [ClassCopyController::class, 'store'])->name('academic-years.copy-classes.store');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->where('backup', BackupDatabase::FILE_NAME_PATTERN)->name('backups.download');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->where('backup', BackupDatabase::FILE_NAME_PATTERN)->name('backups.destroy');
        Route::get('settings', [SchoolSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SchoolSettingController::class, 'update'])->name('settings.update');
        Route::get('quizzes', [AdminQuizController::class, 'index'])->name('quizzes.index');
        Route::get('grades', [GradeRecapController::class, 'index'])->name('grades.index');
        Route::get('grades/export', [GradeRecapController::class, 'export'])->name('grades.export');
        Route::get('reports/teachers', [TeacherReportController::class, 'index'])->name('reports.teachers');
        Route::get('reports/teachers/export', [TeacherReportController::class, 'export'])->name('reports.teachers.export');
        Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
        Route::resource('classes', ClassController::class);
        Route::get('classes/{class}/assign-students', [ClassController::class, 'assignStudents'])->name('classes.assign-students');
        Route::post('classes/{class}/assign-students', [ClassController::class, 'storeAssignStudents'])->name('classes.assign-students.store');
        Route::resource('subjects', SubjectController::class);
        Route::get('announcements/{announcement}/image', [AnnouncementController::class, 'image'])->name('announcements.image');
        Route::resource('announcements', AnnouncementController::class);
    });

    Route::middleware(['role:teacher', 'password.changed'])->prefix('guru')->name('teacher.')->group(function () {
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
        Route::get('/quizzes/create', [TeacherQuizController::class, 'create'])->name('quizzes.create');
        Route::post('/quizzes', [TeacherQuizController::class, 'store'])->name('quizzes.store');
        Route::get('/quizzes/{quiz}', [TeacherQuizController::class, 'show'])->name('quizzes.show');
        Route::get('/quizzes/{quiz}/edit', [TeacherQuizController::class, 'edit'])->name('quizzes.edit');
        Route::put('/quizzes/{quiz}', [TeacherQuizController::class, 'update'])->name('quizzes.update');
        Route::patch('/quizzes/{quiz}/publish', [TeacherQuizController::class, 'publish'])->name('quizzes.publish');
        Route::delete('/quizzes/{quiz}', [TeacherQuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::get('/quizzes/{quiz}/questions/create', [TeacherQuizQuestionController::class, 'create'])->name('quizzes.questions.create');
        Route::post('/quizzes/{quiz}/questions', [TeacherQuizQuestionController::class, 'store'])->name('quizzes.questions.store');
        Route::get('/quizzes/{quiz}/questions/{question}/edit', [TeacherQuizQuestionController::class, 'edit'])->name('quizzes.questions.edit');
        Route::get('/quizzes/{quiz}/questions/{question}/image', [TeacherQuizQuestionController::class, 'image'])->name('quizzes.questions.image');
        Route::put('/quizzes/{quiz}/questions/{question}', [TeacherQuizQuestionController::class, 'update'])->name('quizzes.questions.update');
        Route::delete('/quizzes/{quiz}/questions/{question}', [TeacherQuizQuestionController::class, 'destroy'])->name('quizzes.questions.destroy');
        Route::get('/grades', [TeacherGradeController::class, 'index'])->name('grades.index');
        Route::get('/grades/{subject}/{class}', [TeacherGradeController::class, 'show'])->name('grades.show');
        Route::put('/grades/{subject}/{class}', [TeacherGradeController::class, 'updateSettings'])->name('grades.settings');
        Route::get('/grades/{subject}/{class}/export', [TeacherGradeController::class, 'export'])->name('grades.export');
        require __DIR__.'/discussions.php';

        Route::get('/announcements', [TeacherAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{announcement}', [TeacherAnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/announcements/{announcement}/image', [TeacherAnnouncementController::class, 'image'])->name('announcements.image');

        Route::get('/profile', [TeacherProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [TeacherProfileController::class, 'update'])->name('profile.update');

        Route::get('/pdf-converter', [PdfConverterController::class, 'index'])->name('pdf-converter.index');
        Route::post('/pdf-converter', [PdfConverterController::class, 'store'])->middleware('throttle:10,1')->name('pdf-converter.store');
    });

    Route::middleware(['role:student', 'password.changed'])->prefix('siswa')->name('student.')->group(function () {
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
        Route::get('/quizzes/{quiz}', [StudentQuizController::class, 'show'])->name('quizzes.show');
        Route::post('/quizzes/{quiz}/start', [StudentQuizController::class, 'start'])->name('quizzes.start');
        Route::get('/quizzes/{quiz}/attempt', [StudentQuizController::class, 'attempt'])->name('quizzes.attempt');
        Route::post('/quizzes/{quiz}/answer', [StudentQuizController::class, 'saveAnswer'])->name('quizzes.answer');
        Route::post('/quizzes/{quiz}/next', [StudentQuizController::class, 'next'])->name('quizzes.next');
        Route::post('/quizzes/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('quizzes.submit');
        Route::get('/quizzes/{quiz}/questions/{question}/image', [StudentQuizController::class, 'questionImage'])->name('quizzes.questions.image');
        Route::get('/grades', [StudentGradeController::class, 'index'])->name('grades.index');
        require __DIR__.'/discussions.php';

        Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/{announcement}', [StudentAnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/announcements/{announcement}/image', [StudentAnnouncementController::class, 'image'])->name('announcements.image');

        Route::get('/profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [StudentProfileController::class, 'update'])->name('profile.update');

        Route::get('/pdf-converter', [PdfConverterController::class, 'index'])->name('pdf-converter.index');
        Route::post('/pdf-converter', [PdfConverterController::class, 'store'])->middleware('throttle:10,1')->name('pdf-converter.store');
    });
});
