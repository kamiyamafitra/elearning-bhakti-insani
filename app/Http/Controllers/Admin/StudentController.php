<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ChoosesPageSize;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\ClassMembership;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    use ChoosesPageSize;

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $classId = $request->integer('class_id') ?: null;
        $perPage = $this->perPage($request);

        $students = StudentProfile::with([
            'user:id,name,email',
            'class:id,name',
        ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%");
                });
            })
            ->when($classId, fn ($query) => $query->where('class_id', $classId))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $classes = Classes::with('academicYear:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year_id']);

        $totalStudents = StudentProfile::count();
        $studentsWithClass = StudentProfile::whereNotNull('class_id')->count();
        $classesWithStudents = Classes::whereHas('students')->count();

        return view('admin.students.index', ['perPageOptions' => self::PER_PAGE_OPTIONS, 'defaultPerPage' => self::DEFAULT_PER_PAGE] + compact(
            'students',
            'search',
            'classes',
            'classId',
            'perPage',
            'totalStudents',
            'studentsWithClass',
            'classesWithStudents',
        ));
    }

    public function create(): View
    {
        $classes = Classes::orderBy('name')->get();

        return view('admin.students.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', ...PasswordRules::rules(), 'confirmed'],
            'nisn' => ['nullable', 'string', 'max:20', 'unique:student_profiles,nisn'],
            'nis' => ['nullable', 'string', 'max:20', 'unique:student_profiles,nis'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
        ], PasswordRules::messages());

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'student',
                'is_active' => true,
            ]);

            StudentProfile::create([
                'user_id' => $user->id,
                'nisn' => $validated['nisn'] ?? null,
                'nis' => $validated['nis'] ?? null,
                'class_id' => $validated['class_id'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
            ]);
        });

        return redirect()->route('admin.students.index')->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function show(StudentProfile $student): View
    {
        $student->load(['user', 'class']);

        return view('admin.students.show', compact('student'));
    }

    public function edit(StudentProfile $student): View
    {
        $student->load(['user']);
        $classes = Classes::orderBy('name')->get();

        return view('admin.students.edit', compact('student', 'classes'));
    }

    public function update(Request $request, StudentProfile $student): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->user_id)],
            'password' => ['nullable', ...PasswordRules::rules(), 'confirmed'],
            'nisn' => ['nullable', 'string', 'max:20', Rule::unique('student_profiles', 'nisn')->ignore($student->id)],
            'nis' => ['nullable', 'string', 'max:20', Rule::unique('student_profiles', 'nis')->ignore($student->id)],
            'class_id' => ['nullable', 'exists:classes,id'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
        ], PasswordRules::messages());

        DB::transaction(function () use ($validated, $student) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];
            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            $student->user->update($userData);

            $student->update([
                'nisn' => $validated['nisn'] ?? null,
                'nis' => $validated['nis'] ?? null,
                'class_id' => $validated['class_id'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
            ]);
        });

        return redirect()->route('admin.students.index')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(StudentProfile $student): RedirectResponse
    {
        // The database removes the student's submissions with the account, but not the uploaded files, so delete those too.
        $submissionFiles = AssignmentSubmission::where('student_id', $student->user_id)->whereNotNull('file_path')->pluck('file_path')->all();

        DB::transaction(function () use ($student) {
            $user = $student->user;
            ClassMembership::where('student_id', $student->user_id)->delete();
            $student->delete();
            if ($user) {
                $user->delete();
            }
        });

        Storage::disk('local')->delete($submissionFiles);

        return redirect()->route('admin.students.index')->with('success', 'Data siswa berhasil dihapus.');
    }
}
