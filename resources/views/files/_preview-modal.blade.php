{{-- Shared pop-up that previews PDFs, Word files, spreadsheets, images and text files without downloading them. --}}
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-6 text-truncate" id="filePreviewTitle" data-preview-title>Pratinjau</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body file-preview" data-preview-body></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <a href="#" class="btn btn-primary" data-preview-download>
                    <i class="bi bi-download me-1" aria-hidden="true"></i> Unduh
                </a>
            </div>
        </div>
    </div>
</div>
{{-- The preview logic lives in resources/js/file-preview.js and bundles its viewer libraries (no outside CDN). --}}
