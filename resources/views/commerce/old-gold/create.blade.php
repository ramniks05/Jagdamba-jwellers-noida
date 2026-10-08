@extends('layouts.app')

@section('title', 'Old gold exchange')

@section('content')
    <h1 class="page-title h3 mb-1">Old gold exchange</h1>
    <p class="text-secondary mb-3">Weigh and test the customer’s old gold or silver. The value is worked out before you save. Pay it now, or keep it to the customer’s credit and take it off their next bill.</p>
    <form method="POST" action="{{ route('old-gold.store') }}" id="exchange-form" autocomplete="off">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
        <div class="row g-3">
            <div class="col-lg-7">
                @include('commerce.partials.customer-picker', ['addLabel' => 'Add to exchange'])
                <div class="card mb-3">
                    <div class="card-header bg-white">Old gold received</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Weight</div>
                        <div class="stone-box mt-0">
                            <div class="metal-row">
                                <div>
                                    <label for="metal">Metal</label>
                                    <select class="form-select" id="metal" name="metal_uuid" required>
                                        @foreach ($metals as $metal)
                                            <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="purity">Purity</label>
                                    <select class="form-select" id="purity" name="purity_uuid" required>
                                        @foreach ($metals as $metal)
                                            @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                                <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="gross">Gross g</label>
                                    <input class="form-control metal-net" id="gross" name="gross_weight" value="{{ old('gross_weight') }}" inputmode="decimal" placeholder="0.000" required>
                                </div>
                                <div>
                                    <label for="stone">Stone g</label>
                                    <input class="form-control" id="stone" name="stone_weight" value="{{ old('stone_weight') }}" inputmode="decimal" placeholder="0.000">
                                </div>
                                <div>
                                    <label for="net">Net g</label>
                                    <input class="form-control" id="net" readonly tabindex="-1" placeholder="0.000">
                                </div>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Value</div>
                            <div class="stone-box mt-0">
                                <div class="metal-row">
                                    <div>
                                        <label for="loss">Loss %</label>
                                        <input class="form-control" id="loss" name="melting_loss_percent" value="{{ old('melting_loss_percent') }}" inputmode="decimal" placeholder="0">
                                    </div>
                                    <div>
                                        <label for="fine">Fine g</label>
                                        <input class="form-control" id="fine" readonly tabindex="-1" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="rate">Rate / g</label>
                                        <input class="form-control metal-net" id="rate" name="rate_per_gram" value="{{ old('rate_per_gram') }}" inputmode="decimal" required>
                                    </div>
                                    <div>
                                        <label for="deduction">Less ₹</label>
                                        <input class="form-control" id="deduction" name="deduction_amount" value="{{ old('deduction_amount') }}" inputmode="decimal" placeholder="0">
                                    </div>
                                    <div>
                                        <label for="value">Value</label>
                                        <input class="form-control" id="value" readonly tabindex="-1" placeholder="0">
                                    </div>
                                </div>
                                <div class="form-text" id="rate-note"></div>
                                <div class="form-text">Loss % is the melting loss on the net weight. Less ₹ is any extra rupee cut, for example testing charges.</div>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Testing</div>
                            <label class="form-label" for="testing">Testing result</label>
                            <input class="form-control" id="testing" name="testing_result" value="{{ old('testing_result') }}" maxlength="200" placeholder="Karat meter 91.6%, touchstone 22K">
                            <div class="bill-products mt-2" id="testing-chips">
                                @foreach (['Touchstone', 'Karat meter', 'Acid test', 'Hallmarked', 'Our shop piece'] as $test)
                                    <button type="button" data-test="{{ $test }}">{{ $test }}</button>
                                @endforeach
                            </div>
                            <label class="form-label mt-3" for="notes">What was received</label>
                            <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="2 old bangles, 1 broken chain">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Valuation</div>
                    <div class="card-body bill-sums">
                        <div class="weigh-quote is-empty" id="value-empty">Enter the gross weight</div>
                        <div id="value-sums" class="d-none">
                            <div class="bill-block mt-0">
                                <div class="bill-row bill-row-muted"><span>Gross weight</span><span id="sum-gross">0.000 g</span></div>
                                <div class="bill-row bill-row-muted d-none" id="sum-stone-row"><span>Less stone / dust</span><span id="sum-stone">0.000 g</span></div>
                                <div class="bill-row bill-row-muted d-none" id="sum-loss-row"><span id="sum-loss-label">Less melting loss</span><span id="sum-loss">0.000 g</span></div>
                                <div class="bill-row bill-row-sub"><span>Fine weight</span><span id="sum-fine">0.000 g</span></div>
                            </div>
                            <div class="bill-block">
                                <div class="bill-row"><span id="sum-rate-label">At rate</span><span id="sum-metal">₹0.00</span></div>
                                <div class="bill-row d-none" id="sum-deduction-row"><span>Less deduction</span><span id="sum-deduction">− ₹0.00</span></div>
                            </div>
                            <div class="bill-grand"><span>Old gold value</span><strong id="sum-value">₹0.00</strong></div>
                            <div id="sum-account" class="d-none">
                                <div class="bill-row d-none" id="sum-due-row"><span>Customer already owes</span><span id="sum-due">− ₹0.00</span></div>
                                <div class="bill-row d-none" id="sum-credit-row"><span>Already to their credit</span><span id="sum-credit">₹0.00</span></div>
                                <div class="bill-grand"><span>Can be paid to customer</span><strong id="sum-cash">₹0.00</strong></div>
                            </div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2" id="price-note"></p>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Pay the customer</span>
                        <button class="btn btn-outline-secondary btn-sm" id="use-refund" type="button">Pay full</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label" for="method">Paid by</label>
                                <select class="form-select" id="method" name="method">
                                    @foreach ($methods as $method)
                                        <option value="{{ $method->value }}" @selected(old('method', 'cash') === $method->value)>{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="refund">Amount ₹</label>
                                <input class="form-control @error('refund') is-invalid @enderror" id="refund" name="refund" value="{{ old('refund') }}" inputmode="decimal" placeholder="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="reference">Reference</label>
                                <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" placeholder="UPI / cheque">
                            </div>
                        </div>
                        @error('refund')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="refund-help">Leave it empty to keep the value for the customer’s next bill.</div>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-arrow-left-right"></i> Save old gold</button>
            </div>
        </div>
    </form>

    @include('commerce.partials.customer-modals', ['document' => 'exchange', 'note' => 'The customer code is assigned when you save. Search by the mobile number next time.'])

    <script type="application/json" id="exchange-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
        'customers' => $customers->map(fn ($customer) => [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'code' => $customer->code,
            'mobile' => $customer->mobile,
            'walkin' => (bool) $customer->is_system,
            'balance' => $balances[$customer->id] ?? '0.00',
        ])->values(),
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
    <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
    <script>
        const exchange = JSON.parse(document.getElementById('exchange-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
        const $ = (id) => document.getElementById(id);
        const rateInput = $('rate');
        const refundInput = $('refund');
        let suggestedRate = '';
        let customer = null;
        let payable = 0;

        const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
        const round3 = (value) => Math.round((value + Number.EPSILON) * 1000) / 1000;
        const show = (id, visible) => $(id).classList.toggle('d-none', !visible);

        function selected(id) {
            const field = $(id);
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
                ? 'Today’s ' + metal.text + ' ' + purity.text + ' rate is ' + money.format(Number(rate.rate_per_gram)) + ' / g. Change it if you buy old gold at a different rate.'
                : 'No rate saved for ' + (metal ? metal.text : '') + ' ' + (purity ? purity.text : '') + '. Enter the buying rate per gram.';
        }

        function stop(message, keepWeights = false) {
            show('value-empty', true);
            show('value-sums', false);
            $('value-empty').textContent = message;
            if (!keepWeights) {
                $('net').value = '';
                $('fine').value = '';
            }
            $('value').value = '';
            $('price-note').textContent = '';
            payable = 0;
        }

        function renderPrice() {
            syncPurities();
            suggestRate();
            const gross = Number($('gross').value || 0);
            const stone = Number($('stone').value || 0);
            const loss = Number($('loss').value || 0);
            const rate = Number(rateInput.value || 0);
            const deduction = Math.max(0, Number($('deduction').value || 0));
            const help = $('refund-help');
            refundInput.readOnly = false;

            if (gross <= 0) return stop('Enter the gross weight');
            if (stone > gross) return stop('Stone weight cannot be more than the gross weight');
            if (loss < 0 || loss > 100) return stop('Melting loss must be between 0 and 100%');

            const net = round3(gross - stone);
            const fine = round3(net * (100 - loss) / 100);
            $('net').value = net.toFixed(3);
            $('fine').value = fine.toFixed(3);
            if (rate <= 0) return stop('Enter the rate per gram', true);

            const metalValue = round2(fine * rate);
            if (deduction > metalValue + 0.001) return stop('The deduction cannot be more than the metal value', true);

            const value = round2(metalValue - deduction);
            $('value').value = value.toFixed(2);
            show('value-empty', false);
            show('value-sums', true);
            $('sum-gross').textContent = gross.toFixed(3) + ' g';
            $('sum-stone').textContent = '− ' + stone.toFixed(3) + ' g';
            show('sum-stone-row', stone > 0);
            $('sum-loss-label').textContent = 'Less melting loss ' + loss + '%';
            $('sum-loss').textContent = '− ' + round3(net - fine).toFixed(3) + ' g';
            show('sum-loss-row', loss > 0);
            $('sum-fine').textContent = fine.toFixed(3) + ' g';
            $('sum-rate-label').textContent = fine.toFixed(3) + ' g × ' + money.format(rate);
            $('sum-metal').textContent = money.format(metalValue);
            $('sum-deduction').textContent = '− ' + money.format(deduction);
            show('sum-deduction-row', deduction > 0);
            $('sum-value').textContent = money.format(value);

            if (!customer) {
                show('sum-account', false);
                payable = 0;
                $('price-note').textContent = 'Choose the customer to see what can be paid.';
                return;
            }

            const balance = Number(customer.balance || 0);
            const due = balance > 0 ? round2(balance) : 0;
            const credit = balance < 0 ? round2(Math.abs(balance)) : 0;
            payable = round2(Math.max(0, value - due + credit));
            show('sum-account', due > 0 || credit > 0 || customer.walkin);
            $('sum-due').textContent = '− ' + money.format(Math.min(due, value));
            show('sum-due-row', due > 0);
            $('sum-credit').textContent = money.format(credit);
            show('sum-credit-row', credit > 0);
            $('sum-cash').textContent = money.format(payable);

            if (customer.walkin) {
                refundInput.value = payable.toFixed(2);
                refundInput.readOnly = true;
                help.textContent = 'A walk-in exchange is paid in full now.';
                $('price-note').textContent = 'To take the value off a new bill, choose or add the customer instead of walk-in.';
                return;
            }

            const paying = Math.max(0, Number(refundInput.value || 0));
            help.classList.toggle('text-danger', paying > payable);
            help.textContent = paying > payable
                ? 'Cannot pay more than ' + money.format(payable) + '.'
                : (paying > 0
                    ? (round2(payable - paying) > 0 ? money.format(round2(payable - paying)) + ' stays on the customer’s account.' : 'Paid in full.')
                    : 'Leave it empty to keep the value for the customer’s next bill.');
            $('price-note').textContent = due > 0
                ? 'What the customer already owes is cleared first.'
                : '';
        }

        const picker = customerPicker({
            customers: exchange.customers,
            createUrl: exchange.customerUrl,
            showUrl: exchange.customerShowUrl,
            csrf: exchange.csrf,
            addLabel: 'Add to exchange',
            chipLabel: 'Old gold from',
            onChange: (row) => {
                if (customer && customer.walkin && !(row && row.walkin)) refundInput.value = '';
                customer = row;
                renderPrice();
            },
        });
        $('testing-chips').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const field = $('testing');
            const parts = field.value.split(',').map((part) => part.trim()).filter(Boolean);
            const index = parts.indexOf(button.dataset.test);
            if (index === -1) parts.push(button.dataset.test); else parts.splice(index, 1);
            field.value = parts.join(', ');
            button.classList.toggle('active', index === -1);
        });
        $('exchange-form').addEventListener('input', renderPrice);
        $('exchange-form').addEventListener('change', renderPrice);
        $('exchange-form').addEventListener('submit', (event) => {
            if (!picker.ensure()) event.preventDefault();
        });
        if (!@json(old('purity_uuid'))) {
            syncPurities();
            const usual = Array.from($('purity').options).find((row) => !row.disabled && row.text.trim().toUpperCase() === '22K');
            if (usual) usual.selected = true;
        }
        document.querySelectorAll('#testing-chips button').forEach((button) => {
            button.classList.toggle('active', $('testing').value.split(',').map((part) => part.trim()).includes(button.dataset.test));
        });
        $('use-refund').addEventListener('click', () => {
            if (!refundInput.readOnly && payable > 0) {
                refundInput.value = payable.toFixed(2);
                renderPrice();
            }
        });
        renderPrice();
    </script>
@endpush
