@extends($layout)

@section('title', 'Konversi PDF')
@section('page-title', 'Konversi PDF')
@section('page-description', 'Ubah gambar menjadi file PDF.')

@section('content')
<div class="card card-dashboard">
    <div class="card-body">
        <form method="POST" action="{{ $storeRoute }}" enctype="multipart/form-data" id="pdfConverterForm"
            data-image-extensions="{{ implode(',', $imageExtensions) }}"
            data-max-files="{{ $maxFiles }}"
            data-max-file-kilobytes="{{ $maxFileKilobytes }}">
            @csrf

            <label class="form-label" for="files">Pilih Gambar</label>
            <input type="file" id="files" name="files[]" class="form-control" accept="{{ collect($imageExtensions)->map(fn ($extension) => ".{$extension}")->implode(',') }}" multiple required>
            <div class="form-text">
                JPG, PNG, atau WEBP. Sampai {{ $maxFiles }} gambar digabung menjadi satu PDF, masing-masing maksimal {{ $maxFileKilobytes / 1024 }} MB.
            </div>

            <div class="text-danger small mt-2 d-none" id="fileProblem" role="alert"></div>
            <ul class="list-unstyled small text-muted mt-3 mb-0" id="fileList"></ul>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="convertButton">
                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> <span id="convertLabel">Konversi ke PDF</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('pdfConverterForm');
        const input = document.getElementById('files');
        const problemMessage = document.getElementById('fileProblem');
        const fileList = document.getElementById('fileList');
        const button = document.getElementById('convertButton');
        const label = document.getElementById('convertLabel');
        const imageExtensions = form.dataset.imageExtensions.split(',');
        const maxFiles = Number(form.dataset.maxFiles);
        const maxFileKilobytes = Number(form.dataset.maxFileKilobytes);
        const buttonResetDelay = 5000;

        function findProblem(files) {
            const unsupported = files.find((file) => !imageExtensions.includes(file.name.split('.').pop().toLowerCase()));
            const oversized = files.find((file) => file.size > maxFileKilobytes * 1024);

            if (unsupported) {
                return 'Format file "' + unsupported.name + '" tidak didukung.';
            }

            if (oversized) {
                return 'File "' + oversized.name + '" lebih besar dari ' + (maxFileKilobytes / 1024) + ' MB.';
            }

            return files.length > maxFiles ? 'Maksimal ' + maxFiles + ' gambar sekali konversi.' : null;
        }

        function refresh() {
            const files = [...input.files];
            const problem = findProblem(files);

            problemMessage.textContent = problem ?? '';
            problemMessage.classList.toggle('d-none', !problem);
            button.disabled = Boolean(problem);

            fileList.replaceChildren(...files.map(function (file, index) {
                const item = document.createElement('li');
                item.textContent = (index + 1) + '. ' + file.name;

                return item;
            }));
        }

        input.addEventListener('change', refresh);

        form.addEventListener('submit', function (event) {
            if (findProblem([...input.files])) {
                event.preventDefault();

                return;
            }

            // A download leaves the page in place, so the button has to reset itself.
            label.textContent = 'Memproses...';
            setTimeout(function () {
                button.disabled = true;
            });
            setTimeout(function () {
                label.textContent = 'Konversi ke PDF';
                button.disabled = false;
            }, buttonResetDelay);
        });
    })();
</script>
@endpush
