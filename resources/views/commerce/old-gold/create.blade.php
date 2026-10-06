@extends('layouts.app')

@section('title', 'Old gold exchange')

@section('content')
    <h1 class="page-title h3 mb-2">Old gold exchange</h1>
    <p class="text-secondary">The gold value and the cash refund are shown before you save. Melting loss reduces the weight. Deduction is an extra rupee cut.</p>
    <form method="POST" action="{{ route('old-gold.store') }}" id="exchange-form">
        @csrf
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white">Old gold</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="customer">Customer</label>
                            <select class="form-select" id="customer" name="customer_uuid" required>
                                <option value="">Choose</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->uuid }}" data-balance="{{ $balances[$customer->id] ?? '0.00' }}" data-walkin="{{ $customer->is_system ? '1' : '0' }}" @selected(old('customer_uuid') === $customer->uuid)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="metal">Metal</label>
                            <select class="form-select" id="metal" name="metal_uuid" required>
                                @foreach ($metals as $metal)
                                    <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="purity">Purity</label>
                            <select class="form-select" id="purity" name="purity_uuid" required>
                                @foreach ($metals as $metal)
                                    @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                        <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="gross">Gross weight (g)</label>
                            <input class="form-control" id="gross" name="gross_weight" value="{{ old('gross_weight') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="stone">Stone g</label>
                            <input class="form-control" id="stone" name="stone_weight" value="{{ old('stone_weight', '0') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="loss">Melting loss %</label>
                            <input class="form-control" id="loss" name="melting_loss_percent" value="{{ old('melting_loss_percent', '0') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="rate">Rate per gram</label>
                            <input class="form-control" id="rate" name="rate_per_gram" value="{{ old('rate_per_gram') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="deduction">Deduction</label>
                            <input class="form-control" id="deduction" name="deduction_amount" value="{{ old('deduction_amount', '0') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="testing">Testing result</label>
                            <input class="form-control" id="testing" name="testing_result" value="{{ old('testing_result') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Price and refund</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3" id="price-lines"></ul>
                        <div class="border-top pt-3">
                            <div class="text-secondary">Cash refund</div>
                            <div class="h3 mb-1" id="refund-figure">—</div>
                            <p class="mb-0 text-secondary" id="price-note"></p>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Pay now</span>
                        <button class="btn btn-outline-secondary btn-sm" id="use-refund" type="button">Pay this refund</button>
                    </div>
                    <div class="card-body">
                        <label class="form-label" for="refund">Cash given now</label>
                        <input class="form-control" id="refund" name="refund" value="{{ old('refund', '0') }}">
                        <div class="form-text" id="refund-help">Leave 0 to keep the gold value on the customer account.</div>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Save exchange</button>
            </div>
        </div>
    </form>
    <script type="application/json" id="exchange-config">{!! json_encode([
        'branchId' => $branchId,
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
        const exchange = JSON.parse(document.getElementById('exchange-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
        const rateInput = document.getElementById('rate');
        let suggestedRate = '';

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
            const rows = exchange.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
            return rows.find((rate) => String(rate.branch_id) === String(exchange.branchId))
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

        function renderPrice() {
            syncPurities();
            suggestRate();
            const note = document.getElementById('price-note');
            const list = document.getElementById('price-lines');
            const figure = document.getElementById('refund-figure');
            const help = document.getElementById('refund-help');
            const refundInput = document.getElementById('refund');
            const customer = selected('customer');
            const gross = Number(document.getElementById('gross').value || 0);
            const stone = Number(document.getElementById('stone').value || 0);
            const loss = Number(document.getElementById('loss').value || 0);
            const rate = Number(rateInput.value || 0);
            const deduction = Math.max(0, Number(document.getElementById('deduction').value || 0));
            const rows = [];

            figure.textContent = '—';
            document.getElementById('exchange-form').dataset.refund = '';
            refundInput.readOnly = false;

            if (gross <= 0) {
                list.innerHTML = '';
                note.textContent = 'Enter the gross weight.';
                return;
            }
            if (stone > gross) {
                list.innerHTML = '';
                note.textContent = 'Stone weight cannot be more than the gross weight.';
                return;
            }
            if (loss < 0 || loss > 100) {
                list.innerHTML = '';
                note.textContent = 'Melting loss must be between 0 and 100 percent.';
                return;
            }
            if (rate <= 0) {
                list.innerHTML = '';
                note.textContent = 'Enter the rate per gram. Today\'s rate fills in when that purity has one saved.';
                return;
            }

            const net = round3(gross - stone);
            const melted = round3(net * (100 - loss) / 100);
            const metalValue = melted * rate;
            if (deduction > metalValue + 0.001) {
                list.innerHTML = '';
                note.textContent = 'The deduction cannot be more than the metal value.';
                return;
            }

            const gold = round2(metalValue - deduction);
            rows.push(['Net weight', net.toFixed(3) + ' g']);
            rows.push(['Melting loss ' + loss + '%', melted.toFixed(3) + ' g']);
            rows.push(['Rate / g', rate]);
            rows.push(['Metal value', round2(metalValue)]);
            if (deduction > 0) rows.push(['Deduction', deduction]);
            rows.push(['Gold value', gold]);

            if (!customer || !customer.value) {
                list.innerHTML = rows.map(line).join('');
                note.textContent = 'Choose a customer to see the cash refund.';
                return;
            }

            const balance = Number(customer.dataset.balance || 0);
            const due = balance > 0 ? round2(balance) : 0;
            const credit = balance < 0 ? round2(Math.abs(balance)) : 0;
            const cash = round2(Math.max(0, gold - due + credit));
            if (due > 0) rows.push(['Already due', due]);
            if (credit > 0) rows.push(['Already to their credit', credit]);
            if (due > 0) rows.push(['Adjusted on the bill', round2(Math.min(due, gold))]);

            list.innerHTML = rows.map(line).join('');
            figure.textContent = money.format(cash);
            document.getElementById('exchange-form').dataset.refund = cash.toFixed(2);

            const walkIn = customer.dataset.walkin === '1';
            if (walkIn) {
                refundInput.value = cash.toFixed(2);
                refundInput.readOnly = true;
                help.textContent = 'A walk-in exchange is paid in full. This amount is given in cash.';
                note.textContent = 'Walk-in is paid this full cash refund.';
                return;
            }

            const paying = Math.max(0, Number(refundInput.value || 0));
            const left = round2(Math.max(0, cash - paying));
            help.textContent = paying > cash
                ? 'Cash given cannot be more than the refund, ' + money.format(cash) + '.'
                : (paying > 0
                    ? money.format(left) + ' stays on the customer account.'
                    : 'Leave 0 to keep the gold value on the customer account.');
            note.textContent = due > 0
                ? 'Due is cleared first. The cash refund is what is left to pay.'
                : 'This is the cash that can be given for this old gold.';
        }

        function line(row) {
            const value = typeof row[1] === 'number' ? money.format(row[1]) : row[1];
            const strong = row[0] === 'Gold value' ? ' fw-semibold' : '';
            return '<li class="list-group-item d-flex justify-content-between px-0' + strong + '"><span>' + row[0] + '</span><span>' + value + '</span></li>';
        }

        document.getElementById('exchange-form').addEventListener('input', renderPrice);
        document.getElementById('exchange-form').addEventListener('change', renderPrice);
        document.getElementById('use-refund').addEventListener('click', () => {
            const refund = document.getElementById('exchange-form').dataset.refund;
            const field = document.getElementById('refund');
            if (refund && !field.readOnly) {
                field.value = refund;
                renderPrice();
            }
        });
        renderPrice();
    </script>
@endpush
