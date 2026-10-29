<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::query()
            ->visibleTo($request->user())
            ->with(['creator:id,name', 'class:id,name'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('teacher.announcements.index', compact('announcements'));
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $announcement = Announcement::query()
            ->visibleTo($request->user())
            ->with(['creator:id,name', 'class:id,name'])
            ->whereKey($announcement->id)
            ->firstOrFail();

        return view('teacher.announcements.show', compact('announcement'));
    }
}
