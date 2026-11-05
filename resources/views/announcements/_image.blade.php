{{-- Small thumbnail that opens the full picture in a pop-up. Expects $imageUrl. --}}
<button type="button" class="announcement-thumb" data-bs-toggle="modal" data-bs-target="#announcementImageModal" aria-label="Perbesar gambar pengumuman">
    <img src="{{ $imageUrl }}" alt="Gambar pengumuman" loading="lazy">
    <span class="announcement-thumb__hint"><i class="bi bi-arrows-angle-expand" aria-hidden="true"></i> Ketuk untuk memperbesar</span>
</button>

<div class="modal fade" id="announcementImageModal" tabindex="-1" aria-label="Gambar pengumuman" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <img src="{{ $imageUrl }}" alt="Gambar pengumuman" class="announcement-full-image">
        </div>
    </div>
</div>
