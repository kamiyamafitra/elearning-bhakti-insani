@php
    $icon = $icon ?? 'bi-journal-x';
    $actionUrl = $actionUrl ?? null;
    $actionLabel = $actionLabel ?? null;
    $actionClass = $actionClass ?? 'btn btn-outline-primary';
@endphp

<div class="subjects-empty">
    <div class="subjects-empty__icon">
        <i class="bi {{ $icon }}" aria-hidden="true"></i>
    </div>
    <h2 class="subjects-empty__title">{{ $title }}</h2>
    <p class="subjects-empty__description">{{ $description }}</p>

    @if ($actionUrl !== null && $actionLabel !== null)
        <a href="{{ $actionUrl }}" class="{{ $actionClass }}">{{ $actionLabel }}</a>
    @endif
</div>
