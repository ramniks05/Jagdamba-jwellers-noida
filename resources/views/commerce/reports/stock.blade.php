@extends('layouts.app')

@section('title', request()->boolean('grams') ? 'Stock and purchase report' : (request()->boolean('report') ? 'Pieces report' : 'Stock and purchase'))

@section('content')
    @include('commerce.reports.partials.head', ['title' => request()->boolean('grams') ? 'Stock and purchase report' : (request()->boolean('report') ? 'Pieces report' : 'Stock and purchase'), 'print' => request()->boolean('report') || request()->boolean('grams')])
    @if (request()->boolean('report') || request()->boolean('grams'))
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.stock') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        @if (request()->boolean('report'))
            <input type="hidden" name="report" value="1">
        @endif
        @if (request()->boolean('grams'))
            <input type="hidden" name="grams" value="1">
        @endif
        <div class="row g-2 align-items-end">
            <div class="col-md-3 col-6">
                <label class="form-label" for="filter-metal">Metal</label>
                <select class="form-select" id="filter-metal" name="metal">
                    <option value="">All metals</option>
                    @foreach ($metals as $row)
                        <option value="{{ $row->uuid }}" @selected($filters['metal'] === $row->uuid)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label" for="filter-purity">Purity</label>
                <select class="form-select" id="filter-purity" name="purity">
                    <option value="">All purities</option>
                    @foreach ($metals as $row)
                        @foreach ($row->purities as $option)
                            <option value="{{ $option->uuid }}" data-metal="{{ $row->uuid }}" @selected($filters['purity'] === $option->uuid)>{{ $option->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label" for="filter-supplier">Supplier</label>
                <select class="form-select" id="filter-supplier" name="supplier">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->uuid }}" @selected($filters['supplierUuid'] === $supplier->uuid)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($filtered)
                    <a class="btn btn-outline-secondary" href="{{ route('reports.stock', array_filter(['report' => request()->boolean('report') ? 1 : null, 'grams' => request()->boolean('grams') ? 1 : null])) }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    @endif
    @can('inventory.create')
        @if (! request()->boolean('report') && ! request()->boolean('grams'))
        @php
            $purchaseGst = $editing?->tax_percent !== null && $editing?->tax_percent !== ''
                ? rtrim(rtrim((string) $editing->tax_percent, '0'), '.')
                : $gstPercent;
            $editingSupplier = $editing?->reference instanceof App\Models\Supplier ? $editing->reference->uuid : null;
        @endphp
        <div class="card mb-3 no-print">
            <div class="card-header bg-white">{{ $editing ? 'Correct purchase' : 'Add Purchase to stock' }}</div>
            <div class="card-body">
                <p class="text-secondary small">{{ $editing ? 'Change the supplier, bill number, metal, grams, or rate. Stock and the amount owed are updated.' : 'Stock is grams of a purity. Opening sets what is already in the shop. A purchase adds grams from a supplier and needs that supplier’s bill number. Weight times the rate, then GST, is the total. A normal bill takes those grams off.' }}</p>
                <form method="POST" action="{{ $editing ? route('reports.stock.update', $editing) : route('reports.stock.store') }}" class="row g-2 align-items-end" id="metal-stock-form" data-gst="{{ $purchaseGst }}" data-round="{{ $roundRupee ? '1' : '0' }}">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                        <input type="hidden" name="kind" value="purchase">
                    @endif
                    @if ($branches->count() > 1)
                        <div class="col-md-2 col-6">
                            <label class="form-label" for="stock-branch">Branch</label>
                            <select class="form-select" id="stock-branch" name="branch_uuid" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->uuid }}" @selected(old('branch_uuid', $editing?->branch?->uuid ?? $branches->first()?->uuid) === $branch->uuid)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_uuid" value="{{ $editing?->branch?->uuid ?? $branches->first()?->uuid }}">
                    @endif
                    @php
                        $showPurchase = $editing || old('kind', 'purchase') === 'purchase';
                    @endphp
                    @unless ($editing)
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-kind">Add as</label>
                        <select class="form-select" id="stock-kind" name="kind">
                            <option value="opening" @selected(! $showPurchase)>Opening stock</option>
                            <option value="purchase" @selected($showPurchase)>Purchase</option>
                        </select>
                    </div>
                    @endunless
                    <div class="col-md-3 col-12 {{ $showPurchase ? '' : 'd-none' }}" id="stock-supplier-wrap">
                        <label class="form-label" for="stock-supplier">Supplier</label>
                        <select class="form-select" id="stock-supplier" name="supplier_uuid" @required($showPurchase)>
                            <option value="">Choose</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->uuid }}" @selected(old('supplier_uuid', $editingSupplier) === $supplier->uuid)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-12 {{ $showPurchase ? '' : 'd-none' }}" id="stock-bill-wrap">
                        <label class="form-label" for="stock-bill">Supplier bill no.</label>
                        <input class="form-control @error('supplier_bill_number') is-invalid @enderror" id="stock-bill" name="supplier_bill_number" value="{{ old('supplier_bill_number', $editing?->supplier_bill_number) }}" maxlength="40" placeholder="Bill number" autocomplete="off" @required($showPurchase)>
                        @error('supplier_bill_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-metal">Metal</label>
                        <select class="form-select" id="stock-metal" name="metal_uuid" required>
                            @foreach ($metals as $metal)
                                <option value="{{ $metal->uuid }}" @selected(old('metal_uuid', $editing?->metalType?->uuid) === $metal->uuid)>{{ $metal->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-purity">Purity</label>
                        <select class="form-select" id="stock-purity" name="purity_uuid" required>
                            @foreach ($metals as $metal)
                                @foreach ($metal->purities as $purity)
                                    <option value="{{ $purity->uuid }}" data-metal="{{ $metal->uuid }}" @selected(old('purity_uuid', $editing?->purity?->uuid) === $purity->uuid)>{{ $purity->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-grams">Grams</label>
                        <input class="form-control" id="stock-grams" name="grams" value="{{ old('grams', $editing ? rtrim(rtrim((string) $editing->grams, '0'), '.') : '') }}" inputmode="decimal" placeholder="0.000" required>
                    </div>
                    <div class="col-md-2 col-6 {{ $showPurchase ? '' : 'd-none' }}" id="stock-rate-wrap">
                        <label class="form-label" for="stock-rate">Rate / g</label>
                        <input class="form-control @error('rate_per_gram') is-invalid @enderror" id="stock-rate" name="rate_per_gram" value="{{ old('rate_per_gram', $editing?->rate_per_gram) }}" inputmode="decimal" placeholder="0.00" @required($showPurchase)>
                    </div>
                    <div class="col-12 {{ $showPurchase ? '' : 'd-none' }}" id="stock-quote">
                        <p class="small mb-0">Metal value <strong id="stock-metal-value">0.00</strong> · GST {{ $purchaseGst }}% <strong id="stock-gst">0.00</strong> · Total <strong id="stock-total">0.00</strong></p>
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button class="btn btn-primary" type="submit">{{ $editing ? 'Save correction' : 'Add to stock' }}</button>
                        @if ($editing)
                            <a class="btn btn-outline-secondary" href="{{ route('reports.stock') }}">Cancel</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endcan
    @if (! request()->boolean('report'))
    @if ($metalStock->isNotEmpty())
        @php
            $gramTotal = function (iterable $rows, string $key): string {
                $total = 0;
                foreach ($rows as $row) {
                    $total += (float) $row[$key];
                }

                return number_format($total, 3, '.', '');
            };
        @endphp
        <div class="stock-boards mb-3">
            @foreach ($metalStock->groupBy('metal') as $metal => $rows)
                @php
                    $inHand = $gramTotal($rows, 'balance');
                @endphp
                <section class="stock-board">
                    <header class="stock-board-total">
                        <div class="stock-board-name">{{ $metal }} total</div>
                        <div class="stock-board-figures">
                            <div>
                                <span>Opening</span>
                                <strong>{{ $weight($gramTotal($rows, 'opening')) }}</strong>
                            </div>
                            <div>
                                <span>Purchased</span>
                                <strong>{{ $weight($gramTotal($rows, 'purchased')) }}</strong>
                            </div>
                            <div>
                                <span>Sold</span>
                                <strong>{{ $weight($gramTotal($rows, 'sold')) }}</strong>
                            </div>
                            <div @class(['is-hand', 'is-short' => (float) $inHand < 0])>
                                <span>In hand</span>
                                <strong>{{ $weight($inHand) }}</strong>
                            </div>
                        </div>
                    </header>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Purity</th>
                                    <th class="num">Opening</th>
                                    <th class="num">Purchased</th>
                                    <th class="num">Sold</th>
                                    <th class="num">In hand</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>{{ $row['purity'] }}</td>
                                        <td class="num">{{ $weight($row['opening']) }}</td>
                                        <td class="num">{{ $weight($row['purchased']) }}</td>
                                        <td class="num">{{ $weight($row['sold']) }}</td>
                                        <td class="num {{ $row['short'] ? 'text-danger' : '' }}"><strong>{{ $weight($row['balance']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
        @if ($supplierName)
            <p class="text-secondary small">Purchased grams are from {{ $supplierName }}. Opening, sold and in hand are the shop totals for that purity.</p>
        @endif
    @elseif ($filters['metal'] !== '' || $filters['purity'] !== '' || $filters['supplierUuid'] !== '')
        <p class="text-secondary">No grams for this filter.</p>
    @endif
    @endif
    @can('inventory.create')
        @if (! request()->boolean('report') && ! request()->boolean('grams'))
        @if ($purchases->isNotEmpty())
            <div class="card mb-3 no-print">
                <div class="card-header bg-white">Last 5 purchases</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Bill no.</th>
                                <th>Metal</th>
                                <th>Purity</th>
                                <th class="num">Grams</th>
                                <th class="num">Rate</th>
                                <th class="num">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchases as $purchase)
                                <tr @class(['table-active' => $editing && $editing->id === $purchase->id])>
                                    <td>{{ $purchase->occurred_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                                    <td>{{ $purchase->reference instanceof App\Models\Supplier ? $purchase->reference->name : '—' }}</td>
                                    <td>{{ $purchase->supplier_bill_number ?: '—' }}</td>
                                    <td>{{ $purchase->metalType?->name }}</td>
                                    <td>{{ $purchase->purity?->name }}</td>
                                    <td class="num">{{ $weight((string) $purchase->grams) }}</td>
                                    <td class="num">{{ $purchase->rate_per_gram !== null ? $money((string) $purchase->rate_per_gram) : '—' }}</td>
                                    <td class="num">{{ $purchase->total_amount !== null ? $money((string) $purchase->total_amount) : '—' }}</td>
                                    <td class="text-end"><a href="{{ route('reports.stock', ['edit' => $purchase->uuid]) }}">Edit</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        @endif
    @endcan
    @if (request()->boolean('report'))
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Stock on hand</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ now()->timezone(config('app.timezone'))->format('d M Y') }}@if ($filtered) · filtered @endif</p>
            </div>
            <div class="text-end">
                <div>{{ $pieceCount }} {{ $pieceCount === 1 ? 'piece' : 'pieces' }}</div>
                <div>Net {{ $netTotal }}</div>
            </div>
        </header>
        @if ($metalTotals->isNotEmpty())
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Metal</th>
                            <th class="num">Pieces</th>
                            <th class="num">Gross</th>
                            <th class="num">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metalTotals as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="num">{{ $row['pieces'] }}</td>
                                <td class="num">{{ $row['gross'] }}</td>
                                <td class="num">{{ $row['net'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Grand total</td>
                            <td class="num">{{ $pieceCount }}</td>
                            <td class="num">{{ $grossTotal }}</td>
                            <td class="num">{{ $netTotal }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Metal</th>
                        <th>Category</th>
                        <th>Place</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pieces as $item)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}@if ($item->huid)<div class="small text-secondary">HUID {{ $item->huid }}</div>@endif</td>
                            <td>{{ trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? '')) }}</td>
                            <td>{{ $item->category?->name ?: '—' }}</td>
                            <td>{{ collect([$item->location?->branch?->name, $item->location?->name])->filter()->join(' / ') ?: '—' }}</td>
                            <td class="num">{{ $weight((string) $item->gross_weight) }}</td>
                            <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                            <td><span class="order-status is-{{ $item->status->value }}">{{ $item->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No pieces in stock for this filter.</td></tr>
                    @endforelse
                </tbody>
                @if ($pieceCount > 0)
                    <tfoot>
                        <tr>
                            <td colspan="5">Total · {{ $pieceCount }} {{ $pieceCount === 1 ? 'piece' : 'pieces' }}</td>
                            <td class="num">{{ $grossTotal }}</td>
                            <td class="num">{{ $netTotal }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </article>
    @include('commerce.reports.partials.pager', ['rows' => $pieces, 'noun' => 'pieces'])
    @endif
@endsection

@push('scripts')
    <script>
        const stockMetal = document.getElementById('stock-metal');
        const stockPurity = document.getElementById('stock-purity');
        if (stockMetal && stockPurity) {
            const syncStockPurity = () => {
                const metal = stockMetal.value;
                let first = null;
                Array.from(stockPurity.options).forEach((option) => {
                    const show = option.dataset.metal === metal;
                    option.hidden = !show;
                    option.disabled = !show;
                    if (show && !first) first = option;
                });
                if (!stockPurity.selectedOptions[0] || stockPurity.selectedOptions[0].disabled) {
                    if (first) first.selected = true;
                }
            };
            stockMetal.addEventListener('change', syncStockPurity);
            syncStockPurity();
        }
        const stockKind = document.getElementById('stock-kind');
        const stockSupplierWrap = document.getElementById('stock-supplier-wrap');
        const stockSupplier = document.getElementById('stock-supplier');
        const stockBillWrap = document.getElementById('stock-bill-wrap');
        const stockBill = document.getElementById('stock-bill');
        const stockRateWrap = document.getElementById('stock-rate-wrap');
        const stockRate = document.getElementById('stock-rate');
        const stockQuote = document.getElementById('stock-quote');
        const stockForm = document.getElementById('metal-stock-form');
        const stockGrams = document.getElementById('stock-grams');
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
        const previewPurchase = () => {
            if (!stockForm || !stockQuote) return;
            const grams = Number(stockGrams?.value || 0);
            const rate = Number(stockRate?.value || 0);
            const gstPercent = Number(stockForm.dataset.gst || 0);
            const metalValue = grams > 0 && rate > 0 ? round2(grams * rate) : 0;
            const gst = round2(metalValue * gstPercent / 100);
            let total = round2(metalValue + gst);
            if (stockForm.dataset.round === '1') total = Math.round(total);
            const metalNode = document.getElementById('stock-metal-value');
            const gstNode = document.getElementById('stock-gst');
            const totalNode = document.getElementById('stock-total');
            if (metalNode) metalNode.textContent = money.format(metalValue);
            if (gstNode) gstNode.textContent = money.format(gst);
            if (totalNode) totalNode.textContent = money.format(total);
        };
        if (stockKind && stockSupplierWrap && stockSupplier && stockBillWrap && stockBill && stockRateWrap && stockRate && stockQuote) {
            const syncStockSupplier = () => {
                const purchase = stockKind.value === 'purchase';
                stockSupplierWrap.classList.toggle('d-none', !purchase);
                stockSupplier.required = purchase;
                stockSupplier.disabled = !purchase;
                stockBillWrap.classList.toggle('d-none', !purchase);
                stockBill.required = purchase;
                stockBill.disabled = !purchase;
                stockRateWrap.classList.toggle('d-none', !purchase);
                stockQuote.classList.toggle('d-none', !purchase);
                stockRate.required = purchase;
                stockRate.disabled = !purchase;
                previewPurchase();
            };
            stockKind.addEventListener('change', syncStockSupplier);
            stockGrams?.addEventListener('input', previewPurchase);
            stockRate.addEventListener('input', previewPurchase);
            syncStockSupplier();
        } else if (stockRate && stockQuote) {
            stockGrams?.addEventListener('input', previewPurchase);
            stockRate.addEventListener('input', previewPurchase);
            previewPurchase();
        }
        const filterMetal = document.getElementById('filter-metal');
        const filterPurity = document.getElementById('filter-purity');
        if (filterMetal && filterPurity) {
            const syncFilterPurity = () => {
                const metal = filterMetal.value;
                let keep = filterPurity.value === '';
                Array.from(filterPurity.options).forEach((option) => {
                    if (!option.value) {
                        return;
                    }
                    const show = metal === '' || option.dataset.metal === metal;
                    option.hidden = !show;
                    option.disabled = !show;
                    if (show && option.value === filterPurity.value) {
                        keep = true;
                    }
                });
                if (!keep) {
                    filterPurity.value = '';
                }
            };
            filterMetal.addEventListener('change', syncFilterPurity);
            syncFilterPurity();
        }
    </script>
@endpush
