<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));

        $announcements = Announcement::with(['creator:id,name', 'class:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements', 'search'));
    }

    public function create(): View
    {
        $classes = Classes::orderBy('name')->get(['id', 'name']);

        return view('admin.announcements.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::in([
                Announcement::VISIBILITY_ALL,
                Announcement::VISIBILITY_ROLE,
                Announcement::VISIBILITY_CLASS,
            ])],
            'target_role' => ['nullable', 'required_if:visibility,role', Rule::in(['teacher', 'student'])],
            'class_id' => ['nullable', 'required_if:visibility,class', 'exists:classes,id'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'visibility' => $validated['visibility'],
            'target_role' => $validated['visibility'] === Announcement::VISIBILITY_ROLE
                ? ($validated['target_role'] ?? null)
                : null,
            'class_id' => $validated['visibility'] === Announcement::VISIBILITY_CLASS
                ? ($validated['class_id'] ?? null)
                : null,
            'created_by' => Auth::id(),
            'is_published' => $request->boolean('is_published'),
            'published_at' => $request->boolean('is_published') ? ($validated['published_at'] ?? now()) : null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function show(Announcement $announcement): View
    {
        $announcement->load(['creator:id,name', 'class:id,name']);

        return view('admin.announcements.show', compact('announcement'));
    }

    public function edit(Announcement $announcement): View
    {
        $classes = Classes::orderBy('name')->get(['id', 'name']);

        return view('admin.announcements.edit', compact('announcement', 'classes'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::in([
                Announcement::VISIBILITY_ALL,
                Announcement::VISIBILITY_ROLE,
                Announcement::VISIBILITY_CLASS,
            ])],
            'target_role' => ['nullable', 'required_if:visibility,role', Rule::in(['teacher', 'student'])],
            'class_id' => ['nullable', 'required_if:visibility,class', 'exists:classes,id'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'visibility' => $validated['visibility'],
            'target_role' => $validated['visibility'] === Announcement::VISIBILITY_ROLE
                ? ($validated['target_role'] ?? null)
                : null,
            'class_id' => $validated['visibility'] === Announcement::VISIBILITY_CLASS
                ? ($validated['class_id'] ?? null)
                : null,
            'is_published' => $request->boolean('is_published'),
            'published_at' => $request->boolean('is_published') ? ($announcement->published_at ?? now()) : null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
    }
}
