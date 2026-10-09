@extends('layouts.app')

@section('title', 'New girvi')

@section('content')
    <h1 class="page-title h3 mb-1">New girvi</h1>
    <p class="text-secondary mb-3">Weigh every piece the customer leaves. Each piece keeps its own metal, karat and rate, and the loan is worked out on the total value.</p>
    <form method="POST" action="{{ route('girvi.store') }}" id="girvi-form" autocomplete="off">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
        <div id="piece-fields"></div>
        <div class="row g-3">
            <div class="col-lg-7">
                @include('commerce.partials.customer-picker', ['addLabel' => 'Add to girvi', 'errorText' => 'Choose the customer. Girvi cannot be in the walk-in name.'])
                <div class="card mb-3">
                    <div class="card-header bg-white">Pieces kept in the shop</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Piece</div>
                        <div class="bill-products" id="girvi-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="description">Name</label>
                            <input class="form-control" id="description" placeholder="Chain" maxlength="160">
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Weight and rate</div>
                            <div class="stone-box mt-0">
                                <div class="metal-row">
                                    <div>
                                        <label for="metal">Metal</label>
                                        <select class="form-select" id="metal">
                                            @foreach ($metals as $metal)
                                                <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}">{{ $metal->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="purity">Purity</label>
                                        <select class="form-select" id="purity">
                                            @foreach ($metals as $metal)
                                                @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                                    <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}">{{ $purity->name }}</option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="gross">Gross g</label>
                                        <input class="form-control metal-net" id="gross" inputmode="decimal" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="stone">Stone g</label>
                                        <input class="form-control" id="stone" inputmode="decimal" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="rate">Rate / g</label>
                                        <input class="form-control" id="rate" inputmode="decimal">
                                    </div>
                                </div>
                                <div class="form-text" id="rate-note"></div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                                <div class="small fw-semibold" id="piece-preview"></div>
                                <button class="btn btn-outline-primary" id="add-piece" type="button"><i class="bi bi-plus-lg"></i> Add this piece</button>
                            </div>
                            <div class="text-danger small mt-2 d-none" id="piece-error"></div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Note</div>
                            <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Hallmark seen, one stone missing">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">This girvi</div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Piece</th><th class="text-end">Value</th><th></th></tr></thead>
                            <tbody id="girvi-lines"><tr><td colspan="3">No pieces added yet.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="card-body bill-sums">
                        <div class="bill-block" id="weight-rows"></div>
                        <div class="bill-grand"><span>Total value</span><strong id="sum-value">0.00</strong></div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Loan and interest</div>
                    <div class="card-body bill-sums">
                        <div class="btn-group w-100 mb-3" role="group" aria-label="Loan on">
                            <input class="btn-check" type="radio" name="loan_mode" id="loan-percent-mode" value="percent" @checked(old('loan_mode', 'percent') !== 'amount')>
                            <label class="btn btn-outline-primary" for="loan-percent-mode">% of value</label>
                            <input class="btn-check" type="radio" name="loan_mode" id="loan-amount-mode" value="amount" @checked(old('loan_mode') === 'amount')>
                            <label class="btn btn-outline-primary" for="loan-amount-mode">One amount</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-6" id="percent-wrap">
                                <label class="form-label" for="loan-percent">Loan %</label>
                                <input class="form-control @error('loan_percent') is-invalid @enderror" id="loan-percent" name="loan_percent" value="{{ old('loan_percent', '75') }}" inputmode="decimal">
                            </div>
                            <div class="col-6 d-none" id="amount-wrap">
                                <label class="form-label" for="loan-amount">Loan ₹</label>
                                <input class="form-control @error('loan_amount') is-invalid @enderror" id="loan-amount" name="loan_amount" value="{{ old('loan_amount') }}" inputmode="decimal">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="interest">Interest % / month</label>
                                <input class="form-control @error('interest_percent') is-invalid @enderror" id="interest" name="interest_percent" value="{{ old('interest_percent', '2') }}" inputmode="decimal" required>
                            </div>
                        </div>
                        @foreach (['customer_uuid', 'pieces', 'loan_percent', 'loan_amount', 'interest_percent'] as $field)
                            @error($field)
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        @endforeach
                        <div class="weigh-quote is-empty mt-3" id="loan-empty">Add the first piece</div>
                        <div class="d-none mt-2" id="loan-sums">
                            <div class="bill-grand"><span>Loan given now</span><strong id="sum-loan">0.00</strong></div>
                            <div class="bill-block">
                                <div class="bill-row"><span id="sum-interest-label">Interest each month</span><span id="sum-interest">0.00</span></div>
                                <div class="bill-row bill-row-sub"><span>To release after 1 month</span><span id="sum-release">0.00</span></div>
                            </div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2">The loan is given in cash now. A part of a month is charged as one full month.</p>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-safe"></i> Save girvi and give loan</button>
            </div>
        </div>
    </form>

    @include('commerce.partials.customer-modals', ['document' => 'girvi', 'note' => 'The customer code is assigned when you save. Girvi cannot be in the walk-in name.'])

    <script type="application/json" id="girvi-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
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
        'pieces' => collect(old('pieces', []))->filter(fn ($row) => is_array($row))->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
    <script>
        const girvi = JSON.parse(document.getElementById('girvi-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const $ = (id) => document.getElementById(id);
        const rateInput = $('rate');
        const pieces = [];
        let suggestedRate = '';

        const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
        const round3 = (value) => Math.round((value + Number.EPSILON) * 1000) / 1000;

        function selected(id) {
            const field = $(id);
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
            const purity = $('purity');
            if (!metal) return;
            Array.from(purity.options).forEach((row) => {
                const visible = row.dataset.metal === metal.dataset.id;
                row.hidden = !visible;
                row.disabled = !visible;
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
            $('rate-note').textContent = rate
                ? 'Today’s ' + metal.text + ' ' + purity.text + ' rate is ' + money.format(Number(rate.rate_per_gram)) + ' / g. Change it if you value girvi at a lower rate.'
                : 'No rate saved for ' + (metal ? metal.text : '') + ' ' + (purity ? purity.text : '') + '. Enter the rate per gram.';
        }

        function metalName(metalUuid, purityUuid) {
            const metal = Array.from($('metal').options).find((row) => row.value === metalUuid);
            const purity = Array.from($('purity').options).find((row) => row.value === purityUuid);
            return [metal ? metal.text : '', purity ? purity.text : ''].join(' ').trim();
        }

        function makePiece(row) {
            const gross = Number(row.gross_weight || 0);
            const stone = Number(row.stone_weight || 0);
            const net = round3(gross - stone);
            return {
                description: String(row.description || ''),
                metal_uuid: row.metal_uuid,
                purity_uuid: row.purity_uuid,
                gross_weight: String(row.gross_weight),
                stone_weight: String(row.stone_weight || '0'),
                rate_per_gram: String(row.rate_per_gram),
                metal: metalName(row.metal_uuid, row.purity_uuid),
                net,
                value: round2(net * Number(row.rate_per_gram || 0)),
            };
        }

        function draftPiece() {
            syncPurities();
            suggestRate();
            const gross = Number($('gross').value || 0);
            const stone = Number($('stone').value || 0);
            const rate = Number(rateInput.value || 0);
            const description = $('description').value.trim();
            let error = '';
            if (description === '') error = 'Choose the piece, such as Chain or Ring.';
            else if (gross <= 0) error = 'Enter the gross weight.';
            else if (stone > gross) error = 'Stone weight cannot be more than the gross weight.';
            else if (round3(gross - stone) <= 0) error = 'Enter a gross weight greater than the stone weight.';
            else if (rate <= 0) error = 'Enter the rate per gram.';
            return {
                error,
                piece: makePiece({
                    description,
                    metal_uuid: selected('metal').value,
                    purity_uuid: selected('purity').value,
                    gross_weight: $('gross').value,
                    stone_weight: $('stone').value || '0',
                    rate_per_gram: rateInput.value,
                }),
            };
        }

        function previewPiece() {
            const { piece } = draftPiece();
            const gross = Number($('gross').value || 0);
            $('piece-preview').textContent = gross > 0 && piece.net > 0 && Number(piece.rate_per_gram) > 0
                ? 'Net ' + piece.net.toFixed(3) + ' g × ' + money.format(Number(piece.rate_per_gram)) + ' = ' + money.format(piece.value)
                : '';
        }

        function addPiece() {
            const { error, piece } = draftPiece();
            const box = $('piece-error');
            if (error) {
                box.textContent = error;
                box.classList.remove('d-none');
                return;
            }
            pieces.push(piece);
            $('description').value = '';
            $('gross').value = '';
            $('stone').value = '';
            document.querySelectorAll('#girvi-products button').forEach((row) => row.classList.remove('active'));
            box.classList.add('d-none');
            renderGirvi();
            $('description').focus();
        }

        function writeFields() {
            const holder = $('piece-fields');
            holder.innerHTML = '';
            const fields = ['description', 'metal_uuid', 'purity_uuid', 'gross_weight', 'stone_weight', 'rate_per_gram'];
            pieces.forEach((piece, index) => fields.forEach((field) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pieces[' + index + '][' + field + ']';
                input.value = piece[field];
                holder.appendChild(input);
            }));
        }

        function renderLines() {
            const body = $('girvi-lines');
            body.innerHTML = '';
            if (pieces.length === 0) {
                body.innerHTML = '<tr><td colspan="3">No pieces added yet.</td></tr>';
                return;
            }
            pieces.forEach((piece, index) => {
                const tr = document.createElement('tr');
                const cell = document.createElement('td');
                cell.className = 'bill-line-cell';
                cell.colSpan = 2;
                const head = document.createElement('div');
                head.className = 'bill-line-head';
                const name = document.createElement('strong');
                name.textContent = piece.description;
                const total = document.createElement('strong');
                total.className = 'bill-line-total';
                total.textContent = money.format(piece.value);
                head.append(name, total);
                const meta = document.createElement('div');
                meta.className = 'bill-line-meta';
                meta.textContent = [
                    piece.metal,
                    'Gross ' + Number(piece.gross_weight).toFixed(3) + ' g',
                    Number(piece.stone_weight) > 0 ? 'Stone ' + Number(piece.stone_weight).toFixed(3) + ' g' : '',
                    'Net ' + piece.net.toFixed(3) + ' g',
                    money.format(Number(piece.rate_per_gram)) + ' / g',
                ].filter(Boolean).join(' · ');
                cell.append(head, meta);
                const action = document.createElement('td');
                action.className = 'text-end bill-line-cell';
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'bill-remove';
                remove.title = 'Remove';
                remove.setAttribute('aria-label', 'Remove');
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    pieces.splice(index, 1);
                    renderGirvi();
                });
                action.appendChild(remove);
                tr.append(cell, action);
                body.appendChild(tr);
            });
        }

        function renderGirvi() {
            writeFields();
            renderLines();
            previewPiece();
            const useAmount = $('loan-amount-mode').checked;
            $('percent-wrap').classList.toggle('d-none', useAmount);
            $('amount-wrap').classList.toggle('d-none', !useAmount);

            const groups = new Map();
            pieces.forEach((piece) => groups.set(piece.metal, round3((groups.get(piece.metal) || 0) + piece.net)));
            $('weight-rows').innerHTML = '';
            groups.forEach((grams, name) => {
                const row = document.createElement('div');
                row.className = 'bill-row bill-row-muted';
                const label = document.createElement('span');
                label.textContent = name + ' net';
                const value = document.createElement('span');
                value.textContent = grams.toFixed(3) + ' g';
                row.append(label, value);
                $('weight-rows').appendChild(row);
            });
            $('weight-rows').classList.toggle('d-none', groups.size === 0);
            const total = round2(pieces.reduce((sum, piece) => sum + piece.value, 0));
            $('sum-value').textContent = money.format(total);

            const empty = $('loan-empty');
            const sums = $('loan-sums');
            const stop = (message) => {
                empty.textContent = message;
                empty.classList.remove('d-none');
                sums.classList.add('d-none');
            };
            if (pieces.length === 0) return stop('Add the first piece');
            const principal = useAmount
                ? round2(Number($('loan-amount').value || 0))
                : round2(total * Number($('loan-percent').value || 0) / 100);
            if (principal <= 0) return stop(useAmount ? 'Enter the loan amount' : 'Enter the loan percent');
            if (principal > total + 0.001) return stop('The loan cannot be more than the total value, ' + money.format(total));
            const percent = Number($('interest').value || 0);
            const interest = round2(principal * percent / 100);
            empty.classList.add('d-none');
            sums.classList.remove('d-none');
            $('sum-loan').textContent = money.format(principal);
            $('sum-interest-label').textContent = 'Interest ' + percent + '% each month';
            $('sum-interest').textContent = money.format(interest);
            $('sum-release').textContent = money.format(round2(principal + interest));
        }

        const picker = customerPicker({
            customers: girvi.customers,
            createUrl: girvi.customerUrl,
            showUrl: girvi.customerShowUrl,
            csrf: girvi.csrf,
            addLabel: 'Add to girvi',
            chipLabel: 'Girvi of',
        });
        $('girvi-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#girvi-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            $('description').value = button.dataset.name;
            $('gross').focus();
        });
        $('add-piece').addEventListener('click', addPiece);
        ['description', 'gross', 'stone', 'rate'].forEach((id) => $(id).addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            addPiece();
        }));
        $('girvi-form').addEventListener('input', renderGirvi);
        $('girvi-form').addEventListener('change', renderGirvi);
        $('girvi-form').addEventListener('submit', (event) => {
            const hasCustomer = picker.ensure();
            if (pieces.length === 0) {
                $('piece-error').textContent = 'Add at least one piece.';
                $('piece-error').classList.remove('d-none');
            }
            if (!hasCustomer || pieces.length === 0) event.preventDefault();
        });
        syncPurities();
        const usual = Array.from($('purity').options).find((row) => !row.disabled && row.text.trim().toUpperCase() === '22K');
        if (usual) usual.selected = true;
        girvi.pieces.forEach((row) => pieces.push(makePiece(row)));
        renderGirvi();
    </script>
@endpush
