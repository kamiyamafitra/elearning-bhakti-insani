<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => SchoolSetting::current(),
            'semesterLabels' => SchoolSetting::SEMESTER_LABELS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'semester' => ['required', Rule::in(array_keys(SchoolSetting::SEMESTER_LABELS))],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.mimes' => 'Logo harus berformat PNG, JPG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
        ], [
            'name' => 'nama sekolah',
            'city' => 'kota',
            'address' => 'alamat',
            'phone' => 'telepon',
        ]);

        $settings = SchoolSetting::current();
        $previousLogoPath = $settings->logo_path;
        $settings->fill(Arr::except($validated, ['logo', 'remove_logo']));

        if ($request->hasFile('logo')) {
            $settings->logo_path = $request->file('logo')->store('school', 'local');
        } elseif ($request->boolean('remove_logo')) {
            $settings->logo_path = null;
        }

        $settings->save();

        if ($previousLogoPath && $previousLogoPath !== $settings->logo_path) {
            Storage::disk('local')->delete($previousLogoPath);
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Pengaturan sekolah berhasil disimpan.');
    }

    /**
     * Serve the logo from private storage, because the login page needs it before anyone is signed in.
     */
    public function logo(): BinaryFileResponse
    {
        $logoPath = SchoolSetting::current()->logo_path;

        abort_unless($logoPath && Storage::disk('local')->exists($logoPath), 404);

        return response()->file(Storage::disk('local')->path($logoPath), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
