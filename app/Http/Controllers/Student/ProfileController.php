<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['studentProfile.class.academicYear']);

        abort_if($user->studentProfile === null, 404);

        return view('student.profile.edit', [
            'user' => $user,
            'student' => $user->studentProfile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        abort_if($student === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($user, $student, $validated) {
            $user->update(['name' => $validated['name']]);

            $student->update([
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
            ]);
        });

        return redirect()
            ->route('student.profile.edit')
            ->with('success', 'Profil berhasil diperbarui.');
    }
}
