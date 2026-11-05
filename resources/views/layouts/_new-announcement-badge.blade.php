{{-- Red counter beside "Pengumuman" in the sidebar. Expects $newAnnouncementCount. --}}
@if (($newAnnouncementCount ?? 0) > 0)
    <span class="badge rounded-pill text-bg-danger ms-auto" aria-label="{{ $newAnnouncementCount }} pengumuman baru">{{ $newAnnouncementCount > 99 ? '99+' : $newAnnouncementCount }}</span>
@endif
