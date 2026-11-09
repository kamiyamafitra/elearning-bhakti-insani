{{-- Letter grade badge. Expects $predicate (A-D or null). --}}
@if ($predicate)
    <span class="badge predicate-badge predicate-badge--{{ strtolower($predicate) }}">{{ $predicate }}</span>
@else
    <span class="text-muted">-</span>
@endif
