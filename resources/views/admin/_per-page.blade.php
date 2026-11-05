{{-- Page size picker shown above a directory table. Expects $paginator, $route, $perPage, $perPageOptions, $noun and $keep (filters to carry over). --}}
<form method="GET" action="{{ route($route) }}" class="d-flex flex-wrap align-items-center gap-2 small text-muted px-3 py-2 border-bottom">
    @foreach (array_filter($keep ?? [], fn ($value) => $value !== null && $value !== '') as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <label for="per_page" class="mb-0">Tampilkan</label>
    <select name="per_page" id="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        @foreach ($perPageOptions as $option)
            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <span>per halaman &middot; Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $noun }}</span>
    <noscript><button type="submit" class="btn btn-sm btn-light">Terapkan</button></noscript>
</form>
