@extends('layouts.app')

@section('title', 'New bill')

@inject('billScan', 'App\Services\Commerce\BillScanService')
@inject('shopContext', 'App\Support\CompanyContext')

@section('content')
    <h1 class="page-title h3 mb-3">New bill</h1>
    @if ($order)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Delivering order {{ $order->number }}</strong> for {{ $order->customer?->name }} · {{ $order->description }}<br>
                <span class="small">{{ $order->metalType?->name }} {{ $order->purity?->name }} is billed at the locked rate {{ $money((string) $order->rate_per_gram) }} / g. The advance of {{ $money($order->advanceHeld()) }} is taken off this bill.</span>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('sales.create') }}">Bill without the order</a>
        </div>
    @endif
    <form method="POST" action="{{ route('sales.store') }}" id="bill-form" autocomplete="off">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid', $order?->customer?->uuid ?? request()->query('customer')) }}">
        @if ($order)
            <input type="hidden" name="order_uuid" value="{{ $order->uuid }}">
        @endif
        <div id="bill-piece-inputs"></div>
        <div class="row g-3">
            <div class="col-lg-7">
                @include('commerce.partials.customer-picker', ['addLabel' => 'Add to bill'])
                <div class="card mb-3 scan-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-upc-scan"></i> Scan tag</span>
                        <small class="text-secondary">Scanner or keyboard, then Enter</small>
                    </div>
                    <div class="card-body">
                        <label class="form-label" for="scan-code">Tag code</label>
                        <div class="input-group">
                            <input class="form-control scan-input" id="scan-code" maxlength="64" placeholder="Scan the tag, or type the code and press Enter" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="go">
                            <button class="btn btn-primary" id="scan-add" type="button"><i class="bi bi-plus-lg"></i> Add</button>
                        </div>
                        <div class="scan-status" id="scan-status" role="status" aria-live="polite"></div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Weigh and bill</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Piece</div>
                        <div class="bill-products" id="weigh-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="weigh-name">Name</label>
                            <input class="form-control" id="weigh-name" placeholder="Ring" value="{{ $order?->description }}">
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Weight</div>
                            <div class="stone-box mt-0">
                                <div class="stone-box-head">
                                    <span class="weigh-step-label">1 · Metal</span>
                                </div>
                                <div class="metal-row">
                                    <div>
                                        <label for="weigh-metal">Metal</label>
                                        <select class="form-select" id="weigh-metal">
                                            @foreach ($metals as $metal)
                                                <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected($order && (int) $order->metal_type_id === (int) $metal->id)>{{ $metal->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="weigh-purity">Purity</label>
                                        <select class="form-select" id="weigh-purity">
                                            @foreach ($metals as $metal)
                                                @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                                    <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected($order && (int) $order->purity_id === (int) $purity->id)>{{ $purity->name }}</option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="weigh-net">Net weight g</label>
                                        <input class="form-control metal-net" id="weigh-net" inputmode="decimal" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="weigh-metal-rate">Rate / g</label>
                                        <input class="form-control" id="weigh-metal-rate" readonly tabindex="-1" placeholder="—">
                                    </div>
                                    <div>
                                        <label for="weigh-metal-value">Value</label>
                                        <input class="form-control" id="weigh-metal-value" readonly tabindex="-1" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="stone-box">
                                <div class="stone-box-head">
                                    <span class="weigh-step-label">2 · Stones / diamond</span>
                                    <button class="btn btn-link btn-sm p-0" id="stone-add" type="button"><i class="bi bi-plus-circle"></i> Add stone</button>
                                </div>
                                <div id="stone-list"></div>
                            </div>
                            <div class="weigh-pair mt-3">
                                <div class="weight-field">
                                    <label for="weigh-gross">3 · Gross = net + stones + other</label>
                                    <div class="weight-input">
                                        <input class="form-control bill-weight" id="weigh-gross" inputmode="decimal" placeholder="0.000" data-auto="1">
                                        <span>g</span>
                                    </div>
                                </div>
                                <div class="weight-field">
                                    <label for="weigh-other-g">Other (lac, wax) g</label>
                                    <input class="form-control" id="weigh-other-g" inputmode="decimal" value="0">
                                </div>
                            </div>
                            <div class="form-text" id="weigh-gross-hint">Gross fills itself. Type the scale gross to check it.</div>
                        </div>
                        <div class="weigh-section">
                            <div class="charges-head">
                                <div class="weigh-section-title">Charges</div>
                                <select class="form-select form-select-sm" id="making-mode" name="making_mode" aria-label="GST on making charge">
                                    <option value="inside" @selected($makingMode === 'inside')>Making inside jewellery GST</option>
                                    <option value="separate" @selected($makingMode === 'separate')>Making with own GST {{ $makingGstPercent }}%</option>
                                    <option value="processing" @selected($makingMode === 'processing')>Processing charge, no GST</option>
                                </select>
                            </div>
                            <div class="weigh-metrics">
                                <div class="weight-field">
                                    <label for="weigh-making" id="weigh-making-label">Making</label>
                                    <div class="input-group">
                                        <select class="form-select charge-method" id="weigh-making">
                                            <option value="">None</option>
                                            @foreach ($making as $method)
                                                <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'percentage')>{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control charge-value" id="weigh-making-value" inputmode="decimal" value="0" aria-label="Making amount">
                                    </div>
                                </div>
                                <div class="weight-field">
                                    <label for="weigh-wastage">Wastage</label>
                                    <div class="input-group">
                                        <select class="form-select charge-method" id="weigh-wastage">
                                            <option value="">None</option>
                                            @foreach ($wastage as $method)
                                                <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'percentage')>{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control charge-value" id="weigh-wastage-value" inputmode="decimal" value="0" aria-label="Wastage amount">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 {{ $locations->count() < 2 ? 'd-none' : '' }}">
                                <label class="form-label" for="weigh-location">Location</label>
                                <select class="form-select" id="weigh-location">
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->uuid }}" data-branch="{{ $location->branch_id }}">{{ $location->branch?->name }} / {{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Price</div>
                            <div class="weigh-quote is-empty" id="weigh-price">Enter the weight</div>
                            <div class="price-table" id="weigh-split"></div>
                            <div class="text-danger small d-none mt-2" id="weigh-error"></div>
                            <div class="weigh-editing d-none" id="weigh-editing"></div>
                            <button class="btn btn-primary w-100 mt-3" id="weigh-add" type="button"><i class="bi bi-plus-lg"></i> Add to bill</button>
                            <button class="btn btn-outline-secondary w-100 mt-2 d-none" id="weigh-cancel" type="button"><i class="bi bi-x-lg"></i> Cancel edit</button>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Tagged piece in stock</div>
                    <div class="card-body">
                        <label class="form-label" for="piece-search">Code or barcode</label>
                        <input class="form-control" id="piece-search" value="{{ $search }}" placeholder="R001 or a piece name" autocomplete="off">
                        <div class="bill-results mt-2" id="piece-results"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">This bill</div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Piece</th><th class="text-end">Amount</th><th></th></tr></thead>
                            <tbody id="bill-lines"><tr><td colspan="3" class="text-secondary">No pieces added yet.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="card-body bill-sums">
                        <div class="bill-settings">
                            <label class="form-label mb-0" for="discount">Discount ₹</label>
                            <input class="form-control form-control-sm" id="discount" name="discount" inputmode="decimal" value="{{ old('discount', '0') }}">
                        </div>
                        <div class="bill-block">
                            <div class="bill-row bill-row-muted"><span>Net weight</span><span id="bill-net-weight">0.000 g</span></div>
                            <div class="bill-row"><span>Metal value</span><span id="bill-metal">0.00</span></div>
                            <div class="bill-row d-none" id="bill-stone-row"><span>Stones</span><span id="bill-stone">0.00</span></div>
                            <div class="bill-row d-none" id="bill-wastage-row"><span>Wastage</span><span id="bill-wastage">0.00</span></div>
                            <div class="bill-row d-none" id="bill-making-row"><span id="bill-making-label">Making</span><span id="bill-making">0.00</span></div>
                            <div class="bill-row bill-row-sub"><span>Pieces total</span><span id="bill-subtotal">0.00</span></div>
                        </div>
                        <div class="bill-block">
                            <div class="bill-row d-none" id="bill-discount-row"><span>Discount</span><span id="bill-discount">0.00</span></div>
                            <div class="bill-row"><span id="bill-tax-label">GST {{ $gstPercent }}%</span><span id="bill-tax">0.00</span></div>
                            <div class="bill-row d-none" id="bill-making-tax-row"><span id="bill-making-tax-label">GST {{ $makingGstPercent }}% on making</span><span id="bill-making-tax">0.00</span></div>
                            <div class="bill-row d-none" id="bill-round-row"><span>Round off</span><span id="bill-round">0.00</span></div>
                        </div>
                        <div class="bill-grand"><span>Total</span><strong id="bill-total">0.00</strong></div>
                        @if ($order)
                            <div class="bill-row"><span>Advance {{ $order->number }}</span><span id="bill-advance">0.00</span></div>
                        @endif
                        <div class="bill-credit d-none" id="bill-credit-row">
                            <label class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="use-credit-toggle" @checked((float) old('use_credit', 1) > 0)>
                                <span class="form-check-label">Old gold / credit <small id="bill-credit-available"></small></span>
                            </label>
                            <span id="bill-credit">− 0.00</span>
                        </div>
                        <input type="hidden" name="use_credit" id="use-credit" value="0">
                        <div class="bill-grand {{ $order ? '' : 'd-none' }}" id="bill-pay-now-row"><span>To pay now</span><strong id="bill-pay-now">0.00</strong></div>
                        @error('use_credit')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        <p class="text-secondary small d-none mb-2" id="bill-note"></p>
                        <label class="form-label" for="notes">Note</label>
                        <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}">
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Payment</span>
                        <button class="btn btn-outline-secondary btn-sm" id="use-total" type="button">Cash</button>
                    </div>
                    <div class="card-body">
                        @foreach ([0, 1, 2] as $slot)
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <select class="form-select" name="payments[{{ $slot }}][method]">
                                        <option value="cash">Cash</option>
                                        <option value="upi">UPI</option>
                                        <option value="card">Card</option>
                                        <option value="bank">Bank</option>
                                        <option value="cheque">Cheque</option>
                                    </select>
                                </div>
                                <div class="col-4"><input class="form-control bill-pay" name="payments[{{ $slot }}][amount]" value="{{ old('payments.'.$slot.'.amount') }}" placeholder="Amount"></div>
                                <div class="col-4"><input class="form-control" name="payments[{{ $slot }}][reference]" value="{{ old('payments.'.$slot.'.reference') }}" placeholder="Reference"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Save invoice</button>
            </div>
        </div>
    </form>

    @include('commerce.partials.customer-modals', ['document' => 'bill'])

    <script type="application/json" id="bill-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
        'gst' => (float) $gstPercent,
        'makingGst' => (float) $makingGstPercent,
        'exclusive' => $taxExclusive,
        'round' => $roundRupee,
        'order' => $order ? [
            'metal_type_id' => $order->metal_type_id,
            'purity_id' => $order->purity_id,
            'rate' => (string) $order->rate_per_gram,
            'advance' => (float) $order->advanceHeld(),
        ] : null,
        'customers' => $customers->map(fn ($customer) => [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'code' => $customer->code,
            'mobile' => $customer->mobile,
            'walkin' => (bool) $customer->is_system,
            'credit' => (float) ($credits[$customer->id] ?? 0),
        ])->values(),
        'scanUrl' => route('sales.scan'),
        'orderUuid' => $order?->uuid,
        'pieces' => $rows->map(fn ($row) => $billScan->present($row['item'], $row['quote'], $shopContext->company()))->values(),
        'rates' => $rates->map(fn ($rate) => [
            'metal_type_id' => $rate->metal_type_id,
            'purity_id' => $rate->purity_id,
            'branch_id' => $rate->branch_id,
            'rate_per_gram' => (string) $rate->rate_per_gram,
        ])->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
    <script>
        const bill = JSON.parse(document.getElementById('bill-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const stockLines = [];
        const freshLines = [];
        let editingIndex = null;
        let weighDefaults = null;
        let customer = bill.customers.find((row) => row.uuid === document.getElementById('customer-uuid').value) || null;

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function option(id) {
            const field = document.getElementById(id);
            return field.options[field.selectedIndex];
        }

        function currentRate(metalId, purityId, branchId) {
            if (bill.order && String(bill.order.metal_type_id) === String(metalId) && String(bill.order.purity_id) === String(purityId)) {
                return { rate_per_gram: bill.order.rate };
            }
            const rows = bill.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
            return rows.find((rate) => String(rate.branch_id) === String(branchId))
                || rows.find((rate) => rate.branch_id === null)
                || null;
        }

        function syncPurities() {
            const metal = option('weigh-metal');
            const purity = document.getElementById('weigh-purity');
            if (!metal || !purity) return;
            Array.from(purity.options).forEach((row) => {
                const show = row.dataset.metal === metal.dataset.id;
                row.hidden = !show;
                row.disabled = !show;
            });
            const location = document.getElementById('weigh-location');
            const branch = location && location.selectedIndex >= 0 ? location.options[location.selectedIndex].dataset.branch : '';
            const visible = Array.from(purity.options).filter((row) => !row.disabled);
            const selected = visible.find((row) => row.selected);
            const priced = visible.find((row) => currentRate(metal.dataset.id, row.dataset.id, branch));
            const next = (selected && currentRate(metal.dataset.id, selected.dataset.id, branch)) ? selected : (priced || visible[0]);
            if (next) next.selected = true;
        }

        function emptyQuote(message) {
            const preview = document.getElementById('weigh-price');
            preview.className = 'weigh-quote is-empty';
            preview.textContent = message;
            document.getElementById('weigh-split').replaceChildren();
            return null;
        }

        function collectStones() {
            return Array.from(document.querySelectorAll('#stone-list .stone-row')).map((row) => {
                const unit = row.querySelector('.stone-unit').value;
                return {
                    name: row.querySelector('.stone-name').value.trim(),
                    weight: row.querySelector('.stone-weight').value || '0',
                    value: row.querySelector('.stone-value').value || '0',
                    rate: unit === 'fixed' ? '' : row.querySelector('.stone-rate').value,
                    rate_unit: unit,
                };
            }).filter((stone) => stone.name !== '' || Number(stone.weight) > 0 || Number(stone.value) > 0);
        }

        function syncStoneRow(row) {
            const unit = row.querySelector('.stone-unit').value;
            const rate = row.querySelector('.stone-rate');
            const value = row.querySelector('.stone-value');
            const fixed = unit === 'fixed';
            rate.disabled = fixed;
            if (fixed) rate.value = '';
            value.readOnly = !fixed;
            if (!fixed) {
                const weight = Number(row.querySelector('.stone-weight').value || 0);
                const amount = weight * (unit === 'carat' ? 5 : 1) * Number(rate.value || 0);
                value.value = rate.value === '' ? '' : round2(amount).toFixed(2);
            }
        }

        function stoneWeight() {
            return collectStones().reduce((sum, stone) => sum + Number(stone.weight || 0), 0);
        }

        function addStoneRow(stone = null) {
            const row = document.createElement('div');
            row.className = 'stone-row';
            [
                ['Name', 'stone-name', 'Stone name', 'text'],
                ['Weight g', 'stone-weight', '0.000', 'decimal'],
                ['Per', 'stone-unit', '', 'unit'],
                ['Rate', 'stone-rate', '0', 'decimal'],
                ['Value', 'stone-value', '0', 'decimal'],
            ].forEach(([labelText, className, placeholder, mode]) => {
                const field = document.createElement('div');
                const label = document.createElement('label');
                label.textContent = labelText;
                let input;
                if (mode === 'unit') {
                    input = document.createElement('select');
                    input.className = 'form-select ' + className;
                    [['gram', 'Gram'], ['carat', 'Carat'], ['fixed', 'Fixed']].forEach(([value, text]) => input.add(new Option(text, value)));
                } else {
                    input = document.createElement('input');
                    input.className = 'form-control ' + className;
                    input.placeholder = placeholder;
                    if (mode === 'decimal') input.inputMode = 'decimal';
                }
                field.append(label, input);
                row.appendChild(field);
            });
            row.addEventListener('input', () => syncStoneRow(row));
            row.addEventListener('change', () => syncStoneRow(row));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'bill-remove';
            remove.setAttribute('aria-label', 'Remove stone');
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                row.remove();
                if (!document.querySelector('#stone-list .stone-row')) addStoneRow();
                previewWeigh();
            });
            row.appendChild(remove);
            document.getElementById('stone-list').appendChild(row);
            if (stone) {
                row.querySelector('.stone-name').value = stone.name || '';
                row.querySelector('.stone-weight').value = Number(stone.weight) > 0 ? stone.weight : '';
                row.querySelector('.stone-unit').value = stone.rate_unit || 'gram';
                row.querySelector('.stone-rate').value = stone.rate || '';
                row.querySelector('.stone-value').value = stone.value || '';
            }
            syncStoneRow(row);
        }

        function resetStones(stones = []) {
            document.getElementById('stone-list').replaceChildren();
            if (stones.length === 0) addStoneRow();
            stones.forEach((stone) => addStoneRow(stone));
        }

        function showSplit(target, rows) {
            target.replaceChildren();
            rows.forEach((row) => {
                const line = document.createElement('div');
                line.className = 'piece-split-row';
                const label = document.createElement('span');
                label.textContent = row.label;
                const value = document.createElement('strong');
                value.textContent = row.value;
                line.append(label, value);
                target.appendChild(line);
            });
        }

        function showBreakdown(rows, total, weights) {
            const preview = document.getElementById('weigh-price');
            preview.className = 'd-none';
            preview.textContent = '';
            const target = document.getElementById('weigh-split');
            target.replaceChildren();
            const cells = (className, values) => {
                const row = document.createElement('div');
                row.className = className;
                values.forEach((value) => {
                    const cell = document.createElement('span');
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                target.appendChild(row);
                return row;
            };
            cells('price-row price-head', ['Item', 'Weight', 'Rate', 'Amount']);
            rows.forEach((row) => cells('price-row', [row.name, row.weight || '—', row.rate, money.format(row.amount)]));
            cells('price-row price-total', ['Amount', '', '', money.format(total)]);
            const parts = ['Net ' + weights.net.toFixed(3)];
            if (weights.stone > 0) parts.push('Stones ' + weights.stone.toFixed(3));
            if (weights.other > 0) parts.push('Other ' + weights.other.toFixed(3));
            const gross = document.createElement('div');
            gross.className = 'price-gross';
            const label = document.createElement('strong');
            label.textContent = 'Gross ' + weights.gross.toFixed(3) + ' g';
            const detail = document.createElement('span');
            detail.textContent = '= ' + parts.join(' + ') + ' g';
            gross.append(label, detail);
            target.appendChild(gross);
        }

        function previewWeigh() {
            syncPurities();
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            const location = option('weigh-location');
            const grossInput = document.getElementById('weigh-gross');
            const typedNet = Number(document.getElementById('weigh-net').value || 0);
            const stones = collectStones();
            const deduct = stoneWeight() + Number(document.getElementById('weigh-other-g').value || 0);
            grossInput.placeholder = typedNet > 0 ? (typedNet + deduct).toFixed(3) : '0.000';
            if (grossInput.dataset.auto === '1') {
                grossInput.value = typedNet > 0 ? (typedNet + deduct).toFixed(3) : '';
            }
            grossInput.classList.toggle('is-auto', grossInput.dataset.auto === '1' && typedNet > 0);
            const typedGross = Number(grossInput.value || 0);
            const shownRate = metal && purity && location ? currentRate(metal.dataset.id, purity.dataset.id, location.dataset.branch) : null;
            const metalNet = typedNet > 0 ? typedNet : typedGross - deduct;
            document.getElementById('weigh-metal-rate').value = shownRate ? money.format(Number(shownRate.rate_per_gram)) : '';
            document.getElementById('weigh-metal-value').value = shownRate && metalNet > 0 ? money.format(round2(metalNet * Number(shownRate.rate_per_gram))) : '';
            if (typedGross <= 0 && typedNet <= 0) {
                return emptyQuote('Enter the gross or net weight');
            }
            const gross = typedGross > 0 ? typedGross : typedNet + deduct;
            const net = typedNet > 0 ? typedNet : typedGross - deduct;
            if (typedGross > 0 && typedNet > 0 && Math.abs(typedGross - (typedNet + deduct)) >= 0.0005) {
                return emptyQuote('Gross ' + typedGross.toFixed(3) + ' g does not match net ' + typedNet.toFixed(3) + ' g + stones and other ' + deduct.toFixed(3) + ' g = ' + (typedNet + deduct).toFixed(3) + ' g');
            }
            if (net <= 0) {
                return emptyQuote('Net weight must be more than zero');
            }
            if (!metal || !purity || !location) {
                return emptyQuote('Choose metal and purity');
            }
            const rate = currentRate(metal.dataset.id, purity.dataset.id, location.dataset.branch);
            if (!rate) {
                return emptyQuote('Set today\'s ' + metal.text + ' ' + purity.text + ' rate');
            }
            const rateValue = Number(rate.rate_per_gram);
            const making = option('weigh-making');
            const wastage = option('weigh-wastage');
            const makingCode = making && making.value ? (making.dataset.code || 'fixed') : 'fixed';
            const wastageCode = wastage && wastage.value ? (wastage.dataset.code || 'fixed') : 'fixed';
            const makingValue = making && making.value ? Number(document.getElementById('weigh-making-value').value || 0) : 0;
            const wastageValue = wastage && wastage.value ? Number(document.getElementById('weigh-wastage-value').value || 0) : 0;
            const stone = stones.reduce((sum, row) => sum + Number(row.value || 0), 0);
            const metalAmount = round2(net * rateValue);
            const wastageAmount = wastageCode === 'percentage' ? net * wastageValue / 100 * rateValue : (wastageCode === 'per_gram' ? net * wastageValue : wastageValue);
            const makingAmount = makingCode === 'per_gram' ? net * makingValue : (makingCode === 'percentage' ? metalAmount * makingValue / 100 : makingValue);
            const line = round2(metalAmount + wastageAmount + makingAmount + stone);
            const makingLabel = document.getElementById('making-mode').value === 'processing' ? 'Processing charge' : 'Making';
            const chargeRow = (name, code, value, amount, kind) => ({
                kind,
                name,
                weight: code === 'per_gram' ? net.toFixed(3) + ' g' : '',
                rate: code === 'percentage' ? value + '% on metal' : (code === 'per_gram' ? money.format(value) + '/g' : 'Fixed'),
                amount,
            });
            const stoneRows = stones.filter((row) => row.name !== '').map((row) => {
                const weight = Number(row.weight || 0);
                const carat = row.rate_unit === 'carat' && row.rate;
                return {
                    name: row.name,
                    weight: weight > 0 ? weight.toFixed(3) + ' g' + (carat ? ' · ' + (weight * 5).toFixed(2) + ' ct' : '') : '',
                    rate: row.rate ? money.format(Number(row.rate)) + (carat ? '/ct' : '/g') : 'Fixed',
                    amount: Number(row.value || 0),
                };
            });
            const parts = [
                { kind: 'metal', name: metal.text + ' ' + purity.text, weight: net.toFixed(3) + ' g', rate: money.format(rateValue) + '/g', amount: metalAmount },
                ...stoneRows.map((row) => ({ ...row, kind: 'stone' })),
                ...(wastageAmount > 0 ? [chargeRow('Wastage', wastageCode, wastageValue, round2(wastageAmount), 'wastage')] : []),
                ...(makingAmount > 0 ? [chargeRow(makingLabel, makingCode, makingValue, round2(makingAmount), 'making')] : []),
            ];
            showBreakdown(parts, line, { gross, net, stone: stoneWeight(), other: Number(document.getElementById('weigh-other-g').value || 0) });
            return { line, gross, net, metal: metalAmount, making: round2(makingAmount), wastage: round2(wastageAmount), stone: round2(stone), parts, stones };
        }

        function weighState() {
            const field = (id) => document.getElementById(id).value;
            const product = document.querySelector('#weigh-products button.active');
            return {
                name: field('weigh-name'),
                product: product ? product.dataset.name : '',
                metal: field('weigh-metal'),
                purity: field('weigh-purity'),
                location: field('weigh-location'),
                net: field('weigh-net'),
                gross: field('weigh-gross'),
                grossAuto: document.getElementById('weigh-gross').dataset.auto,
                other: field('weigh-other-g'),
                making: field('weigh-making'),
                makingValue: field('weigh-making-value'),
                wastage: field('weigh-wastage'),
                wastageValue: field('weigh-wastage-value'),
                stones: collectStones(),
            };
        }

        function applyWeigh(state) {
            const set = (id, value) => { document.getElementById(id).value = value; };
            set('weigh-name', state.name);
            document.querySelectorAll('#weigh-products button').forEach((row) => row.classList.toggle('active', state.product !== '' && row.dataset.name === state.product));
            set('weigh-metal', state.metal);
            set('weigh-purity', state.purity);
            set('weigh-location', state.location);
            set('weigh-net', state.net);
            set('weigh-gross', state.gross);
            document.getElementById('weigh-gross').dataset.auto = state.grossAuto;
            set('weigh-other-g', state.other);
            set('weigh-making', state.making);
            set('weigh-making-value', state.makingValue);
            set('weigh-wastage', state.wastage);
            set('weigh-wastage-value', state.wastageValue);
            syncCharge('making');
            syncCharge('wastage');
            resetStones(state.stones);
            document.getElementById('weigh-error').classList.add('d-none');
            previewWeigh();
        }

        function showEditing() {
            const editing = editingIndex !== null;
            const add = document.getElementById('weigh-add');
            add.innerHTML = editing ? '<i class="bi bi-check-lg"></i> Update piece' : '<i class="bi bi-plus-lg"></i> Add to bill';
            document.getElementById('weigh-cancel').classList.toggle('d-none', !editing);
            const note = document.getElementById('weigh-editing');
            note.classList.toggle('d-none', !editing);
            note.textContent = editing ? 'Editing ' + freshLines[editingIndex].name + '. Change it and press Update piece.' : '';
        }

        function resetWeigh() {
            editingIndex = null;
            applyWeigh(weighDefaults);
            showEditing();
        }

        function editLine(index) {
            editingIndex = index;
            applyWeigh(freshLines[index].form);
            showEditing();
            renderBill();
            document.getElementById('weigh-name').closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('weigh-net').focus({ preventScroll: true });
        }

        function matches(query, values) {
            const needle = query.trim().toLowerCase();
            if (needle === '') return false;
            return values.some((value) => String(value || '').toLowerCase().includes(needle));
        }

        function resultRow(title, meta, onAdd) {
            const button = document.createElement('button');
            button.type = 'button';
            const info = document.createElement('span');
            info.className = 'result-info';
            const name = document.createElement('strong');
            name.textContent = title;
            const detail = document.createElement('small');
            detail.textContent = meta;
            info.append(name, detail);
            const add = document.createElement('span');
            add.className = 'result-add';
            add.innerHTML = '<i class="bi bi-plus-lg"></i> Add to bill';
            button.append(info, add);
            button.addEventListener('click', onAdd);
            return button;
        }

        function renderPieces() {
            const query = document.getElementById('piece-search').value;
            const box = document.getElementById('piece-results');
            const rows = bill.pieces.filter((row) => matches(query, [row.name, row.code, row.barcode, row.huid, row.metal]) && !stockLines.some((line) => line.uuid === row.uuid)).slice(0, 8);
            box.innerHTML = '';
            if (query.trim() === '') return;
            if (rows.length === 0) {
                box.innerHTML = '<div class="p-2 text-secondary small">Not in stock</div>';
                return;
            }
            rows.forEach((row) => {
                const button = resultRow(row.name, [row.code, row.metal, row.net, row.label].filter(Boolean).join(' · '), () => {
                    stockLines.push(row);
                    document.getElementById('piece-search').value = '';
                    renderPieces();
                    renderBill();
                });
                button.disabled = !row.ready;
                box.appendChild(button);
            });
        }

        function makingName() {
            const mode = document.getElementById('making-mode').value;
            return mode === 'processing' ? 'Processing charge' : 'Making';
        }

        function partLabel(part) {
            const name = part.kind === 'making' ? makingName() : part.name;
            if (part.weight && part.rate && part.rate !== 'Fixed') return name + ' · ' + part.weight + ' × ' + part.rate;
            return part.rate ? name + ' · ' + part.rate : name;
        }

        function lineParts(line) {
            if (line.kind === 'fresh') return line.row.parts || [];
            const row = line.row;
            const parts = [];
            if (row.metalAmount) {
                parts.push({ kind: 'metal', name: row.metal, weight: Number(row.netWeight || 0).toFixed(3) + ' g', rate: row.rate ? money.format(row.rate) + '/g' : '', amount: row.metalAmount });
            }
            if ((row.stones || []).length) {
                row.stones.forEach((stone) => {
                    const weight = Number(stone.weight || 0);
                    const carat = stone.rate_unit === 'carat' && stone.rate;
                    parts.push({
                        kind: 'stone',
                        name: stone.name,
                        weight: weight > 0 ? weight.toFixed(3) + ' g' + (carat ? ' · ' + (weight * 5).toFixed(2) + ' ct' : '') : '',
                        rate: stone.rate ? money.format(Number(stone.rate)) + (carat ? '/ct' : '/g') : 'Fixed',
                        amount: Number(stone.value || 0),
                    });
                });
            } else if (row.stoneAmount) {
                parts.push({ kind: 'stone', name: 'Stones', amount: row.stoneAmount });
            }
            if (row.wastageAmount) parts.push({ kind: 'wastage', name: 'Wastage', amount: row.wastageAmount });
            if (row.makingAmount) parts.push({ kind: 'making', name: 'Making', amount: row.makingAmount });
            return parts;
        }

        function renderBill() {
            const body = document.getElementById('bill-lines');
            const inputs = document.getElementById('bill-piece-inputs');
            body.innerHTML = '';
            inputs.innerHTML = '';
            const lines = [
                ...stockLines.map((row) => ({ kind: 'stock', title: row.code + ' · ' + row.name, amount: row.line, row })),
                ...freshLines.map((row, index) => ({ kind: 'fresh', title: row.name + ' · ' + row.metal, amount: row.line, row, index })),
            ];
            if (lines.length === 0) {
                body.innerHTML = '<tr><td colspan="3">Nothing on this bill</td></tr>';
            }
            lines.forEach((line) => {
                const tr = document.createElement('tr');
                const nameCell = document.createElement('td');
                nameCell.className = 'bill-line-cell';
                nameCell.colSpan = 2;
                const head = document.createElement('div');
                head.className = 'bill-line-head';
                const name = document.createElement('strong');
                name.textContent = line.row.name;
                const total = document.createElement('strong');
                total.className = 'bill-line-total';
                total.textContent = money.format(line.amount);
                head.append(name, total);
                const meta = document.createElement('div');
                meta.className = 'bill-line-meta';
                meta.textContent = line.kind === 'stock'
                    ? [line.row.code, line.row.metal, line.row.gross ? 'Gross ' + line.row.gross : '', line.row.net ? 'Net ' + line.row.net : ''].filter(Boolean).join(' · ')
                    : [line.row.metal, 'Gross ' + Number(line.row.fields.gross_weight || 0).toFixed(3) + ' g', 'Net ' + Number(line.row.netWeight || 0).toFixed(3) + ' g'].join(' · ');
                nameCell.append(head, meta);
                const split = document.createElement('div');
                split.className = 'piece-split bill-line-split';
                showSplit(split, lineParts(line).map((part) => ({ label: partLabel(part), value: money.format(part.amount) })));
                nameCell.appendChild(split);
                const action = document.createElement('td');
                action.className = 'text-end bill-line-cell';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'bill-remove';
                button.setAttribute('aria-label', 'Remove');
                button.title = 'Remove';
                button.textContent = '×';
                if (line.kind === 'fresh') {
                    tr.classList.toggle('is-editing', line.index === editingIndex);
                    const edit = document.createElement('button');
                    edit.type = 'button';
                    edit.className = 'bill-remove bill-edit';
                    edit.setAttribute('aria-label', 'Edit');
                    edit.title = 'Edit';
                    edit.innerHTML = '<i class="bi bi-pencil"></i>';
                    edit.addEventListener('click', () => editLine(line.index));
                    action.appendChild(edit);
                }
                action.appendChild(button);
                tr.append(nameCell, action);
                button.addEventListener('click', () => {
                    if (line.kind === 'stock') {
                        stockLines.splice(stockLines.indexOf(line.row), 1);
                    } else {
                        freshLines.splice(line.index, 1);
                        if (editingIndex === line.index) {
                            resetWeigh();
                        } else if (editingIndex !== null && editingIndex > line.index) {
                            editingIndex -= 1;
                        }
                    }
                    renderPieces();
                    renderBill();
                });
                body.appendChild(tr);
                if (line.kind === 'stock') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'item_ids[]';
                    input.value = line.row.uuid;
                    inputs.appendChild(input);
                } else {
                    Object.entries(line.row.fields).forEach(([key, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'new_pieces[' + line.index + '][' + key + ']';
                        input.value = value ?? '';
                        inputs.appendChild(input);
                    });
                    (line.row.stones || []).forEach((stone, index) => {
                        ['name', 'weight', 'value', 'rate', 'rate_unit'].forEach((key) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'new_pieces[' + line.index + '][stones][' + index + '][' + key + ']';
                            input.value = stone[key] ?? '';
                            inputs.appendChild(input);
                        });
                    });
                }
            });
            renderTotals();
        }

        function renderTotals() {
            const rows = [...stockLines, ...freshLines];
            const subtotal = round2(rows.reduce((sum, row) => sum + Number(row.line || row.amount || 0), 0));
            const mode = document.getElementById('making-mode').value;
            const making = Math.min(subtotal, round2(rows.reduce((sum, row) => sum + Number(row.makingAmount || 0), 0)));
            const discount = Math.min(subtotal, Math.max(0, Number(document.getElementById('discount').value || 0)));
            const taxOf = (amount, percent) => percent > 0
                ? round2(bill.exclusive ? amount * percent / 100 : amount * percent / (percent + 100))
                : 0;
            let tax = 0;
            let makingTax = 0;
            let taxBase = round2(subtotal - discount);
            let makingBase = 0;
            let exact = taxBase;
            if (mode === 'inside') {
                tax = taxOf(exact, bill.gst);
                if (bill.exclusive) exact = round2(exact + tax);
            } else {
                const jewellery = round2(subtotal - making);
                const jewelleryDiscount = Math.min(discount, jewellery);
                const jewelleryNet = round2(jewellery - jewelleryDiscount);
                const makingNet = round2(making - (discount - jewelleryDiscount));
                taxBase = jewelleryNet;
                makingBase = makingNet;
                tax = taxOf(jewelleryNet, bill.gst);
                makingTax = mode === 'separate' ? taxOf(makingNet, bill.makingGst) : 0;
                exact = round2(jewelleryNet + makingNet + (bill.exclusive ? tax + makingTax : 0));
            }
            const stoneTotal = round2(rows.reduce((sum, row) => sum + Number(row.stoneAmount || 0), 0));
            const wastageTotal = round2(rows.reduce((sum, row) => sum + Number(row.wastageAmount || 0), 0));
            const showRow = (id, amount, show) => {
                document.getElementById(id + '-row').classList.toggle('d-none', !show);
                document.getElementById(id).textContent = money.format(amount);
            };
            showRow('bill-stone', stoneTotal, stoneTotal > 0);
            showRow('bill-wastage', wastageTotal, wastageTotal > 0);
            showRow('bill-making', making, making > 0);
            showRow('bill-discount', discount, discount > 0);
            document.getElementById('bill-discount').textContent = '− ' + money.format(discount);
            document.getElementById('bill-making-label').textContent = {
                inside: 'Making (in jewellery GST)',
                separate: 'Making (own GST)',
                processing: 'Processing charge (no GST)',
            }[mode];
            const taxParts = ['metal'];
            if (stoneTotal > 0) taxParts.push('stones');
            if (wastageTotal > 0) taxParts.push('wastage');
            if (mode === 'inside' && making > 0) taxParts.push('making');
            const taxOn = taxParts.join(' + ') + (discount > 0 ? ' after discount' : '');
            const taxNote = subtotal <= 0 ? '' : ' on ' + taxOn + (bill.exclusive ? ' ' + money.format(taxBase) : ' (included)');
            document.getElementById('bill-tax-label').textContent = 'GST ' + bill.gst + '%' + taxNote;
            document.getElementById('bill-making-tax-row').classList.toggle('d-none', mode !== 'separate' || making <= 0);
            document.getElementById('bill-making-tax-label').textContent = 'GST ' + bill.makingGst + '% on making' + (subtotal > 0 ? (bill.exclusive ? ' ' + money.format(makingBase) : ' (included)') : '');
            document.getElementById('bill-making-tax').textContent = money.format(makingTax);
            document.getElementById('weigh-making-label').textContent = mode === 'processing' ? 'Processing charge' : 'Making';
            let total = exact;
            let roundOff = 0;
            if (bill.round) {
                total = Math.round(exact);
                roundOff = round2(total - exact);
            }
            const netWeight = rows.reduce((sum, row) => sum + Number(row.netWeight || 0), 0);
            const metalValue = round2(rows.reduce((sum, row) => sum + Number(row.metalAmount || 0), 0));
            document.getElementById('bill-net-weight').textContent = netWeight.toFixed(3) + ' g';
            document.getElementById('bill-metal').textContent = money.format(metalValue);
            document.getElementById('bill-subtotal').textContent = money.format(subtotal);
            document.getElementById('bill-tax').textContent = money.format(tax);
            showRow('bill-round', roundOff, roundOff !== 0);
            document.getElementById('bill-total').textContent = money.format(total);
            document.getElementById('bill-form').dataset.total = String(total);
            let payNow = total;
            if (bill.order) {
                const advance = Math.min(bill.order.advance, total);
                payNow = round2(total - advance);
                document.getElementById('bill-advance').textContent = '− ' + money.format(advance);
            }
            const credit = customer && !customer.walkin ? Number(customer.credit || 0) : 0;
            const creditUsed = credit > 0 && document.getElementById('use-credit-toggle').checked ? round2(Math.min(credit, payNow)) : 0;
            document.getElementById('bill-credit-row').classList.toggle('d-none', credit <= 0);
            document.getElementById('bill-credit-available').textContent = money.format(credit) + ' on account';
            document.getElementById('bill-credit').textContent = '− ' + money.format(creditUsed);
            document.getElementById('use-credit').value = creditUsed.toFixed(2);
            payNow = round2(payNow - creditUsed);
            document.getElementById('bill-pay-now-row').classList.toggle('d-none', !bill.order && creditUsed <= 0);
            document.getElementById('bill-pay-now').textContent = money.format(payNow);
            document.getElementById('bill-form').dataset.pay = String(payNow);
            const note = document.getElementById('bill-note');
            if (customer && customer.walkin) {
                note.textContent = 'Walk-in pays the full total.';
                note.classList.remove('d-none');
            } else {
                note.textContent = '';
                note.classList.add('d-none');
            }
        }

        const scanInput = document.getElementById('scan-code');
        const scanQueue = [];
        let scanRunning = false;

        function scanMessage(kind, text) {
            const status = document.getElementById('scan-status');
            status.className = 'scan-status is-' + kind;
            status.textContent = text;
        }

        function sameCode(a, b) {
            return String(a || '').toLowerCase() === String(b || '').toLowerCase();
        }

        function onBill(code) {
            return stockLines.find((row) => sameCode(row.barcode, code) || sameCode(row.code, code)) || null;
        }

        function queueScan() {
            const code = scanInput.value.replace(/[\x00-\x1F\x7F]/g, '').trim();
            scanInput.value = '';
            if (code === '') return;
            if (scanQueue.some((queued) => sameCode(queued, code))) return;
            scanQueue.push(code);
            runScans();
        }

        async function runScans() {
            if (scanRunning) return;
            scanRunning = true;
            while (scanQueue.length > 0) {
                await lookUp(scanQueue[0]);
                scanQueue.shift();
            }
            scanRunning = false;
        }

        async function lookUp(code) {
            const already = onBill(code);
            if (already) {
                scanMessage('warning', already.code + ' is already on this bill.');
                return;
            }
            scanMessage('busy', 'Looking up ' + code + '…');
            const url = new URL(bill.scanUrl, window.location.origin);
            url.searchParams.set('code', code);
            if (bill.orderUuid) url.searchParams.set('order', bill.orderUuid);
            let response;
            let body = {};
            try {
                response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                body = await response.json().catch(() => ({}));
            } catch (error) {
                scanMessage('error', 'Could not reach the server. Check the internet and scan again.');
                return;
            }
            if (response.status === 401 || response.status === 419) {
                scanMessage('error', 'You have been signed out. Sign in again to keep billing.');
                return;
            }
            if (!response.ok || !body.piece) {
                scanMessage('error', body.message || 'This tag could not be added.');
                return;
            }
            const piece = body.piece;
            if (stockLines.some((row) => row.uuid === piece.uuid)) {
                scanMessage('warning', piece.code + ' is already on this bill.');
                return;
            }
            const known = bill.pieces.findIndex((row) => row.uuid === piece.uuid);
            if (known === -1) bill.pieces.push(piece); else bill.pieces[known] = piece;
            stockLines.push(piece);
            renderPieces();
            renderBill();
            scanMessage('success', 'Added ' + [piece.code, piece.name, piece.metal, piece.net ? 'Net ' + piece.net : '', piece.label].filter(Boolean).join(' · '));
        }

        scanInput.addEventListener('keydown', (event) => {
            if (event.isComposing) return;
            if (event.key === 'Enter' || (event.key === 'Tab' && !event.shiftKey && scanInput.value.trim() !== '')) {
                event.preventDefault();
                queueScan();
            }
        });
        document.getElementById('scan-add').addEventListener('click', () => {
            queueScan();
            scanInput.focus();
        });
        document.getElementById('bill-form').addEventListener('keydown', (event) => {
            const field = event.target;
            if (event.key !== 'Enter' || field === scanInput || field.tagName !== 'INPUT') return;
            if (['button', 'submit', 'checkbox', 'radio'].includes(field.type)) return;
            event.preventDefault();
        });

        document.getElementById('piece-search').addEventListener('input', renderPieces);
        document.getElementById('discount').addEventListener('input', renderTotals);
        document.getElementById('use-credit-toggle').addEventListener('change', renderTotals);
        document.getElementById('making-mode').addEventListener('change', () => {
            renderBill();
            previewWeigh();
        });
        document.getElementById('bill-form').addEventListener('input', (event) => {
            if ((event.target.id && event.target.id.startsWith('weigh-')) || event.target.closest('#stone-list')) previewWeigh();
        });
        document.getElementById('weigh-gross').addEventListener('input', (event) => {
            event.target.dataset.auto = '';
        });
        document.getElementById('weigh-gross').addEventListener('blur', (event) => {
            if (event.target.value.trim() !== '') return;
            event.target.dataset.auto = '1';
            previewWeigh();
        });
        document.getElementById('stone-add').addEventListener('click', () => addStoneRow());
        document.getElementById('weigh-metal').addEventListener('change', previewWeigh);
        document.getElementById('weigh-purity').addEventListener('change', previewWeigh);
        function syncCharge(kind) {
            const method = document.getElementById('weigh-' + kind);
            const value = document.getElementById('weigh-' + kind + '-value');
            const none = method.value === '';
            value.disabled = none;
            value.placeholder = none ? '—' : '0';
            if (none) value.value = '';
        }
        ['making', 'wastage'].forEach((kind) => {
            syncCharge(kind);
            document.getElementById('weigh-' + kind).addEventListener('change', () => {
                syncCharge(kind);
                previewWeigh();
            });
        });
        document.getElementById('weigh-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#weigh-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            document.getElementById('weigh-name').value = button.dataset.name;
            document.getElementById('weigh-net').focus();
            previewWeigh();
        });
        document.getElementById('use-total').addEventListener('click', () => {
            const field = document.querySelector('.bill-pay');
            const pay = Number(document.getElementById('bill-form').dataset.pay || 0);
            if (field) field.value = pay > 0 ? pay.toFixed(2) : '';
        });
        document.getElementById('weigh-add').addEventListener('click', () => {
            const error = document.getElementById('weigh-error');
            const name = document.getElementById('weigh-name').value.trim();
            const quote = previewWeigh();
            const stones = collectStones();
            if (name === '' || quote === null || stones.some((stone) => stone.name === '')) {
                error.textContent = name === '' ? 'Choose a product.' : (stones.some((stone) => stone.name === '') ? 'Write the stone name.' : document.getElementById('weigh-price').textContent);
                error.classList.remove('d-none');
                return;
            }
            error.classList.add('d-none');
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            const stoneWeightTotal = stones.reduce((sum, stone) => sum + Number(stone.weight || 0), 0);
            const stoneValueTotal = stones.reduce((sum, stone) => sum + Number(stone.value || 0), 0);
            const entry = {
                name,
                form: weighState(),
                metal: metal.text + ' ' + purity.text,
                line: quote.line,
                netWeight: quote.net,
                metalAmount: quote.metal,
                makingAmount: quote.making,
                wastageAmount: quote.wastage,
                stoneAmount: quote.stone,
                parts: quote.parts,
                stones,
                fields: {
                    name,
                    metal_uuid: metal.value,
                    purity_uuid: purity.value,
                    location_uuid: option('weigh-location').value,
                    gross_weight: quote.gross.toFixed(3),
                    stone_weight: stoneWeightTotal ? String(stoneWeightTotal) : '0',
                    other_weight: document.getElementById('weigh-other-g').value,
                    stone_value: stoneValueTotal ? String(stoneValueTotal) : '0',
                    making_method_uuid: option('weigh-making').value,
                    making_value: document.getElementById('weigh-making-value').value || '0',
                    wastage_method_uuid: option('weigh-wastage').value,
                    wastage_value: document.getElementById('weigh-wastage-value').value || '0',
                },
            };
            if (editingIndex !== null) {
                freshLines[editingIndex] = entry;
            } else {
                freshLines.push(entry);
            }
            resetWeigh();
            renderBill();
        });
        document.getElementById('weigh-cancel').addEventListener('click', () => {
            resetWeigh();
            renderBill();
        });
        document.getElementById('bill-form').addEventListener('submit', (event) => {
            if (!picker.ensure()) {
                event.preventDefault();
            }
            if (stockLines.length === 0 && freshLines.length === 0) {
                event.preventDefault();
                document.getElementById('piece-search').focus();
            }
        });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) resetWeigh();
        });
        resetStones();
        weighDefaults = weighState();
        const picker = customerPicker({
            customers: bill.customers,
            createUrl: bill.customerUrl,
            showUrl: bill.customerShowUrl,
            csrf: bill.csrf,
            addLabel: 'Add to bill',
            chipLabel: 'Bill to',
            onChange: (row) => {
                customer = row;
                renderTotals();
            },
        });
        renderPieces();
        renderBill();
        previewWeigh();
        if (document.activeElement === document.body || document.activeElement === null) scanInput.focus();
    </script>
@endpush
