@php
    $sortOptions = $sortOptions ?? ['name_asc' => 'Nama A–Z'];
    $filterOptions = $filterOptions ?? [];
    $filterName = $filterName ?? null;
    $hasFilter = $filterName !== null && $filterOptions !== [];
    $defaultSort = array_key_first($sortOptions);
    $hasActiveFilters = filled($search)
        || ($hasFilter && filled($filterValue ?? null))
        || $sort !== $defaultSort;
    $searchPlaceholder = $searchPlaceholder ?? 'Cari nama atau kode mata pelajaran...';
@endphp

<form method="GET" action="{{ $action }}" class="subjects-toolbar" role="search">
    <div class="subjects-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input
            type="search"
            name="q"
            class="form-control"
            placeholder="{{ $searchPlaceholder }}"
            value="{{ $search }}"
            aria-label="{{ $searchPlaceholder }}"
        >
    </div>

    @if ($hasFilter)
        <div class="subjects-filter">
            <label for="{{ $filterName }}" class="subjects-filter__label">
                <i class="bi {{ $filterIcon ?? 'bi-funnel' }}" aria-hidden="true"></i>
                {{ $filterLabel }}
            </label>
            <select name="{{ $filterName }}" id="{{ $filterName }}" class="form-select">
                <option value="">{{ $filterPlaceholder ?? 'Semua' }}</option>
                @foreach ($filterOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) ($filterValue ?? '') === (string) $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="subjects-filter">
        <label for="sort" class="subjects-filter__label">
            <i class="bi bi-sort-down" aria-hidden="true"></i>
            Urutkan
        </label>
        <select name="sort" id="sort" class="form-select">
            @foreach ($sortOptions as $value => $label)
                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <button class="btn btn-primary subjects-toolbar__button" type="submit">
        <i class="bi bi-funnel me-1" aria-hidden="true"></i>
        Terapkan
    </button>

    @if ($hasActiveFilters)
        <a href="{{ $action }}" class="btn btn-light subjects-toolbar__reset">
            Reset
        </a>
    @endif
</form>
