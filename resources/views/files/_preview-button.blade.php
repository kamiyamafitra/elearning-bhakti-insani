{{--
    "Lihat" button that opens an uploaded file in the shared preview pop-up. Renders nothing for files
    that can only be downloaded. Expects $url (the download route), $path (stored path) and $name;
    optional $class for the button style and $iconOnly for a compact eye icon.
--}}
@php($previewType = \App\Http\Controllers\Controller::previewTypeFor($path ?? null))
@if ($previewType)
    <button type="button"
            class="{{ $class ?? 'btn btn-sm btn-outline-primary' }}"
            data-bs-toggle="modal"
            data-bs-target="#filePreviewModal"
            data-file-preview="{{ $previewType }}"
            data-preview-url="{{ $url.(str_contains($url, '?') ? '&' : '?').'inline=1' }}"
            data-download-url="{{ $url }}"
            data-file-name="{{ $name ?: basename($path) }}"
            @if ($iconOnly ?? false) aria-label="Lihat {{ $name }}" title="Lihat" @endif>
        <i class="bi bi-eye{{ ($iconOnly ?? false) ? '' : ' me-1' }}" aria-hidden="true"></i>@unless ($iconOnly ?? false) Lihat @endunless
    </button>
@endif
