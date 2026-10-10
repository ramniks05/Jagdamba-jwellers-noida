@extends('layouts.app')

@section('title', 'Pieces')

@section('content')
    @php
        $pill = fn (string $value) => match ($value) {
            'available' => 'is-ready',
            'reserved' => 'is-booked',
            'repair' => 'is-repairing',
            'damaged', 'lost' => 'is-cancelled',
            default => 'is-delivered',
        };
        $filters = ['available' => 'In stock', 'reserved' => 'Reserved', 'repair' => 'At repair', 'sold' => 'Sold', '' => 'All'];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title h3 mb-0">Jewellery pieces</h1>
            <p class="text-secondary mb-0">Your stock list. To sell a piece, open it and use Sell this piece, or start a New bill.</p>
        </div>
        @can('create', App\Models\Item::class)
            <a class="btn btn-primary" href="{{ route('items.create') }}"><i class="bi bi-plus-lg"></i> Add piece</a>
        @endcan
    </div>
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach ($filters as $key => $label)
            @php($count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0))
            <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('items.index', ['status' => $key] + array_filter(['search' => $search, 'source' => $source])) }}">{{ $label }} <span class="opacity-75">{{ $count }}</span></a>
        @endforeach
        @if (! array_key_exists($status, $filters))
            <span class="btn btn-sm btn-primary disabled">{{ App\Enums\ItemStatus::tryFrom($status)?->label() }}</span>
        @endif
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="small text-secondary">Source</span>
        <a class="btn btn-sm {{ $source === '' ? 'btn-dark' : 'btn-outline-secondary' }}" href="{{ route('items.index', ['status' => $status] + array_filter(['search' => $search])) }}">All</a>
        @foreach ($sources as $option)
            <a class="btn btn-sm {{ $source === $option->value ? 'btn-dark' : 'btn-outline-secondary' }}" href="{{ route('items.index', ['status' => $status, 'source' => $option->value] + array_filter(['search' => $search])) }}">{{ $option->label() }}</a>
        @endforeach
    </div>
    <form class="d-flex flex-wrap gap-2 mb-3" method="GET" action="{{ route('items.index') }}">
        <input type="hidden" name="status" value="{{ $status }}">
        @if ($source !== '')
            <input type="hidden" name="source" value="{{ $source }}">
        @endif
        <input class="form-control" style="max-width: 22rem" name="search" value="{{ $search }}" placeholder="Code, name, barcode or HUID">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Find</button>
        @if ($search !== '')
            <a class="btn btn-link" href="{{ route('items.index', ['status' => $status] + array_filter(['source' => $source])) }}">Clear</a>
        @endif
    </form>
    @php($canPrint = auth()->user()->can('inventory.print'))
    @if ($canPrint && $items->isNotEmpty())
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="tag-batch">
            <button class="btn btn-sm btn-outline-secondary" type="button" id="tag-batch-print" disabled><i class="bi bi-upc-scan"></i> Print tags <span id="tag-batch-count">(0)</span></button>
            <a class="btn btn-sm btn-link" href="{{ route('labels.settings.edit') }}">Tag settings</a>
            <span class="small" id="tag-batch-result" role="status" aria-live="polite"></span>
        </div>
    @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        @if ($canPrint)
                            <th><input class="form-check-input item-pick" type="checkbox" id="tag-pick-all" aria-label="Pick every piece on this page"></th>
                        @endif
                        <th>Code</th>
                        <th>Piece</th>
                        <th>Metal</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                        <th>Kept at</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            @if ($canPrint)
                                <td><input class="form-check-input item-pick" type="checkbox" value="{{ $item->uuid }}" data-tag-pick aria-label="Pick {{ $item->item_code }}"></td>
                            @endif
                            <td class="text-nowrap"><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}@if ($item->category)<div class="small text-secondary">{{ $item->category->name }}</div>@endif</td>
                            <td class="text-nowrap">{{ $item->metalType?->name }} {{ $item->purity?->name }}</td>
                            <td class="num">{{ $weight((string) $item->gross_weight) }}</td>
                            <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                            <td class="text-nowrap">{{ $item->location?->label() }}</td>
                            <td class="text-nowrap">{{ $item->source->label() }}</td>
                            <td><span class="order-status {{ $pill($item->status->value) }}">{{ $item->status->label() }}</span></td>
                            <td class="text-end"><a href="{{ route('items.show', $item) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canPrint ? 10 : 9 }}">{{ $search !== '' ? 'Nothing found.' : 'No pieces here yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endsection

@if ($canPrint && $items->isNotEmpty())
    @push('scripts')
        <script src="{{ asset('vendor/qz-tray/qz-tray.js') }}?v={{ filemtime(public_path('vendor/qz-tray/qz-tray.js')) }}"></script>
        <script src="{{ asset('js/jewellery-label-printer.js') }}?v={{ filemtime(public_path('js/jewellery-label-printer.js')) }}"></script>
        <script>
            (function () {
                const printer = window.JewelleryLabelPrinter;
                const picks = Array.from(document.querySelectorAll('[data-tag-pick]'));
                const all = document.getElementById('tag-pick-all');
                const button = document.getElementById('tag-batch-print');
                const count = document.getElementById('tag-batch-count');
                const result = document.getElementById('tag-batch-result');
                const limit = {{ App\Http\Controllers\Web\Commerce\ItemLabelController::BATCH_LIMIT }};

                const show = function (tone, text) {
                    result.className = 'small text-' + tone;
                    result.textContent = text;
                };

                const chosen = function () {
                    return picks.filter(function (box) { return box.checked; }).map(function (box) { return box.value; });
                };

                const refresh = function () {
                    const total = chosen().length;
                    count.textContent = '(' + total + ')';
                    button.disabled = total === 0 || printer.isBusy();
                    all.checked = total > 0 && total === picks.length;
                };

                if (!printer) {
                    show('danger', 'The printing helper did not load. Refresh the page.');
                    return;
                }

                printer.configure({
                    printerName: @json((string) app(App\Services\Foundation\SettingService::class)->get('label.printer_name')),
                    certificateUrl: @json(route('labels.qz.certificate')),
                    signUrl: @json(route('labels.qz.sign')),
                    csrf: @json(csrf_token()),
                });

                picks.forEach(function (box) { box.addEventListener('change', refresh); });
                all.addEventListener('change', function () {
                    picks.forEach(function (box) { box.checked = all.checked; });
                    refresh();
                });

                button.addEventListener('click', function () {
                    const items = chosen();

                    if (items.length > limit) {
                        show('danger', 'Print at most ' + limit + ' tags at a time.');
                        return;
                    }

                    if (items.length >= 10 && !window.confirm('Print ' + items.length + ' tags?')) {
                        return;
                    }

                    button.disabled = true;
                    show('secondary', 'Sending tags to the printer…');

                    printer.printBatch(@json(route('labels.batch')), items, 1, function (done, total) {
                        show('secondary', 'Sent ' + done + ' of ' + total + '…');
                    })
                        .then(function (outcome) {
                            if (outcome.failed.length === 0) {
                                show('success', outcome.printed.length + ' tag' + (outcome.printed.length === 1 ? '' : 's') + ' sent to ' + outcome.printer + '.');
                                return;
                            }

                            const lines = outcome.failed.map(function (row) { return row.code + ': ' + row.message; }).join(' ');
                            show(outcome.printed.length ? 'warning' : 'danger', outcome.printed.length + ' sent, ' + outcome.failed.length + ' not printed. ' + lines);
                        })
                        .catch(function (error) { show('danger', error.message); })
                        .finally(refresh);
                });
            })();
        </script>
    @endpush
@endif
