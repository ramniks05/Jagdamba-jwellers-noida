@extends('layouts.app')

@section('title', 'Other items')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Other items',
        'intro' => 'Fixed-price products with no weight (murti, gift items). Pick them on a bill or type a one-off name and price.',
        'actions' => auth()->user()->can('create', App\Models\RetailProduct::class)
            ? [['url' => route('retail-products.create'), 'label' => 'Add item', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route('retail-products.index')])
    @php($canPrint = auth()->user()->can('inventory.print'))
    @if ($canPrint && $products->isNotEmpty())
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
                            <th><input class="form-check-input" type="checkbox" id="tag-pick-all" aria-label="Pick every other item on this page"></th>
                        @endif
                        <th>Name</th>
                        <th>Code</th>
                        <th class="num">Default price</th>
                        <th>GST</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            @if ($canPrint)
                                <td><input class="form-check-input retail-tag-pick" type="checkbox" value="{{ $product->uuid }}" data-tag-pick aria-label="Pick {{ $product->code }}"></td>
                            @endif
                            <td class="fw-semibold">{{ $product->name }}</td>
                            <td>{{ $product->code }}</td>
                            <td class="num">{{ $product->default_unit_price !== null ? number_format((float) $product->default_unit_price, 2) : '—' }}</td>
                            <td>
                                @if ($product->default_tax_mode->value === 'none')
                                    No GST
                                @else
                                    {{ $product->default_tax_mode->label() }}
                                    @if ($product->default_tax_percent)
                                        · {{ rtrim(rtrim(number_format((float) $product->default_tax_percent, 2), '0'), '.') }}%
                                    @endif
                                @endif
                            </td>
                            <td>@include('masters.partials.status', ['active' => $product->is_active])</td>
                            <td class="text-end text-nowrap">
                                @if ($canPrint)
                                    <button class="btn btn-sm btn-link p-0 me-2" type="button" data-tag-print="{{ route('retail-products.label.zpl', $product) }}" data-tag-code="{{ $product->code }}" title="Print tag for {{ $product->code }}" aria-label="Print tag for {{ $product->code }}"><i class="bi bi-printer"></i></button>
                                    <a class="btn btn-sm btn-link p-0 me-2" href="{{ route('retail-products.label', $product) }}">Preview</a>
                                @endif
                                @can('update', $product)
                                    <a href="{{ route('retail-products.edit', $product) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canPrint ? 7 : 6 }}">{{ $search !== '' || $show !== 'all' ? 'No item matches this search.' : 'No other items yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </div>
@endsection

@if ($canPrint && $products->isNotEmpty())
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
                    const products = chosen();

                    if (products.length > limit) {
                        show('danger', 'Print at most ' + limit + ' tags at a time.');
                        return;
                    }

                    if (products.length >= 10 && !window.confirm('Print ' + products.length + ' tags?')) {
                        return;
                    }

                    button.disabled = true;
                    show('secondary', 'Sending tags to the printer…');

                    printer.printBatch(@json(route('labels.retail.batch')), products, 1, function (done, total) {
                        show('secondary', 'Sent ' + done + ' of ' + total + '…');
                    }, 'products')
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
