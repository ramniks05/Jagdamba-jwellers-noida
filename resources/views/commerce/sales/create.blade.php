@extends('layouts.app')

@section('title', 'New bill')

@section('content')
    <h1 class="page-title h3 mb-2">New bill</h1>
    <p class="text-secondary">Choose the customer, then either weigh a general product such as a ring, or search a tagged piece already in stock. The price is calculated on this page before you save.</p>
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
                        <div class="alert alert-warning mt-3 mb-0 d-none" id="customer-chosen"></div>
                        <div class="text-danger small mt-2 d-none" id="customer-error">Choose a customer before saving. Search above, or add a new customer.</div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Weigh and bill</div>
                    <div class="card-body">
                        <p class="text-secondary">Use this when the piece is not a tagged stock item. Pick Ring, Chain, or another product, type the weight from the scale, and add it to this bill.</p>
                        <div class="bill-products mb-3" id="weigh-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="weigh-name">Name on the bill</label>
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
                            <div class="col-md-4">
                                <label class="form-label" for="weigh-gross">Gross weight (g)</label>
                                <input class="form-control bill-weight" id="weigh-gross" inputmode="decimal" placeholder="0.000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="weigh-making-value">Making per gram</label>
                                <input class="form-control" id="weigh-making-value" inputmode="decimal" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="weigh-wastage-value">Wastage %</label>
                                <input class="form-control" id="weigh-wastage-value" inputmode="decimal" value="0">
                            </div>
                        </div>
                        <details class="mt-3">
                            <summary>Stone, location, or a different making charge</summary>
                            <div class="row g-3 mt-1">
                                <div class="col-md-3">
                                    <label class="form-label" for="weigh-stone-g">Stone g</label>
                                    <input class="form-control" id="weigh-stone-g" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="weigh-other-g">Other g</label>
                                    <input class="form-control" id="weigh-other-g" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="weigh-stone-value">Stone value</label>
                                    <input class="form-control" id="weigh-stone-value" value="0">
                                </div>
                                <div class="col-md-3 {{ $locations->count() < 2 ? 'd-none' : '' }}">
                                    <label class="form-label" for="weigh-location">Location</label>
                                    <select class="form-select" id="weigh-location">
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->uuid }}" data-branch="{{ $location->branch_id }}">{{ $location->branch?->name }} / {{ $location->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="weigh-making">Making charge</label>
                                    <select class="form-select" id="weigh-making">
                                        <option value="">None</option>
                                        @foreach ($making as $method)
                                            <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'per_gram')>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="weigh-wastage">Wastage charge</label>
                                    <select class="form-select" id="weigh-wastage">
                                        <option value="">None</option>
                                        @foreach ($wastage as $method)
                                            <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'percentage')>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </details>
                        <div class="fw-semibold mt-3" id="weigh-price">Choose a product and enter the weight.</div>
                        <div class="text-danger small d-none mt-2" id="weigh-error"></div>
                        <button class="btn btn-primary mt-3" id="weigh-add" type="button">Add to this bill</button>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Tagged piece in stock</div>
                    <div class="card-body">
                        <label class="form-label" for="piece-search">Search by name, code, or barcode</label>
                        <input class="form-control" id="piece-search" value="{{ $search }}" placeholder="R001, barcode, or a saved piece name" autocomplete="off">
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
                    <div class="card-body">
                        <label class="form-label" for="discount">Bill discount</label>
                        <input class="form-control mb-3" id="discount" name="discount" value="{{ old('discount', '0') }}">
                        <ul class="list-group list-group-flush mb-3">
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Pieces</span><span id="bill-subtotal">0.00</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Discount</span><span id="bill-discount">0.00</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>GST {{ $gstPercent }}%</span><span id="bill-tax">0.00</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Round off</span><span id="bill-round">0.00</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0 fw-semibold"><span>Total</span><span id="bill-total">0.00</span></li>
                        </ul>
                        <p class="text-secondary small" id="bill-note">Choose the customer and add at least one piece.</p>
                        <label class="form-label" for="notes">Note on the invoice</label>
                        <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}">
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Payment</span>
                        <button class="btn btn-outline-secondary btn-sm" id="use-total" type="button">Put the total in cash</button>
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
                        <p class="text-secondary small mb-0">A named customer can leave a balance. Walk-in must pay the full total.</p>
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
                    <p class="text-secondary">The customer code is assigned when you save. Search by the mobile number next time.</p>
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

        function chargeLabels() {
            const making = option('weigh-making');
            const wastage = option('weigh-wastage');
            const makingCode = making && making.value ? making.dataset.code : 'per_gram';
            const wastageCode = wastage && wastage.value ? wastage.dataset.code : 'percentage';
            document.querySelector('label[for="weigh-making-value"]').textContent = makingCode === 'percentage' ? 'Making %' : (makingCode === 'per_gram' ? 'Making per gram' : 'Making amount');
            document.querySelector('label[for="weigh-wastage-value"]').textContent = wastageCode === 'percentage' ? 'Wastage %' : (wastageCode === 'per_gram' ? 'Wastage per gram' : 'Wastage amount');
        }

        function previewWeigh() {
            syncPurities();
            const preview = document.getElementById('weigh-price');
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            const location = option('weigh-location');
            const gross = Number(document.getElementById('weigh-gross').value || 0);
            if (gross <= 0) {
                preview.textContent = 'Enter the weight from the scale.';
                return null;
            }
            const net = gross - Number(document.getElementById('weigh-stone-g').value || 0) - Number(document.getElementById('weigh-other-g').value || 0);
            if (net <= 0) {
                preview.textContent = 'Net metal weight must be more than zero.';
                return null;
            }
            if (!metal || !purity || !location) {
                preview.textContent = 'Choose the metal, purity, and location.';
                return null;
            }
            const rate = currentRate(metal.dataset.id, purity.dataset.id, location.dataset.branch);
            if (!rate) {
                preview.textContent = 'Set today\'s ' + metal.text + ' ' + purity.text + ' rate before billing this piece.';
                return null;
            }
            const rateValue = Number(rate.rate_per_gram);
            const making = option('weigh-making');
            const wastage = option('weigh-wastage');
            const makingCode = making && making.value ? (making.dataset.code || 'fixed') : 'fixed';
            const wastageCode = wastage && wastage.value ? (wastage.dataset.code || 'fixed') : 'fixed';
            const makingValue = making && making.value ? Number(document.getElementById('weigh-making-value').value || 0) : 0;
            const wastageValue = wastage && wastage.value ? Number(document.getElementById('weigh-wastage-value').value || 0) : 0;
            const stone = Number(document.getElementById('weigh-stone-value').value || 0);
            const metalAmount = net * rateValue;
            const wastageAmount = wastageCode === 'percentage' ? net * wastageValue / 100 * rateValue : (wastageCode === 'per_gram' ? net * wastageValue : wastageValue);
            const makingAmount = makingCode === 'per_gram' ? net * makingValue : (makingCode === 'percentage' ? metalAmount * makingValue / 100 : makingValue);
            const line = round2(metalAmount + wastageAmount + makingAmount + stone);
            chargeLabels();
            preview.textContent = metal.text + ' ' + purity.text + ' · net ' + net.toFixed(3) + ' g · rate ' + money.format(rateValue) + '/g · amount ' + money.format(line);
            return line;
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
            if (query.trim() === '') {
                box.innerHTML = '<div class="p-2 text-secondary small">Type a mobile number or name.</div>';
                return;
            }
            if (rows.length === 0) {
                box.innerHTML = '<div class="p-2 text-secondary small">No customer found. Use New customer.</div>';
                return;
            }
            rows.forEach((row) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = row.name + (row.mobile ? ' · ' + row.mobile : '') + ' · ' + row.code;
                button.addEventListener('click', () => chooseCustomer(row));
                box.appendChild(button);
            });
        }

        function chooseCustomer(row) {
            customer = row;
            document.getElementById('customer-uuid').value = row.uuid;
            const chosen = document.getElementById('customer-chosen');
            chosen.classList.remove('d-none');
            chosen.textContent = 'Billing ' + row.name + (row.mobile ? ' · ' + row.mobile : '') + ' · ' + row.code;
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
            if (query.trim() === '') {
                box.innerHTML = '<div class="p-2 text-secondary small">Type a piece name, code, or barcode.</div>';
                return;
            }
            if (rows.length === 0) {
                box.innerHTML = '<div class="p-2 text-secondary small">No tagged piece found. Weigh it above and add it to this bill.</div>';
                return;
            }
            rows.forEach((row) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = row.code + ' · ' + row.name + ' · ' + row.metal + ' · ' + row.net + ' · ' + row.label;
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
                body.innerHTML = '<tr><td colspan="3" class="text-secondary">No pieces added yet.</td></tr>';
            }
            lines.forEach((line) => {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td>' + line.title + '</td><td class="text-end">' + money.format(line.amount) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button">Remove</button></td>';
                tr.querySelector('button').addEventListener('click', () => {
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
            document.getElementById('bill-note').textContent = customer && customer.walkin
                ? 'Walk-in must pay this total before you save.'
                : 'A named customer can leave the unpaid amount on their account.';
        }

        document.getElementById('customer-search').addEventListener('input', renderCustomers);
        document.getElementById('piece-search').addEventListener('input', renderPieces);
        document.getElementById('discount').addEventListener('input', renderTotals);
        document.getElementById('bill-form').addEventListener('input', (event) => {
            if (event.target.id && event.target.id.startsWith('weigh-')) previewWeigh();
        });
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
            const line = previewWeigh();
            if (name === '' || line === null) {
                error.textContent = name === '' ? 'Choose a product, such as Ring.' : document.getElementById('weigh-price').textContent;
                error.classList.remove('d-none');
                return;
            }
            error.classList.add('d-none');
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            freshLines.push({
                name,
                metal: metal.text + ' ' + purity.text,
                line,
                fields: {
                    name,
                    metal_uuid: metal.value,
                    purity_uuid: purity.value,
                    location_uuid: option('weigh-location').value,
                    gross_weight: document.getElementById('weigh-gross').value,
                    stone_weight: document.getElementById('weigh-stone-g').value,
                    other_weight: document.getElementById('weigh-other-g').value,
                    stone_value: document.getElementById('weigh-stone-value').value,
                    making_method_uuid: option('weigh-making').value,
                    making_value: document.getElementById('weigh-making-value').value,
                    wastage_method_uuid: option('weigh-wastage').value,
                    wastage_value: document.getElementById('weigh-wastage-value').value,
                },
            });
            document.getElementById('weigh-gross').value = '';
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
        renderCustomers();
        renderPieces();
        renderBill();
        previewWeigh();
    </script>
@endpush
