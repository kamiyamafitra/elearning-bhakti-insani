<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));

        $academicYears = AcademicYear::withCount('classes')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString();

        $totalAcademicYears = AcademicYear::count();
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();
        $totalClasses = Classes::count();

        return view('admin.academic-years.index', compact(
            'academicYears',
            'search',
            'totalAcademicYears',
            'activeAcademicYear',
            'totalClasses',
        ));
    }

    public function create(): View
    {
        return view('admin.academic-years.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAcademicYear($request);

        DB::transaction(function () use ($validated): void {
            $academicYear = AcademicYear::create($validated);

            if ($academicYear->is_active) {
                $this->deactivateOtherYears($academicYear);
            }
        });

        return redirect()->route('admin.academic-years.index')->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function edit(AcademicYear $academicYear): View
    {
        return view('admin.academic-years.edit', compact('academicYear'));
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $validated = $this->validateAcademicYear($request, $academicYear);

        DB::transaction(function () use ($validated, $academicYear): void {
            $academicYear->update($validated);

            if ($academicYear->is_active) {
                $this->deactivateOtherYears($academicYear);
            }
        });

        return redirect()->route('admin.academic-years.index')->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($academicYear): void {
            $academicYear->update(['is_active' => true]);
            $this->deactivateOtherYears($academicYear);
        });

        return redirect()->route('admin.academic-years.index')
            ->with('success', "Tahun ajaran {$academicYear->name} sekarang aktif.");
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active) {
            return back()->with('error', 'Tahun ajaran yang sedang aktif tidak dapat dihapus.');
        }

        if ($academicYear->classes()->exists() || $academicYear->teachingAssignments()->exists() || $academicYear->classMemberships()->exists()) {
            return back()->with('error', 'Tahun ajaran tidak dapat dihapus karena masih memiliki data terkait.');
        }

        $academicYear->delete();

        return redirect()->route('admin.academic-years.index')->with('success', 'Tahun ajaran berhasil dihapus.');
    }

    /**
     * Validate the academic year form, requiring a name such as "2025/2026" whose years are consecutive.
     *
     * @return array{name: string, start_date: string, end_date: string, is_active: bool}
     */
    private function validateAcademicYear(Request $request, ?AcademicYear $academicYear = null): array
    {
        $validator = validator($request->all(), [
            'name' => [
                'required',
                'string',
                'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('academic_years', 'name')->ignore($academicYear?->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.regex' => 'Format tahun ajaran harus seperti 2025/2026.',
        ]);

        $validator->after(function (Validator $validator) use ($request): void {
            if ($validator->errors()->has('name')) {
                return;
            }

            [$firstYear, $secondYear] = array_map('intval', explode('/', (string) $request->input('name')));

            if ($secondYear !== $firstYear + 1) {
                $validator->errors()->add('name', 'Tahun kedua harus satu tahun setelah tahun pertama, contoh 2025/2026.');
            }
        });

        $validated = $validator->validate();
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    /**
     * Ensure only the given academic year remains marked as active.
     */
    private function deactivateOtherYears(AcademicYear $academicYear): void
    {
        AcademicYear::query()
            ->whereKeyNot($academicYear->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
