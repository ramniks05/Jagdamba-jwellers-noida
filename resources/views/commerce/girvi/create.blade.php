@extends('layouts.app')

@section('title', 'New girvi')

@section('content')
    <h1 class="page-title h3 mb-2">New girvi</h1>
    <p class="text-secondary">Add every piece the customer leaves. Each piece is priced from its own weight and rate. The loan and the monthly interest are calculated on the total gold value.</p>
    <form method="POST" action="{{ route('girvi.store') }}" id="girvi-form">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Customer</span>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#customer-modal"><i class="bi bi-person-plus"></i> New customer</button>
                    </div>
                    <div class="card-body">
                        <label class="form-label" for="customer-search">Search by mobile number or name</label>
                        <input class="form-control" id="customer-search" placeholder="Mobile, name, or code" autocomplete="off">
                        <div class="bill-results mt-2 d-none" id="customer-results"></div>
                        <div class="alert alert-warning mt-3 mb-0 d-none" id="customer-chosen"></div>
                        <div class="text-danger small mt-2 d-none" id="customer-error">Choose the customer. Girvi cannot be in the walk-in name.</div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Pieces kept in the shop</div>
                    <div class="card-body">
                        <div class="bill-products mb-3" id="girvi-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="description">Piece</label>
                                <input class="form-control" id="description" placeholder="Chain">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="metal">Metal</label>
                                <select class="form-select" id="metal">
                                    @foreach ($metals as $metal)
                                        <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="purity">Purity</label>
                                <select class="form-select" id="purity">
                                    @foreach ($metals as $metal)
                                        @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                            <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="gross">Gross weight (g)</label>
                                <input class="form-control bill-weight" id="gross">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="stone">Stone g</label>
                                <input class="form-control" id="stone" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rate">Rate per gram</label>
                                <input class="form-control" id="rate">
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-center">
                                <div class="text-danger small d-none" id="piece-error"></div>
                                <button class="btn btn-outline-primary" id="add-piece" type="button"><i class="bi bi-plus-lg"></i> Add this piece</button>
                            </div>
                            <div class="col-12">
                                <div id="piece-fields"></div>
                                <div class="list-group" id="added-pieces"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Notes</label>
                                <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Loan and interest</div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="loan_mode" id="loan-percent-mode" value="percent" @checked(old('loan_mode', 'percent') !== 'amount')>
                            <label class="form-check-label" for="loan-percent-mode">Percent of the gold value</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="loan_mode" id="loan-amount-mode" value="amount" @checked(old('loan_mode') === 'amount')>
                            <label class="form-check-label" for="loan-amount-mode">Enter one loan amount</label>
                        </div>
                        <div class="mb-3" id="percent-wrap">
                            <label class="form-label" for="loan-percent">Loan percent</label>
                            <input class="form-control" id="loan-percent" name="loan_percent" value="{{ old('loan_percent', '75') }}">
                        </div>
                        <div class="mb-3 d-none" id="amount-wrap">
                            <label class="form-label" for="loan-amount">Loan amount</label>
                            <input class="form-control" id="loan-amount" name="loan_amount" value="{{ old('loan_amount') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="interest">Interest % per month</label>
                            <input class="form-control" id="interest" name="interest_percent" value="{{ old('interest_percent', '2') }}" required>
                        </div>
                        <ul class="list-group list-group-flush mb-3" id="girvi-lines"></ul>
                        <p class="fw-semibold mb-0" id="girvi-note"></p>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-safe"></i> Save girvi</button>
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
                    <p class="text-secondary">The customer code is assigned when you save. Girvi cannot be in the walk-in name.</p>
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
                    <button class="btn btn-primary" id="save-customer" type="button">Save and use on this girvi</button>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="girvi-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'branchId' => $branchId,
        'customers' => $customers->map(fn ($customer) => [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'code' => $customer->code,
            'mobile' => $customer->mobile,
        ])->values(),
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
        const girvi = JSON.parse(document.getElementById('girvi-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
        const rateInput = document.getElementById('rate');
        const pieces = [];
        let suggestedRate = '';
        let chosen = null;

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function round3(value) {
            return Math.round((value + Number.EPSILON) * 1000) / 1000;
        }

        function selected(id) {
            const field = document.getElementById(id);
            return field.options[field.selectedIndex];
        }

        function currentRate(metalId, purityId) {
            const rows = girvi.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
            return rows.find((rate) => String(rate.branch_id) === String(girvi.branchId))
                || rows.find((rate) => rate.branch_id === null)
                || rows[0]
                || null;
        }

        function syncPurities() {
            const metal = selected('metal');
            const purity = document.getElementById('purity');
            if (!metal) return;
            Array.from(purity.options).forEach((row) => {
                const show = row.dataset.metal === metal.dataset.id;
                row.hidden = !show;
                row.disabled = !show;
            });
            if (purity.selectedOptions[0]?.disabled) {
                const first = Array.from(purity.options).find((row) => !row.disabled);
                if (first) first.selected = true;
            }
        }

        function suggestRate() {
            const metal = selected('metal');
            const purity = selected('purity');
            const rate = metal && purity ? currentRate(metal.dataset.id, purity.dataset.id) : null;
            const next = rate ? Number(rate.rate_per_gram).toFixed(2) : '';
            if (rateInput.value === '' || rateInput.value === suggestedRate) {
                rateInput.value = next;
            }
            suggestedRate = next;
        }

        function escapeAttr(value) {
            return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function writePieces() {
            const fields = ['description', 'metal_uuid', 'purity_uuid', 'gross_weight', 'stone_weight', 'rate_per_gram'];
            document.getElementById('piece-fields').innerHTML = pieces.map((piece, index) => fields.map((field) => '<input type="hidden" name="pieces[' + index + '][' + field + ']" value="' + escapeAttr(piece[field]) + '">').join('')).join('');
            document.getElementById('added-pieces').innerHTML = pieces.map((piece, index) => '<div class="list-group-item d-flex justify-content-between align-items-center"><span>' + escapeAttr(piece.description) + ' · ' + escapeAttr(piece.metal) + ' · ' + piece.net.toFixed(3) + ' g</span><button class="btn btn-outline-secondary btn-sm" type="button" data-remove="' + index + '">Remove</button></div>').join('');
        }

        function addPiece() {
            syncPurities();
            suggestRate();
            const error = document.getElementById('piece-error');
            const description = document.getElementById('description').value.trim();
            const gross = Number(document.getElementById('gross').value || 0);
            const stone = Number(document.getElementById('stone').value || 0);
            const rate = Number(rateInput.value || 0);
            const net = round3(gross - stone);
            if (description === '' || gross <= 0 || stone > gross || rate <= 0 || net <= 0) {
                error.textContent = description === ''
                    ? 'Choose the piece, such as Chain or Ring.'
                    : (stone > gross ? 'Stone weight cannot be more than the gross weight.' : 'Enter the weight and the rate.');
                error.classList.remove('d-none');
                return;
            }
            const metal = selected('metal');
            const purity = selected('purity');
            pieces.push({
                description,
                metal_uuid: metal.value,
                purity_uuid: purity.value,
                metal: metal.text + ' ' + purity.text,
                gross_weight: document.getElementById('gross').value,
                stone_weight: document.getElementById('stone').value || '0',
                rate_per_gram: rateInput.value,
                net,
                gold: round2(net * rate),
            });
            document.getElementById('description').value = '';
            document.getElementById('gross').value = '';
            document.getElementById('stone').value = '0';
            error.classList.add('d-none');
            renderGirvi();
            document.getElementById('description').focus();
        }

        function renderGirvi() {
            syncPurities();
            suggestRate();
            writePieces();
            const useAmount = document.getElementById('loan-amount-mode').checked;
            document.getElementById('percent-wrap').classList.toggle('d-none', useAmount);
            document.getElementById('amount-wrap').classList.toggle('d-none', !useAmount);
            const note = document.getElementById('girvi-note');
            const list = document.getElementById('girvi-lines');
            const interestPercent = Number(document.getElementById('interest').value || 0);
            const gold = round2(pieces.reduce((sum, piece) => sum + piece.gold, 0));
            const net = round3(pieces.reduce((sum, piece) => sum + piece.net, 0));
            if (pieces.length === 0) {
                list.innerHTML = '';
                note.textContent = 'Add the first piece. You can add more pieces on this same girvi.';
                return;
            }
            let principal = 0;
            if (useAmount) {
                principal = Number(document.getElementById('loan-amount').value || 0);
            } else {
                principal = round2(gold * Number(document.getElementById('loan-percent').value || 0) / 100);
            }
            if (principal <= 0 || principal > gold + 0.001) {
                list.innerHTML = pieces.map((piece) => line([piece.description, piece.gold])).join('');
                note.textContent = principal <= 0
                    ? 'Enter the loan percent or the loan amount.'
                    : 'The loan cannot be more than the gold value, ' + money.format(gold) + '.';
                return;
            }
            const monthInterest = round2(principal * interestPercent / 100);
            const rows = pieces.map((piece) => [piece.description, piece.gold]);
            rows.push(['Pieces', String(pieces.length)]);
            rows.push(['Net weight', net.toFixed(3) + ' g']);
            rows.push(['Gold value', gold]);
            rows.push(['Loan given now', principal]);
            rows.push(['Interest for 1 month', monthInterest]);
            rows.push(['To release after 1 month', round2(principal + monthInterest)]);
            list.innerHTML = rows.map(line).join('');
            note.textContent = chosen
                ? 'A part of a month is charged as one full month.'
                : 'Choose the customer. The loan is given in cash now.';
        }

        function line(row) {
            const value = typeof row[1] === 'number' ? money.format(row[1]) : row[1];
            const strong = row[0] === 'Loan given now' || row[0] === 'To release after 1 month' ? ' fw-semibold' : '';
            return '<li class="list-group-item d-flex justify-content-between px-0' + strong + '"><span>' + row[0] + '</span><span>' + value + '</span></li>';
        }

        function chooseCustomer(customer) {
            chosen = customer;
            document.getElementById('customer-uuid').value = customer.uuid;
            document.getElementById('customer-error').classList.add('d-none');
            document.getElementById('customer-results').classList.add('d-none');
            const box = document.getElementById('customer-chosen');
            box.classList.remove('d-none');
            box.textContent = customer.name + (customer.mobile ? ' · ' + customer.mobile : '') + (customer.code ? ' · ' + customer.code : '');
            renderGirvi();
        }

        document.getElementById('customer-search').addEventListener('input', () => {
            const query = document.getElementById('customer-search').value.trim().toLowerCase();
            const box = document.getElementById('customer-results');
            if (query.length < 1) {
                box.classList.add('d-none');
                box.innerHTML = '';
                return;
            }
            const rows = girvi.customers.filter((customer) => [customer.name, customer.mobile, customer.code].join(' ').toLowerCase().includes(query)).slice(0, 8);
            box.classList.remove('d-none');
            box.innerHTML = rows.length
                ? rows.map((customer) => '<button type="button" data-uuid="' + customer.uuid + '">' + customer.name + (customer.mobile ? ' · ' + customer.mobile : '') + '</button>').join('')
                : '<div class="p-2 text-secondary">No customer found. Add one with New customer.</div>';
        });
        document.getElementById('customer-results').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const customer = girvi.customers.find((row) => row.uuid === button.dataset.uuid);
            if (customer) chooseCustomer(customer);
        });
        document.getElementById('girvi-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#girvi-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            document.getElementById('description').value = button.dataset.name;
            document.getElementById('gross').focus();
            renderGirvi();
        });
        document.getElementById('girvi-form').addEventListener('input', renderGirvi);
        document.getElementById('girvi-form').addEventListener('change', renderGirvi);
        document.getElementById('add-piece').addEventListener('click', addPiece);
        document.getElementById('added-pieces').addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove]');
            if (!button) return;
            pieces.splice(Number(button.dataset.remove), 1);
            renderGirvi();
        });
        document.getElementById('girvi-form').addEventListener('submit', (event) => {
            if (!document.getElementById('customer-uuid').value) {
                event.preventDefault();
                document.getElementById('customer-error').classList.remove('d-none');
                document.getElementById('customer-search').focus();
            }
            if (pieces.length === 0) {
                event.preventDefault();
                document.getElementById('piece-error').textContent = 'Add at least one piece.';
                document.getElementById('piece-error').classList.remove('d-none');
            }
        });
        document.getElementById('save-customer').addEventListener('click', async () => {
            const error = document.getElementById('customer-modal-error');
            error.classList.add('d-none');
            const response = await fetch(girvi.customerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': girvi.csrf,
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
            girvi.customers.push(payload);
            chooseCustomer(payload);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customer-modal')).hide();
            document.getElementById('new-customer-name').value = '';
            document.getElementById('new-customer-mobile').value = '';
        });
        const preset = girvi.customers.find((customer) => customer.uuid === document.getElementById('customer-uuid').value);
        if (preset) chooseCustomer(preset);
        renderGirvi();
    </script>
@endpush
