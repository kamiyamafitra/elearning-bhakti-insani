<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $request->user()->markAnnouncementsSeen();

        $announcements = Announcement::query()
            ->visibleTo($request->user())
            ->with(['creator:id,name', 'class:id,name'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('student.announcements.index', compact('announcements'));
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $announcement = $this->visibleAnnouncement($request, $announcement)->load(['creator:id,name', 'class:id,name']);

        return view('student.announcements.show', compact('announcement'));
    }

    public function image(Request $request, Announcement $announcement): BinaryFileResponse
    {
        return $this->visibleAnnouncement($request, $announcement)->imageResponse();
    }

    /**
     * Only announcements this user may read, so hidden or expired ones answer 404.
     */
    private function visibleAnnouncement(Request $request, Announcement $announcement): Announcement
    {
        return Announcement::query()
            ->visibleTo($request->user())
            ->whereKey($announcement->id)
            ->firstOrFail();
    }
}
