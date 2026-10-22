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
    @if ($categorySummary->isNotEmpty())
        <section class="category-summary" aria-label="In stock by category">
            <div class="category-summary-head">
                <div class="weigh-section-title mb-0"><i class="bi bi-tags"></i> In stock by category · {{ $categorySummary->sum('in_stock') }} pcs</div>
                <div class="d-flex gap-3 small">
                    @if ($categoryUuid !== '')
                        <a href="{{ route('items.index', ['status' => $status] + array_filter(['search' => $search])) }}">Show all categories</a>
                    @endif
                    @can('reports.view')
                        <a href="{{ route('reports.categories') }}">Pieces by category report</a>
                    @endcan
                </div>
            </div>
            <div class="category-tiles">
                @foreach ($categorySummary as $row)
                    @php($key = $row['uuid'] ?? 'none')
                    <a class="category-tile {{ $categoryUuid === $key ? 'is-active' : '' }}" href="{{ route('items.index', ['status' => 'available', 'category' => $key] + array_filter(['search' => $search])) }}">
                        <div class="category-tile-name" title="{{ $row['name'] }}">{{ $row['name'] }}</div>
                        <div class="category-tile-count">{{ $row['in_stock'] }} <small>pcs</small></div>
                        <div class="category-tile-weight">Net {{ $weight($row['net']) }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach ($filters as $key => $label)
            @php($count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0))
            <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('items.index', ['status' => $key] + array_filter(['search' => $search, 'category' => $categoryUuid])) }}">{{ $label }} <span class="opacity-75">{{ $count }}</span></a>
        @endforeach
        @if ($categoryName)
            <span class="btn btn-sm btn-outline-primary disabled">Category: {{ $categoryName }}</span>
        @endif
        @if (! array_key_exists($status, $filters))
            <span class="btn btn-sm btn-primary disabled">{{ App\Enums\ItemStatus::tryFrom($status)?->label() }}</span>
        @endif
    </div>
    <form class="filter-bar" method="GET" action="{{ route('items.index') }}">
        <input type="hidden" name="status" value="{{ $status }}">
        @if ($categoryUuid !== '')
            <input type="hidden" name="category" value="{{ $categoryUuid }}">
        @endif
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="piece-search">Find</label>
                <input class="form-control" id="piece-search" name="search" value="{{ $search }}" placeholder="Code, name, barcode or HUID">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Find</button>
                @if ($search !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('items.index', ['status' => $status] + array_filter(['category' => $categoryUuid])) }}">Clear</a>
                @endif
            </div>
        </div>
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
                            <td><span class="order-status {{ $pill($item->status->value) }}">{{ $item->status->label() }}</span></td>
                            <td class="text-end text-nowrap">
                                @if ($canPrint)
                                    <button class="btn btn-sm btn-link p-0 me-2" type="button" data-tag-print="{{ route('items.label.zpl', $item) }}" data-tag-code="{{ $item->item_code }}" title="Print tag for {{ $item->item_code }}" aria-label="Print tag for {{ $item->item_code }}"><i class="bi bi-printer"></i></button>
                                @endif
                                <a href="{{ route('items.show', $item) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canPrint ? 9 : 8 }}">{{ $search !== '' ? 'Nothing found.' : 'No pieces here yet.' }}</td></tr>
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

                document.querySelectorAll('[data-tag-print]').forEach(function (single) {
                    single.addEventListener('click', function () {
                        if (printer.isBusy()) {
                            show('warning', 'A print is already running. Wait for it to finish.');
                            return;
                        }

                        show('secondary', 'Sending ' + single.dataset.tagCode + ' to the printer…');
                        printer.printUrl(single.dataset.tagPrint + '?copies=1')
                            .then(function (name) { show('success', 'Tag ' + single.dataset.tagCode + ' sent to ' + name + '.'); })
                            .catch(function (error) { show('danger', error.message); })
                            .finally(refresh);
                    });
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
