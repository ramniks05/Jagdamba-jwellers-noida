@extends('layouts.app')

@section('title', 'New bill')

@section('content')
    <h1 class="page-title h3 mb-3">New bill</h1>
    <form method="POST" action="{{ route('sales.store') }}" id="bill-form">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
        <div id="bill-piece-inputs"></div>
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Customer</span>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#customer-modal">New customer</button>
                    </div>
                    <div class="card-body">
                        <label class="form-label" for="customer-search">Search by mobile number or name</label>
                        <input class="form-control" id="customer-search" placeholder="Mobile, name, or code" autocomplete="off">
                        <div class="bill-results mt-2" id="customer-results"></div>
                        <div class="customer-chip d-none" id="customer-chosen"></div>
                        <div class="text-danger small mt-2 d-none" id="customer-error">Choose a customer.</div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Weigh and bill</div>
                    <div class="card-body">
                        <div class="bill-products" id="weigh-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="weigh-name">Name</label>
                                <input class="form-control" id="weigh-name" placeholder="Ring">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="weigh-metal">Metal</label>
                                <select class="form-select" id="weigh-metal">
                                    @foreach ($metals as $metal)
                                        <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}">{{ $metal->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="weigh-purity">Purity</label>
                                <select class="form-select" id="weigh-purity">
                                    @foreach ($metals as $metal)
                                        @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                            <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}">{{ $purity->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="weigh-metrics">
                            <div class="weight-field">
                                <label for="weigh-gross">Gross</label>
                                <div class="weight-input">
                                    <input class="form-control bill-weight" id="weigh-gross" inputmode="decimal" placeholder="0.000">
                                    <span>g</span>
                                </div>
                            </div>
                            <div class="weight-field">
                                <label for="weigh-making">Making</label>
                                <select class="form-select charge-method" id="weigh-making">
                                    <option value="">None</option>
                                    @foreach ($making as $method)
                                        <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'percentage')>{{ $method->name }}</option>
                                    @endforeach
                                </select>
                                <input class="form-control" id="weigh-making-value" inputmode="decimal" value="0" aria-label="Making amount">
                            </div>
                            <div class="weight-field">
                                <label for="weigh-wastage">Wastage</label>
                                <select class="form-select charge-method" id="weigh-wastage">
                                    <option value="">None</option>
                                    @foreach ($wastage as $method)
                                        <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'percentage')>{{ $method->name }}</option>
                                    @endforeach
                                </select>
                                <input class="form-control" id="weigh-wastage-value" inputmode="decimal" value="0" aria-label="Wastage amount">
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
                        <details class="mt-3">
                            <summary>Stones</summary>
                            <div id="stone-list"></div>
                            <button class="btn btn-outline-secondary btn-sm mt-2" id="stone-add" type="button">Add stone</button>
                            <div class="weight-field mt-2" style="max-width: 9rem">
                                <label for="weigh-other-g">Other g</label>
                                <input class="form-control" id="weigh-other-g" value="0">
                            </div>
                        </details>
                        <div class="weigh-quote is-empty" id="weigh-price">Enter the weight</div>
                        <div class="piece-split" id="weigh-split"></div>
                        <div class="text-danger small d-none mt-2" id="weigh-error"></div>
                        <button class="btn btn-primary mt-3" id="weigh-add" type="button">Add to bill</button>
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
                        <label class="form-label" for="discount">Discount</label>
                        <input class="form-control mb-2" id="discount" name="discount" value="{{ old('discount', '0') }}">
                        <div class="bill-row"><span>Pieces</span><span id="bill-subtotal">0.00</span></div>
                        <div class="bill-row"><span>Discount</span><span id="bill-discount">0.00</span></div>
                        <div class="bill-row"><span>GST {{ $gstPercent }}%</span><span id="bill-tax">0.00</span></div>
                        <div class="bill-row"><span>Round off</span><span id="bill-round">0.00</span></div>
                        <div class="bill-grand"><span>Total</span><strong id="bill-total">0.00</strong></div>
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

    <div class="modal fade" id="customer-modal" tabindex="-1" aria-labelledby="customer-modal-title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="customer-modal-title">New customer</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="new-customer-name">Name</label>
                        <input class="form-control" id="new-customer-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new-customer-mobile">Mobile</label>
                        <input class="form-control" id="new-customer-mobile" inputmode="numeric">
                    </div>
                    <div class="text-danger small d-none" id="customer-modal-error"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" id="save-customer" type="button">Save and use on this bill</button>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="bill-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'gst' => (float) $gstPercent,
        'exclusive' => $taxExclusive,
        'round' => $roundRupee,
        'customers' => $customers->map(fn ($customer) => [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'code' => $customer->code,
            'mobile' => $customer->mobile,
            'walkin' => (bool) $customer->is_system,
        ])->values(),
        'pieces' => $rows->map(function ($row) use ($money, $weight) {
            $item = $row['item'];
            $quote = $row['quote'];

            return [
                'uuid' => $item->uuid,
                'code' => $item->item_code,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'huid' => $item->huid,
                'metal' => trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? '')),
                'net' => $weight((string) $item->net_weight),
                'ready' => $quote['ready'],
                'line' => $quote['ready'] ? (float) $quote['line'] : 0,
                'metalAmount' => $quote['ready'] ? (float) $quote['metal'] : 0,
                'stoneAmount' => $quote['ready'] ? (float) $quote['stone'] : 0,
                'stones' => $item->stones->map(fn ($stone) => [
                    'name' => $stone->name,
                    'weight' => (string) $stone->weight,
                    'value' => (float) $stone->value,
                ])->values(),
                'label' => $quote['ready'] ? $money($quote['line']) : $quote['message'],
            ];
        })->values(),
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
        const bill = JSON.parse(document.getElementById('bill-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const stockLines = [];
        const freshLines = [];
        let customer = bill.customers.find((row) => row.uuid === document.getElementById('customer-uuid').value) || null;

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function option(id) {
            const field = document.getElementById(id);
            return field.options[field.selectedIndex];
        }

        function currentRate(metalId, purityId, branchId) {
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
            return Array.from(document.querySelectorAll('#stone-list .stone-row')).map((row) => ({
                name: row.querySelector('.stone-name').value.trim(),
                weight: row.querySelector('.stone-weight').value || '0',
                value: row.querySelector('.stone-value').value || '0',
            })).filter((stone) => stone.name !== '' || Number(stone.weight) > 0 || Number(stone.value) > 0);
        }

        function stoneWeight() {
            return collectStones().reduce((sum, stone) => sum + Number(stone.weight || 0), 0);
        }

        function addStoneRow() {
            const row = document.createElement('div');
            row.className = 'stone-row';
            [
                ['Name', 'stone-name', 'Diamond', 'text'],
                ['Weight g', 'stone-weight', '0.000', 'decimal'],
                ['Value', 'stone-value', '0', 'decimal'],
            ].forEach(([labelText, className, placeholder, mode]) => {
                const field = document.createElement('div');
                const label = document.createElement('label');
                label.textContent = labelText;
                const input = document.createElement('input');
                input.className = 'form-control ' + className;
                input.placeholder = placeholder;
                if (mode === 'decimal') input.inputMode = 'decimal';
                field.append(label, input);
                row.appendChild(field);
            });
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
        }

        function resetStones() {
            document.getElementById('stone-list').replaceChildren();
            addStoneRow();
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

        function showQuote(parts) {
            const preview = document.getElementById('weigh-price');
            preview.className = 'weigh-quote';
            preview.replaceChildren();
            parts.forEach((part) => {
                const box = document.createElement('div');
                if (part.amount) box.className = 'weigh-amount';
                const label = document.createElement('span');
                label.textContent = part.label;
                const value = document.createElement('strong');
                value.textContent = part.value;
                box.append(label, value);
                preview.appendChild(box);
            });
        }

        function previewWeigh() {
            syncPurities();
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            const location = option('weigh-location');
            const gross = Number(document.getElementById('weigh-gross').value || 0);
            if (gross <= 0) {
                return emptyQuote('Enter the weight');
            }
            const stones = collectStones();
            const net = gross - stoneWeight() - Number(document.getElementById('weigh-other-g').value || 0);
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
            showQuote([
                { label: 'Net', value: net.toFixed(3) + ' g' },
                { label: metal.text, value: money.format(metalAmount) },
                { label: 'Amount', value: money.format(line), amount: true },
            ]);
            showSplit(document.getElementById('weigh-split'), [
                { label: 'Rate / g', value: money.format(rateValue) },
                ...stones.filter((row) => row.name !== '').map((row) => ({
                    label: row.name + ' · ' + Number(row.weight || 0).toFixed(3) + ' g',
                    value: money.format(Number(row.value || 0)),
                })),
            ]);
            return { line, metal: metalAmount, stones };
        }

        function matches(query, values) {
            const needle = query.trim().toLowerCase();
            if (needle === '') return false;
            return values.some((value) => String(value || '').toLowerCase().includes(needle));
        }

        function renderCustomers() {
            const query = document.getElementById('customer-search').value;
            const box = document.getElementById('customer-results');
            const rows = bill.customers.filter((row) => matches(query, [row.name, row.mobile, row.code])).slice(0, 8);
            box.innerHTML = '';
            if (query.trim() === '') return;
            if (rows.length === 0) {
                box.innerHTML = '<div class="p-2 text-secondary small">Not found</div>';
                return;
            }
            rows.forEach((row) => {
                const button = document.createElement('button');
                button.type = 'button';
                const name = document.createElement('strong');
                name.textContent = row.name;
                const meta = document.createElement('span');
                meta.textContent = [row.mobile, row.code].filter(Boolean).join(' · ');
                button.append(name, meta);
                button.addEventListener('click', () => chooseCustomer(row));
                box.appendChild(button);
            });
        }

        function chooseCustomer(row) {
            customer = row;
            document.getElementById('customer-uuid').value = row.uuid;
            const chosen = document.getElementById('customer-chosen');
            chosen.classList.remove('d-none');
            chosen.replaceChildren();
            const name = document.createElement('strong');
            name.textContent = row.name;
            const meta = document.createElement('span');
            meta.textContent = [row.mobile, row.code].filter(Boolean).join(' · ');
            chosen.append(name, meta);
            document.getElementById('customer-error').classList.add('d-none');
            document.getElementById('customer-results').innerHTML = '';
            document.getElementById('customer-search').value = '';
            renderTotals();
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
                const button = document.createElement('button');
                button.type = 'button';
                const name = document.createElement('strong');
                name.textContent = row.name;
                const meta = document.createElement('span');
                meta.textContent = [row.code, row.metal, row.net, row.label].filter(Boolean).join(' · ');
                button.append(name, meta);
                button.disabled = !row.ready;
                button.addEventListener('click', () => {
                    stockLines.push(row);
                    document.getElementById('piece-search').value = '';
                    renderPieces();
                    renderBill();
                });
                box.appendChild(button);
            });
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
                const name = document.createElement('div');
                name.textContent = line.kind === 'stock' ? line.row.name : line.row.name;
                const meta = document.createElement('div');
                meta.className = 'small text-secondary';
                meta.textContent = line.kind === 'stock'
                    ? [line.row.code, line.row.metal, line.row.net].filter(Boolean).join(' · ')
                    : [line.row.metal, Number(line.row.fields.gross_weight || 0).toFixed(3) + ' g'].filter(Boolean).join(' · ');
                nameCell.append(name, meta);
                const splits = [];
                if (line.kind === 'fresh') {
                    splits.push({ label: line.row.metal, value: money.format(line.row.metalAmount) });
                    (line.row.stones || []).forEach((stone) => {
                        splits.push({
                            label: stone.name + ' · ' + Number(stone.weight || 0).toFixed(3) + ' g',
                            value: money.format(Number(stone.value || 0)),
                        });
                    });
                } else {
                    if (line.row.metalAmount) splits.push({ label: line.row.metal, value: money.format(line.row.metalAmount) });
                    if ((line.row.stones || []).length) {
                        line.row.stones.forEach((stone) => {
                            splits.push({
                                label: stone.name + ' · ' + Number(stone.weight || 0).toFixed(3) + ' g',
                                value: money.format(Number(stone.value || 0)),
                            });
                        });
                    } else if (line.row.stoneAmount) {
                        splits.push({ label: 'Stone', value: money.format(line.row.stoneAmount) });
                    }
                }
                if (splits.length) {
                    const split = document.createElement('div');
                    split.className = 'piece-split';
                    showSplit(split, splits);
                    nameCell.appendChild(split);
                }
                const amountCell = document.createElement('td');
                amountCell.className = 'num';
                amountCell.textContent = money.format(line.amount);
                const action = document.createElement('td');
                action.className = 'text-end';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'bill-remove';
                button.setAttribute('aria-label', 'Remove');
                button.textContent = '×';
                action.appendChild(button);
                tr.append(nameCell, amountCell, action);
                button.addEventListener('click', () => {
                    if (line.kind === 'stock') {
                        stockLines.splice(stockLines.indexOf(line.row), 1);
                    } else {
                        freshLines.splice(line.index, 1);
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
                        ['name', 'weight', 'value'].forEach((key) => {
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
            const subtotal = round2([...stockLines, ...freshLines].reduce((sum, row) => sum + Number(row.line || row.amount || 0), 0));
            const discount = Math.min(subtotal, Math.max(0, Number(document.getElementById('discount').value || 0)));
            const after = round2(subtotal - discount);
            let tax = 0;
            let exact = after;
            if (bill.gst > 0) {
                if (bill.exclusive) {
                    tax = round2(after * bill.gst / 100);
                    exact = round2(after + tax);
                } else {
                    tax = round2(after * bill.gst / (bill.gst + 100));
                }
            }
            let total = exact;
            let roundOff = 0;
            if (bill.round) {
                total = Math.round(exact);
                roundOff = round2(total - exact);
            }
            document.getElementById('bill-subtotal').textContent = money.format(subtotal);
            document.getElementById('bill-discount').textContent = money.format(discount);
            document.getElementById('bill-tax').textContent = money.format(tax);
            document.getElementById('bill-round').textContent = money.format(roundOff);
            document.getElementById('bill-total').textContent = money.format(total);
            document.getElementById('bill-form').dataset.total = String(total);
            const note = document.getElementById('bill-note');
            if (customer && customer.walkin) {
                note.textContent = 'Walk-in pays the full total.';
                note.classList.remove('d-none');
            } else {
                note.textContent = '';
                note.classList.add('d-none');
            }
        }

        document.getElementById('customer-search').addEventListener('input', renderCustomers);
        document.getElementById('piece-search').addEventListener('input', renderPieces);
        document.getElementById('discount').addEventListener('input', renderTotals);
        document.getElementById('bill-form').addEventListener('input', (event) => {
            if ((event.target.id && event.target.id.startsWith('weigh-')) || event.target.closest('#stone-list')) previewWeigh();
        });
        document.getElementById('stone-add').addEventListener('click', () => addStoneRow());
        document.getElementById('weigh-metal').addEventListener('change', previewWeigh);
        document.getElementById('weigh-purity').addEventListener('change', previewWeigh);
        document.getElementById('weigh-making').addEventListener('change', previewWeigh);
        document.getElementById('weigh-wastage').addEventListener('change', previewWeigh);
        document.getElementById('weigh-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#weigh-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            document.getElementById('weigh-name').value = button.dataset.name;
            document.getElementById('weigh-gross').focus();
            previewWeigh();
        });
        document.getElementById('use-total').addEventListener('click', () => {
            const field = document.querySelector('.bill-pay');
            if (field) field.value = Number(document.getElementById('bill-form').dataset.total || 0).toFixed(2);
        });
        document.getElementById('save-customer').addEventListener('click', async () => {
            const error = document.getElementById('customer-modal-error');
            error.classList.add('d-none');
            const response = await fetch(bill.customerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': bill.csrf,
                },
                body: JSON.stringify({
                    name: document.getElementById('new-customer-name').value,
                    mobile: document.getElementById('new-customer-mobile').value,
                    customer_type: 'retail',
                    kyc_status: 'pending',
                    is_active: true,
                }),
            });
            const payload = await response.json();
            if (!response.ok) {
                error.textContent = Object.values(payload.errors || {}).flat().join(' ') || 'The customer could not be saved.';
                error.classList.remove('d-none');
                return;
            }
            bill.customers.push(payload);
            chooseCustomer(payload);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customer-modal')).hide();
            document.getElementById('new-customer-name').value = '';
            document.getElementById('new-customer-mobile').value = '';
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
            freshLines.push({
                name,
                metal: metal.text + ' ' + purity.text,
                line: quote.line,
                metalAmount: quote.metal,
                stones,
                fields: {
                    name,
                    metal_uuid: metal.value,
                    purity_uuid: purity.value,
                    location_uuid: option('weigh-location').value,
                    gross_weight: document.getElementById('weigh-gross').value,
                    stone_weight: stoneWeightTotal ? String(stoneWeightTotal) : '0',
                    other_weight: document.getElementById('weigh-other-g').value,
                    stone_value: stoneValueTotal ? String(stoneValueTotal) : '0',
                    making_method_uuid: option('weigh-making').value,
                    making_value: document.getElementById('weigh-making-value').value,
                    wastage_method_uuid: option('weigh-wastage').value,
                    wastage_value: document.getElementById('weigh-wastage-value').value,
                },
            });
            document.getElementById('weigh-gross').value = '';
            resetStones();
            previewWeigh();
            renderBill();
        });
        document.getElementById('bill-form').addEventListener('submit', (event) => {
            if (!document.getElementById('customer-uuid').value) {
                event.preventDefault();
                document.getElementById('customer-error').classList.remove('d-none');
                document.getElementById('customer-search').focus();
            }
            if (stockLines.length === 0 && freshLines.length === 0) {
                event.preventDefault();
                document.getElementById('piece-search').focus();
            }
        });
        resetStones();
        renderCustomers();
        renderPieces();
        renderBill();
        previewWeigh();
    </script>
@endpush
