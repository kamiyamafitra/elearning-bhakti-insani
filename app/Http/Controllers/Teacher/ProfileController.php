<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('teacherProfile');

        abort_if($user->teacherProfile === null, 404);

        return view('teacher.profile.edit', [
            'user' => $user,
            'teacher' => $user->teacherProfile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $teacher = $user->teacherProfile;

        abort_if($teacher === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $teacher, $validated) {
            $user->update(['name' => $validated['name']]);

            $teacher->update([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
        });

        return redirect()
            ->route('teacher.profile.edit')
            ->with('success', 'Profil berhasil diperbarui.');
    }
}
