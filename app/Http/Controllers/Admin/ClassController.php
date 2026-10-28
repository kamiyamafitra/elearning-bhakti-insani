<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('q');

        $classes = Classes::with('academicYear')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.classes.index', compact('classes', 'search'));
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
            'code' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('classes', 'code')->ignore($class->id)],
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
        $assignedIds = $class->students()->pluck('student_profiles.id')->all();
        $students = StudentProfile::with('user')
            ->whereNotIn('id', $assignedIds)
            ->orderBy('id')
            ->get();

        return view('admin.classes.assign-students', compact('class', 'students', 'assignedIds'));
    }

    public function storeAssignStudents(Request $request, Classes $class): RedirectResponse
    {
        $validated = $request->validate([
            'student_profile_ids' => ['required', 'array'],
            'student_profile_ids.*' => ['exists:student_profiles,id'],
        ]);

        foreach ($validated['student_profile_ids'] as $studentProfileId) {
            $profile = StudentProfile::find($studentProfileId);
            if ($profile && $profile->class_id !== $class->id) {
                $profile->class_id = $class->id;
                $profile->save();
            }
        }

        return redirect()->route('admin.classes.show', $class)->with('success', 'Siswa berhasil ditetapkan ke kelas.');
    }
}
