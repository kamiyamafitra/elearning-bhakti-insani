<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Classes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassCopyController extends Controller
{
    public function create(Request $request, AcademicYear $academicYear): View
    {
        $sourceYears = AcademicYear::query()
            ->whereKeyNot($academicYear->id)
            ->whereHas('classes')
            ->withCount('classes')
            ->orderByDesc('start_date')
            ->get();

        $sourceYear = $request->filled('source_academic_year_id')
            ? $sourceYears->firstWhere('id', $request->integer('source_academic_year_id'))
            : ($sourceYears->first(fn (AcademicYear $year) => $year->start_date->lt($academicYear->start_date)) ?? $sourceYears->first());

        $existingNames = $academicYear->classes()->pluck('name')->map(fn (string $name) => Str::lower($name));

        $sourceClasses = $sourceYear
            ? $sourceYear->classes()->orderBy('name')->get()->each(function (Classes $class) use ($existingNames): void {
                $class->setAttribute('already_exists', $existingNames->contains(Str::lower($class->name)));
            })
            : collect();

        return view('admin.academic-years.copy-classes', compact('academicYear', 'sourceYears', 'sourceYear', 'sourceClasses'));
    }

    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id'),
                Rule::notIn([$academicYear->id]),
            ],
        ], [
            'source_academic_year_id.required' => 'Pilih tahun ajaran sumber.',
            'source_academic_year_id.not_in' => 'Tahun ajaran sumber harus berbeda dari tahun ajaran tujuan.',
        ]);

        $sourceYear = AcademicYear::findOrFail($validated['source_academic_year_id']);
        $existingNames = $academicYear->classes()->pluck('name')->map(fn (string $name) => Str::lower($name));
        $classesToCopy = $sourceYear->classes()
            ->orderBy('name')
            ->get()
            ->reject(fn (Classes $class) => $existingNames->contains(Str::lower($class->name)));

        ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($classesToCopy, $academicYear): void {
            foreach ($classesToCopy as $class) {
                Classes::create([
                    'academic_year_id' => $academicYear->id,
                    'name' => $class->name,
                    'code' => Classes::uniqueCodeFor($class->code, $academicYear),
                    'level' => $class->level,
                ]);
            }
        }));

        ActivityLog::record(ActivityLog::ACTION_BULK, "menyalin {$classesToCopy->count()} kelas dari {$sourceYear->name} ke {$academicYear->name}", $academicYear, ['classes' => $classesToCopy->pluck('name')->values()->all()]);

        $message = $classesToCopy->isEmpty()
            ? "Semua kelas dari {$sourceYear->name} sudah ada di {$academicYear->name}."
            : "{$classesToCopy->count()} kelas berhasil disalin dari {$sourceYear->name} ke {$academicYear->name}.";

        return redirect()
            ->route('admin.classes.index', ['academic_year_id' => $academicYear->id])
            ->with('success', $message);
    }
}
