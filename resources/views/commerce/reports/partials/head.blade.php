<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <h1 class="page-title h3 mb-0">{{ $title }}</h1>
    <div class="d-flex flex-wrap gap-2">
        @isset($pdfUrl)
            <a class="btn btn-outline-primary" href="{{ $pdfUrl }}"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
        @endisset
        @if ($print ?? true)
            <a class="btn btn-primary" href="{{ request()->fullUrlWithQuery(['all' => 1, 'print' => 1, 'page' => null]) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Print report</a>
        @endif
    </div>
</div>
@if (request()->boolean('print'))
    @push('scripts')
        <script>window.addEventListener('load', () => window.print());</script>
    @endpush
@endif
