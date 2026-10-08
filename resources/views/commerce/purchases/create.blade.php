@extends('layouts.app')

@section('title', 'Receive purchase')

@section('content')
    @php
        $unitLabels = [
            'making' => ['per_gram' => '₹ / g', 'percentage' => '% of metal', 'fixed' => '₹ per piece', 'per_piece' => '₹ per piece'],
            'wastage' => ['per_gram' => '₹ / g', 'percentage' => '% of metal', 'fixed' => '₹ per piece'],
        ];
        $supplierValue = old('supplier_uuid', $chosenSupplier);
        $pricing = old('pricing', 'rate');
        $defaultMaking = old('default_making_method', $making->firstWhere('code', 'per_gram')?->uuid);
        $defaultWastage = old('default_wastage_method', $wastage->firstWhere('code', 'percentage')?->uuid);
        $oldPayments = old('payments', []);
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <a href="{{ route('purchases.index') }}"><i class="bi bi-arrow-left"></i> All purchases</a>
    </div>
    <h1 class="page-title h3 mb-1">Receive purchase</h1>
    <p class="text-secondary mb-3">Type the supplier’s bill. Every piece goes into stock with its own code. The supplier’s rate and labour make your cost; your selling making is kept separately on each piece.</p>

    <form method="POST" action="{{ route('purchases.store') }}" id="purchase-form" autocomplete="off">
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header bg-white">Supplier bill</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <label class="form-label" for="supplier">Supplier</label>
                                    @can('create', App\Models\Supplier::class)
                                        <a class="small" href="{{ route('suppliers.create', ['for' => 'purchase']) }}"><i class="bi bi-plus-lg"></i> New supplier</a>
                                    @endcan
                                </div>
                                <select class="form-select @error('supplier_uuid') is-invalid @enderror" id="supplier" name="supplier_uuid" required>
                                    <option value="">{{ $suppliers->isEmpty() ? 'No suppliers yet' : 'Choose' }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->uuid }}" data-payable="{{ $payable[$supplier->uuid] ?? '0.00' }}" @selected($supplierValue === $supplier->uuid)>{{ $supplier->name }}{{ $supplier->mobile ? ' · '.$supplier->mobile : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text" id="supplier-balance">
                                    @if ($suppliers->isEmpty())
                                        Add the supplier first with “New supplier”. You come straight back here.
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label" for="supplier_bill_number">Supplier bill no.</label>
                                <input class="form-control" id="supplier_bill_number" name="supplier_bill_number" value="{{ old('supplier_bill_number') }}" maxlength="80" placeholder="Their invoice">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label" for="purchased_on">Bill date</label>
                                <input class="form-control" id="purchased_on" name="purchased_on" type="date" value="{{ old('purchased_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="location">Keep pieces at</label>
                                <select class="form-select" id="location" name="location_uuid" required>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->uuid }}" data-branch="{{ $location->branch_id }}" @selected(old('location_uuid') === $location->uuid)>{{ $location->branch?->name }} / {{ $location->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <span class="form-label d-block">Supplier prices by</span>
                                <div class="btn-group w-100" role="group" aria-label="Supplier prices by">
                                    <input class="btn-check" type="radio" name="pricing" id="pricing-rate" value="rate" @checked($pricing !== 'amount')>
                                    <label class="btn btn-outline-secondary" for="pricing-rate">Rate + labour</label>
                                    <input class="btn-check" type="radio" name="pricing" id="pricing-amount" value="amount" @checked($pricing === 'amount')>
                                    <label class="btn btn-outline-secondary" for="pricing-amount">Amount per piece</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white">Your selling charges <span class="text-secondary small">· for every piece on this bill</span></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="default-making">Making</label>
                                <div class="charge-pair">
                                    <select class="form-select" id="default-making" name="default_making_method">
                                        <option value="" data-code="">None</option>
                                        @foreach ($making as $method)
                                            <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" data-unit="{{ $unitLabels['making'][$method->code] ?? '' }}" @selected($defaultMaking === $method->uuid)>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group">
                                        <input class="form-control" id="default-making-value" name="default_making_value" value="{{ old('default_making_value') }}" inputmode="decimal" placeholder="0" aria-label="Making">
                                        <span class="input-group-text" id="default-making-unit"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="default-wastage">Wastage</label>
                                <div class="charge-pair">
                                    <select class="form-select" id="default-wastage" name="default_wastage_method">
                                        <option value="" data-code="">None</option>
                                        @foreach ($wastage as $method)
                                            <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" data-unit="{{ $unitLabels['wastage'][$method->code] ?? '' }}" @selected($defaultWastage === $method->uuid)>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group">
                                        <input class="form-control" id="default-wastage-value" name="default_wastage_value" value="{{ old('default_wastage_value') }}" inputmode="decimal" placeholder="0" aria-label="Wastage">
                                        <span class="input-group-text" id="default-wastage-unit"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">This is what the customer pays on the bill, not the supplier’s labour. A single piece can still be changed under “Selling charges”.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Pieces <span class="text-secondary small" id="piece-count"></span></span>
                        <button class="btn btn-outline-primary btn-sm" id="add-line" type="button"><i class="bi bi-plus-lg"></i> Add piece</button>
                    </div>
                    <div class="card-body" id="line-list"></div>
                    <div class="card-body pt-0">
                        <div class="form-text">“Add piece” copies the name, metal, rate and charges of the last piece, so weigh the next one and type its weight. Net = gross less stone and other weight.</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Supplier bill total</div>
                    <div class="card-body bill-sums">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small mb-1" for="discount">Discount ₹</label>
                                <input class="form-control form-control-sm" id="discount" name="discount" value="{{ old('discount') }}" inputmode="decimal" placeholder="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1" for="gst_percent">GST %</label>
                                <input class="form-control form-control-sm" id="gst_percent" name="gst_percent" value="{{ old('gst_percent', rtrim(rtrim($gstPercent, '0'), '.')) }}" inputmode="decimal">
                            </div>
                        </div>
                        <div class="weigh-quote is-empty" id="total-empty">Enter a piece weight</div>
                        <div id="total-sums" class="d-none">
                            <div class="bill-block">
                                <div class="bill-row bill-row-muted"><span id="sum-count">0 pieces</span><span id="sum-weight">0.000 g</span></div>
                                <div class="bill-row"><span>Pieces value</span><span id="sum-lines">₹0.00</span></div>
                                <div class="bill-row d-none" id="sum-discount-row"><span>Discount</span><span id="sum-discount">₹0.00</span></div>
                                <div class="bill-row"><span id="sum-gst-label">GST</span><span id="sum-gst">₹0.00</span></div>
                                <div class="bill-row bill-row-muted d-none" id="sum-round-row"><span>Round off</span><span id="sum-round">₹0.00</span></div>
                            </div>
                            <div class="bill-grand"><span>Total to supplier</span><strong id="sum-total">₹0.00</strong></div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2">GST is added on top, the way supplier bills show it. Set GST % to 0 for a supplier without GST.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Payment now</span>
                        <button class="btn btn-outline-secondary btn-sm" id="pay-full" type="button">Pay full</button>
                    </div>
                    <div class="card-body">
                        @foreach ([0, 1] as $slot)
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <select class="form-select" name="payments[{{ $slot }}][method]" aria-label="Payment method">
                                        @foreach ($methods as $method)
                                            <option value="{{ $method->value }}" @selected(($oldPayments[$slot]['method'] ?? ($slot === 0 ? 'cash' : 'bank')) === $method->value)>{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4"><input class="form-control pay-amount" name="payments[{{ $slot }}][amount]" value="{{ $oldPayments[$slot]['amount'] ?? '' }}" inputmode="decimal" placeholder="Amount" aria-label="Amount"></div>
                                <div class="col-4"><input class="form-control" name="payments[{{ $slot }}][reference]" value="{{ $oldPayments[$slot]['reference'] ?? '' }}" placeholder="Cheque / UTR" aria-label="Reference"></div>
                            </div>
                        @endforeach
                        <div class="bill-row mt-2"><span>Left to pay later</span><strong id="pay-left">₹0.00</strong></div>
                        <div class="form-text">Leave the amount blank to pay later. It stays on the supplier’s account.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Anything to remember about this bill">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check-lg"></i> Save purchase</button>
            </div>
        </div>
    </form>

    <template id="line-template">
        <div class="stone-box purchase-line">
            <div class="stone-box-head">
                <span class="weigh-section-title mb-0">Piece <span class="line-number"></span></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="line-amount"></span>
                    <button class="bill-remove" type="button" aria-label="Remove piece">×</button>
                </span>
            </div>
            <div class="metal-row purchase-line-top">
                <div><label>Name</label><input class="form-control" data-key="name" maxlength="160" placeholder="Gold chain" required></div>
                <div>
                    <label>Category</label>
                    <select class="form-select" data-key="category_uuid">
                        <option value="">None</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->uuid }}" data-name="{{ $category->name }}">{{ $category->parent ? $category->parent->name.' / '.$category->name : $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Item code</label><input class="form-control text-uppercase" data-key="item_code" maxlength="40" required></div>
                <div><label>HUID</label><input class="form-control text-uppercase" data-key="huid" maxlength="32" placeholder="Optional"></div>
            </div>
            <div class="metal-row purchase-weights">
                <div>
                    <label>Metal</label>
                    <select class="form-select" data-key="metal_uuid">
                        @foreach ($metals as $metal)
                            <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" data-code="{{ $metal->code }}">{{ $metal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Purity</label>
                    <select class="form-select" data-key="purity_uuid">
                        @foreach ($metals as $metal)
                            @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}">{{ $purity->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div><label>Gross g</label><input class="form-control metal-net" data-key="gross_weight" inputmode="decimal" placeholder="0.000" required></div>
                <div><label>Stone g</label><input class="form-control" data-key="stone_weight" inputmode="decimal" placeholder="0.000"></div>
                <div><label>Other g</label><input class="form-control" data-key="other_weight" inputmode="decimal" placeholder="0.000"></div>
                <div><label>Net g</label><input class="form-control line-net" readonly tabindex="-1" placeholder="0.000" aria-label="Net g"></div>
            </div>
            <div class="metal-row purchase-cost">
                <div class="by-rate"><label>Rate ₹/g</label><input class="form-control" data-key="rate_per_gram" inputmode="decimal" placeholder="0"></div>
                <div class="by-rate"><label>Wastage %</label><input class="form-control" data-key="wastage_percent" inputmode="decimal" placeholder="0"></div>
                <div class="by-rate"><label>Labour ₹/g</label><input class="form-control" data-key="labour_per_gram" inputmode="decimal" placeholder="0"></div>
                <div class="by-amount"><label>Amount ₹</label><input class="form-control metal-net" data-key="amount" inputmode="decimal" placeholder="From their bill"></div>
                <div><label>Stones ₹</label><input class="form-control" data-key="stone_value" inputmode="decimal" placeholder="0"></div>
                <div><label>Piece cost</label><input class="form-control line-cost" readonly tabindex="-1" placeholder="₹0" aria-label="Piece cost"></div>
            </div>
            <details class="purchase-selling">
                <summary>Selling charges <span class="selling-summary"></span></summary>
                <div class="purchase-selling-grid">
                    <div>
                        <label>Selling making</label>
                        <div class="charge-pair">
                            <select class="form-select" data-key="making_method_uuid">
                                <option value="" data-code="">None</option>
                                @foreach ($making as $method)
                                    <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" data-unit="{{ $unitLabels['making'][$method->code] ?? '' }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                            <div class="input-group">
                                <input class="form-control" data-key="making_value" inputmode="decimal" placeholder="0" aria-label="Selling making value">
                                <span class="input-group-text making-unit"></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label>Selling wastage</label>
                        <div class="charge-pair">
                            <select class="form-select" data-key="wastage_method_uuid">
                                <option value="" data-code="">None</option>
                                @foreach ($wastage as $method)
                                    <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" data-unit="{{ $unitLabels['wastage'][$method->code] ?? '' }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                            <div class="input-group">
                                <input class="form-control" data-key="wastage_value" inputmode="decimal" placeholder="0" aria-label="Selling wastage value">
                                <span class="input-group-text wastage-unit"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </template>

    <script type="application/json" id="purchase-config">{!! json_encode([
        'round' => $roundRupee,
        'nextCode' => $nextCode,
        'rows' => array_values(array_filter((array) old('lines', []), 'is_array')),
        'errors' => array_keys($errors->getMessages()),
        'rates' => $rates->map(fn ($rate) => [
            'metal_type_id' => $rate->metal_type_id,
            'purity_id' => $rate->purity_id,
            'branch_id' => $rate->branch_id,
            'rate_per_gram' => (string) $rate->rate_per_gram,
        ])->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script>
        (() => {
            const config = JSON.parse(document.getElementById('purchase-config').textContent);
            const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
            const form = document.getElementById('purchase-form');
            const list = document.getElementById('line-list');
            const template = document.getElementById('line-template');
            const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
            const number = (value) => Number(String(value || '').replace(/,/g, '')) || 0;
            const selected = (field) => field.options[field.selectedIndex];
            const field = (row, key) => row.querySelector('[data-key="' + key + '"]');
            let counter = 0;
            let total = 0;

            function byAmount() {
                return document.getElementById('pricing-amount').checked;
            }

            function syncPurities(row) {
                const metal = field(row, 'metal_uuid');
                const purity = field(row, 'purity_uuid');
                const metalId = selected(metal).dataset.id;
                Array.from(purity.options).forEach((option) => {
                    const show = option.dataset.metal === metalId;
                    option.hidden = !show;
                    option.disabled = !show;
                });
                if (!purity.value || selected(purity)?.disabled) {
                    const options = Array.from(purity.options).filter((option) => !option.disabled);
                    const preferred = options.find((option) => option.text === '22K') || options[0];
                    if (preferred) preferred.selected = true;
                }
            }

            function todayRate(row) {
                const metalId = selected(field(row, 'metal_uuid')).dataset.id;
                const purityId = selected(field(row, 'purity_uuid'))?.dataset.id;
                const branchId = selected(document.getElementById('location'))?.dataset.branch;
                const rows = config.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
                const rate = rows.find((item) => String(item.branch_id) === String(branchId)) || rows.find((item) => item.branch_id === null);
                return rate ? Number(rate.rate_per_gram) : 0;
            }

            function fillRate(row) {
                const rate = field(row, 'rate_per_gram');
                if (rate.dataset.touched) return;
                const value = todayRate(row);
                rate.value = value > 0 ? String(value) : '';
                rate.title = value > 0 ? 'Today’s rate. Change it to the rate on the supplier bill.' : 'No rate saved for this purity. Type the supplier rate.';
            }

            function nextCode() {
                let highest = 0;
                const base = /^PC(\d+)$/.exec(config.nextCode);
                if (base) highest = Number(base[1]) - 1;
                list.querySelectorAll('[data-key="item_code"]').forEach((input) => {
                    const match = /^PC(\d+)$/.exec(input.value.trim().toUpperCase());
                    if (match) highest = Math.max(highest, Number(match[1]));
                });
                return 'PC' + String(highest + 1).padStart(4, '0');
            }

            function syncUnit(row, kind) {
                const option = selected(field(row, kind + '_method_uuid'));
                const value = field(row, kind + '_value');
                row.querySelector('.' + kind + '-unit').textContent = option.dataset.unit || '—';
                value.disabled = !option.value;
                if (!option.value) value.value = '';
            }

            function applyDefaults(row, force) {
                ['making', 'wastage'].forEach((kind) => {
                    const select = field(row, kind + '_method_uuid');
                    const value = field(row, kind + '_value');
                    if (!force && (select.dataset.touched || value.dataset.touched)) return;
                    select.value = document.getElementById('default-' + kind).value;
                    value.value = document.getElementById('default-' + kind + '-value').value;
                    syncUnit(row, kind);
                });
            }

            function sellingSummary(row) {
                const parts = ['making', 'wastage'].map((kind) => {
                    const option = selected(field(row, kind + '_method_uuid'));
                    if (!option.value) return kind + ' none';
                    const value = field(row, kind + '_value').value.trim();
                    return kind + ' ' + (value === '' ? '0' : value) + ' ' + (option.dataset.unit || '');
                });
                row.querySelector('.selling-summary').textContent = '· ' + parts.join(' · ');
            }

            function addLine(values, copyFrom) {
                const index = counter++;
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('.purchase-line');
                row.querySelectorAll('[data-key]').forEach((input) => {
                    input.name = 'lines[' + index + '][' + input.dataset.key + ']';
                    input.id = 'line-' + index + '-' + input.dataset.key;
                    const label = input.closest('div:not(.input-group):not(.charge-pair)')?.querySelector('label');
                    if (label && !label.htmlFor) label.htmlFor = input.id;
                });
                list.appendChild(fragment);

                const metal = field(row, 'metal_uuid');
                const gold = Array.from(metal.options).find((option) => option.dataset.code === 'GOLD');
                if (gold) gold.selected = true;

                const source = values || {};
                const copy = copyFrom ? {} : null;
                if (copyFrom) {
                    ['name', 'category_uuid', 'metal_uuid', 'purity_uuid', 'rate_per_gram', 'wastage_percent', 'labour_per_gram', 'making_method_uuid', 'making_value', 'wastage_method_uuid', 'wastage_value'].forEach((key) => {
                        copy[key] = field(copyFrom, key).value;
                    });
                }
                const start = copy || source;
                if (start.metal_uuid) metal.value = start.metal_uuid;
                field(row, 'purity_uuid').value = '';
                syncPurities(row);
                Object.entries(start).forEach(([key, value]) => {
                    const input = field(row, key);
                    if (input && key !== 'metal_uuid' && value !== null && value !== undefined) input.value = value;
                });
                if (copyFrom) {
                    ['rate_per_gram', 'making_method_uuid', 'making_value', 'wastage_method_uuid', 'wastage_value'].forEach((key) => {
                        if (field(copyFrom, key).dataset.touched) field(row, key).dataset.touched = '1';
                    });
                }
                if (!start.item_code) field(row, 'item_code').value = nextCode();
                if (values) {
                    ['rate_per_gram', 'making_method_uuid', 'making_value', 'wastage_method_uuid', 'wastage_value'].forEach((key) => {
                        field(row, key).dataset.touched = '1';
                    });
                } else if (!copyFrom) {
                    applyDefaults(row, true);
                }
                syncUnit(row, 'making');
                syncUnit(row, 'wastage');
                if (!values && !copy?.rate_per_gram) fillRate(row);

                row.addEventListener('input', (event) => {
                    if (event.target.dataset.key) event.target.dataset.touched = '1';
                });
                row.addEventListener('change', (event) => {
                    const key = event.target.dataset.key;
                    if (key) event.target.dataset.touched = '1';
                    if (key === 'metal_uuid') {
                        field(row, 'purity_uuid').value = '';
                        syncPurities(row);
                    }
                    if (key === 'metal_uuid' || key === 'purity_uuid') {
                        delete field(row, 'rate_per_gram').dataset.touched;
                        fillRate(row);
                    }
                    if (key === 'making_method_uuid') syncUnit(row, 'making');
                    if (key === 'wastage_method_uuid') syncUnit(row, 'wastage');
                    if (key === 'category_uuid' && !field(row, 'name').dataset.typed) {
                        const option = selected(event.target);
                        if (option.value) field(row, 'name').value = (selected(field(row, 'metal_uuid')).text + ' ' + option.dataset.name).trim();
                    }
                });
                field(row, 'name').addEventListener('input', () => { field(row, 'name').dataset.typed = '1'; });
                row.querySelector('.bill-remove').addEventListener('click', () => {
                    if (list.querySelectorAll('.purchase-line').length === 1) return;
                    row.remove();
                    render();
                });
                return row;
            }

            function lineCost(row) {
                const gross = number(field(row, 'gross_weight').value);
                const net = gross - number(field(row, 'stone_weight').value) - number(field(row, 'other_weight').value);
                const netField = row.querySelector('.line-net');
                netField.value = gross > 0 ? net.toFixed(3) : '';
                netField.classList.toggle('is-invalid', gross > 0 && net <= 0);
                if (gross <= 0 || net <= 0) return { gross, net: 0, cost: 0 };
                if (byAmount()) return { gross, net, cost: round2(number(field(row, 'amount').value)) };
                const rate = number(field(row, 'rate_per_gram').value);
                const metal = net * rate;
                const wastage = net * number(field(row, 'wastage_percent').value) / 100 * rate;
                const labour = net * number(field(row, 'labour_per_gram').value);
                return { gross, net, cost: round2(metal + wastage + labour + number(field(row, 'stone_value').value)) };
            }

            function render() {
                const amountMode = byAmount();
                const rows = Array.from(list.querySelectorAll('.purchase-line'));
                let sum = 0;
                let gross = 0;
                let net = 0;
                let counted = 0;
                rows.forEach((row, position) => {
                    row.querySelector('.line-number').textContent = String(position + 1);
                    row.querySelector('.bill-remove').hidden = rows.length === 1;
                    row.querySelectorAll('.by-rate').forEach((cell) => { cell.hidden = amountMode; });
                    row.querySelectorAll('.by-amount').forEach((cell) => { cell.hidden = !amountMode; });
                    row.querySelector('.purchase-cost').classList.toggle('is-amount', amountMode);
                    syncPurities(row);
                    sellingSummary(row);
                    const line = lineCost(row);
                    row.querySelector('.line-cost').value = line.cost > 0 ? money.format(line.cost) : '';
                    row.querySelector('.line-amount').textContent = line.cost > 0 ? money.format(line.cost) : '';
                    if (line.net > 0) {
                        counted += 1;
                        gross += line.gross;
                        net += line.net;
                        sum += line.cost;
                    }
                });
                document.getElementById('piece-count').textContent = '· ' + rows.length + (rows.length === 1 ? ' piece' : ' pieces');

                const empty = document.getElementById('total-empty');
                const sums = document.getElementById('total-sums');
                sum = round2(sum);
                if (counted === 0 || sum <= 0) {
                    empty.textContent = counted === 0 ? 'Enter a piece weight' : (amountMode ? 'Type the amount for each piece' : 'Type the supplier rate');
                    empty.classList.remove('d-none');
                    sums.classList.add('d-none');
                    total = 0;
                    renderPay();
                    return;
                }
                const discount = Math.min(sum, Math.max(0, number(document.getElementById('discount').value)));
                const after = round2(sum - discount);
                const gst = Math.max(0, number(document.getElementById('gst_percent').value));
                const tax = round2(after * gst / 100);
                const exact = round2(after + tax);
                total = config.round ? Math.round(exact) : exact;
                const roundOff = round2(total - exact);

                empty.classList.add('d-none');
                sums.classList.remove('d-none');
                document.getElementById('sum-count').textContent = counted + (counted === 1 ? ' piece' : ' pieces') + ' · ' + net.toFixed(3) + ' g net';
                document.getElementById('sum-weight').textContent = gross.toFixed(3) + ' g gross';
                document.getElementById('sum-lines').textContent = money.format(sum);
                document.getElementById('sum-discount-row').classList.toggle('d-none', discount <= 0);
                document.getElementById('sum-discount').textContent = '− ' + money.format(discount);
                document.getElementById('sum-gst-label').textContent = 'GST ' + gst + '%';
                document.getElementById('sum-gst').textContent = money.format(tax);
                document.getElementById('sum-round-row').classList.toggle('d-none', roundOff === 0);
                document.getElementById('sum-round').textContent = money.format(roundOff);
                document.getElementById('sum-total').textContent = money.format(total);
                renderPay();
            }

            function renderPay() {
                let paid = 0;
                document.querySelectorAll('.pay-amount').forEach((input) => { paid += number(input.value); });
                const left = round2(total - paid);
                const field = document.getElementById('pay-left');
                field.textContent = money.format(Math.max(0, left));
                field.classList.toggle('text-danger', left < 0);
                if (left < 0) field.textContent = 'Paid ' + money.format(-left) + ' too much';
            }

            function renderSupplier() {
                const option = selected(document.getElementById('supplier'));
                const note = document.getElementById('supplier-balance');
                if (!option || !option.value) return;
                const payable = Number(option.dataset.payable || 0);
                note.textContent = payable > 0
                    ? 'You already owe this supplier ' + money.format(payable) + '.'
                    : (payable < 0 ? 'This supplier owes you ' + money.format(-payable) + '.' : 'Nothing due to this supplier now.');
            }

            function renderDefaultUnits() {
                ['making', 'wastage'].forEach((kind) => {
                    const option = selected(document.getElementById('default-' + kind));
                    const value = document.getElementById('default-' + kind + '-value');
                    document.getElementById('default-' + kind + '-unit').textContent = option.dataset.unit || '—';
                    value.disabled = !option.value;
                    if (!option.value) value.value = '';
                });
            }

            ['default-making', 'default-making-value', 'default-wastage', 'default-wastage-value'].forEach((id) => {
                ['input', 'change'].forEach((type) => document.getElementById(id).addEventListener(type, () => {
                    renderDefaultUnits();
                    list.querySelectorAll('.purchase-line').forEach((row) => applyDefaults(row, false));
                    render();
                }));
            });
            document.getElementById('location').addEventListener('change', () => {
                list.querySelectorAll('.purchase-line').forEach(fillRate);
            });
            document.getElementById('supplier').addEventListener('change', renderSupplier);
            document.getElementById('add-line').addEventListener('click', () => {
                const rows = list.querySelectorAll('.purchase-line');
                const row = addLine(null, rows[rows.length - 1] || null);
                render();
                field(row, 'gross_weight').focus();
            });
            document.getElementById('pay-full').addEventListener('click', () => {
                const inputs = document.querySelectorAll('.pay-amount');
                inputs.forEach((input, index) => { input.value = index === 0 && total > 0 ? total.toFixed(2) : ''; });
                renderPay();
            });
            form.addEventListener('input', render);
            form.addEventListener('change', render);

            renderDefaultUnits();
            if (config.rows.length) {
                config.rows.forEach((values) => addLine(values, null));
            } else {
                addLine(null, null);
            }
            config.errors.forEach((key) => {
                const match = /^lines\.(\d+)\.(\w+)$/.exec(key);
                const input = match ? document.getElementById('line-' + match[1] + '-' + match[2]) : null;
                if (input) {
                    input.classList.add('is-invalid');
                    input.closest('details')?.setAttribute('open', '');
                }
            });
            renderSupplier();
            render();
        })();
    </script>
@endpush
