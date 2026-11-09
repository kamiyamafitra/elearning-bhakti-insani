{{-- Report card grade and its letter as two table cells. Expects $report from App\Support\GradeRecap. --}}
<td class="text-center fw-bold">
    @if ($report['final'] !== null)
        <span class="{{ $report['passed'] ? '' : 'text-danger' }}" title="{{ $report['passed'] ? 'Tuntas' : 'Belum tuntas' }}">{{ number_format($report['final'], 1, ',', '.') }}</span>
    @else
        <span class="text-muted fw-normal">-</span>
    @endif
</td>
<td class="text-center">
    @include('grades._predicate', ['predicate' => $report['predicate']])
</td>
