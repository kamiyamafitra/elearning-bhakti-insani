<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $level = trim((string) $request->input('level', ''));
        $academicYearId = $request->integer('academic_year_id') ?: null;

        $classes = Classes::with('academicYear:id,name')
            ->withCount('students')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($level !== '', fn ($query) => $query->where('level', $level))
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $totalClasses = Classes::count();
        $totalStudents = StudentProfile::count();
        $classesWithStudents = Classes::whereHas('students')->count();

        $levels = Classes::query()
            ->whereNotNull('level')
            ->where('level', '<>', '')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');

        $academicYears = AcademicYear::orderByDesc('name')->get(['id', 'name']);

        return view('admin.classes.index', compact(
            'classes',
            'search',
            'level',
            'academicYearId',
            'levels',
            'academicYears',
            'totalClasses',
            'totalStudents',
            'classesWithStudents',
        ));
    }

    public function create(): View
    {
        $academicYears = AcademicYear::orderByDesc('name')->get();

        return view('admin.classes.create', compact('academicYears'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', 'unique:classes,code'],
            'level' => ['nullable', 'string', 'max:10'],
        ]);

        Classes::create($validated);

        return redirect()->route('admin.classes.index')->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function show(Classes $class): View
    {
        $class->load(['academicYear', 'students.user']);

        return view('admin.classes.show', compact('class'));
    }

    public function edit(Classes $class): View
    {
        $academicYears = AcademicYear::orderByDesc('name')->get();

        return view('admin.classes.edit', compact('class', 'academicYears'));
    }

    public function update(Request $request, Classes $class): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('classes', 'code')->ignore($class->id)],
            'level' => ['nullable', 'string', 'max:10'],
        ]);

        $class->update($validated);

        return redirect()->route('admin.classes.index')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Classes $class): RedirectResponse
    {
        if ($class->students()->exists() || $class->teachingAssignments()->exists() || $class->classMemberships()->exists()) {
            return back()->with('error', 'Kelas tidak dapat dihapus karena masih memiliki data terkait.');
        }

        $class->delete();

        return redirect()->route('admin.classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    public function assignStudents(Classes $class): View
    {
        $class->load('academicYear');

        $assignedStudents = $class->students()
            ->with('user:id,name,email')
            ->get();

        $students = StudentProfile::with(['user:id,name,email', 'class:id,name'])
            ->whereNotIn('id', $assignedStudents->pluck('id'))
            ->orderBy('id')
            ->get();

        return view('admin.classes.assign-students', compact('class', 'assignedStudents', 'students'));
    }

    public function storeAssignStudents(Request $request, Classes $class): RedirectResponse
    {
        $validated = $request->validate([
            'student_profile_ids' => ['required', 'array'],
            'student_profile_ids.*' => ['exists:student_profiles,id'],
        ]);

        DB::transaction(function () use ($validated, $class) {
            StudentProfile::query()
                ->whereIn('id', $validated['student_profile_ids'])
                ->update(['class_id' => $class->id]);
        });

        return redirect()->route('admin.classes.show', $class)->with('success', 'Siswa berhasil ditetapkan ke kelas.');
    }
}
