@extends('layouts.app')

@section('title', 'New order')

@section('content')
    @php
        $makingTitle = $makingMode === 'processing' ? 'Processing charge' : 'Making';
        $modeLabel = [
            'inside' => 'Making inside jewellery GST',
            'separate' => 'Making with own GST '.$makingGstPercent.'%',
            'processing' => 'Processing charge, no GST',
        ][$makingMode] ?? 'Making inside jewellery GST';
    @endphp
    <h1 class="page-title h3 mb-1">New order</h1>
    <p class="text-secondary mb-3">Book a piece the customer wants made. Today’s rate is locked on the order and the final bill uses the actual weight at that rate.</p>
    <form method="POST" action="{{ route('orders.store') }}" id="order-form" autocomplete="off">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
        <input type="hidden" name="estimated_making" id="estimated-making" value="{{ old('estimated_making', '0') }}">
        <div class="row g-3">
            <div class="col-lg-7">
                @include('commerce.partials.customer-picker', ['addLabel' => 'Add to order', 'errorText' => 'Choose the customer. An order cannot be in the walk-in name.'])
                <div class="card mb-3">
                    <div class="card-header bg-white">What to make</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Piece</div>
                        <div class="bill-products" id="order-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="description">Name</label>
                            <input class="form-control" id="description" name="description" value="{{ old('description') }}" placeholder="Bridal necklace set" maxlength="160" required>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Metal</div>
                            <div class="stone-box mt-0">
                                <div class="stone-box-head">
                                    <span class="weigh-step-label">Rate is locked today</span>
                                </div>
                                <div class="metal-row">
                                    <div>
                                        <label for="metal">Metal</label>
                                        <select class="form-select" id="metal" name="metal_uuid">
                                            @foreach ($metals as $metal)
                                                <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="purity">Purity</label>
                                        <select class="form-select" id="purity" name="purity_uuid">
                                            @foreach ($metals as $metal)
                                                @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                                    <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->name }}</option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="weight">Expected wt g</label>
                                        <input class="form-control metal-net" id="weight" name="expected_weight" value="{{ old('expected_weight') }}" inputmode="decimal" placeholder="0.000" required>
                                    </div>
                                    <div>
                                        <label for="order-rate">Rate / g</label>
                                        <input class="form-control" id="order-rate" readonly tabindex="-1" placeholder="—">
                                    </div>
                                    <div>
                                        <label for="order-metal-value">Value</label>
                                        <input class="form-control" id="order-metal-value" readonly tabindex="-1" placeholder="0">
                                    </div>
                                </div>
                                <div class="text-danger small mt-2 d-none" id="rate-missing"></div>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="charges-head">
                                <div class="weigh-section-title">Charges</div>
                                <span class="charges-mode" title="Set in Settings. The bill on delivery can change it.">{{ $modeLabel }}</span>
                            </div>
                            <div class="weigh-metrics">
                                <div class="weight-field">
                                    <label for="making-method">{{ $makingTitle }}</label>
                                    <div class="input-group">
                                        <select class="form-select charge-method" id="making-method" name="making_method">
                                            <option value="">None</option>
                                            @foreach ($making as $method)
                                                <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected(old('making_method', $making->firstWhere('code', 'percentage')?->uuid) === $method->uuid)>{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control charge-value" id="making-value" name="making_value" inputmode="decimal" value="{{ old('making_value', '0') }}" aria-label="{{ $makingTitle }} amount">
                                    </div>
                                </div>
                                <div class="weight-field">
                                    <label for="due-on">Delivery date</label>
                                    <input class="form-control" id="due-on" name="due_on" type="date" value="{{ old('due_on') }}" min="{{ now()->toDateString() }}">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label" for="design-notes">Design and size</label>
                                <textarea class="form-control" id="design-notes" name="design_notes" rows="2" placeholder="Ring size 14, name engraved inside, matte finish">{{ old('design_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">This order</div>
                    <div class="card-body bill-sums">
                        <div class="weigh-quote is-empty" id="order-empty">Enter the expected weight</div>
                        <div id="order-sums" class="d-none">
                            <div class="bill-block mt-0">
                                <div class="bill-row bill-row-muted"><span>Expected weight</span><span id="sum-weight">0.000 g</span></div>
                                <div class="bill-row bill-row-muted"><span>Rate locked</span><span id="sum-rate">0.00</span></div>
                                <div class="bill-row"><span id="sum-metal-label">Metal value</span><span id="sum-metal">0.00</span></div>
                                <div class="bill-row d-none" id="sum-making-row"><span id="sum-making-label">{{ $makingTitle }}</span><span id="sum-making">0.00</span></div>
                                <div class="bill-row bill-row-sub"><span>Pieces total</span><span id="sum-subtotal">0.00</span></div>
                            </div>
                            <div class="bill-block">
                                <div class="bill-row"><span id="sum-tax-label">GST {{ $gstPercent }}%</span><span id="sum-tax">0.00</span></div>
                                <div class="bill-row d-none" id="sum-making-tax-row"><span id="sum-making-tax-label">GST {{ $makingGstPercent }}% on making</span><span id="sum-making-tax">0.00</span></div>
                                <div class="bill-row d-none" id="sum-round-row"><span>Round off</span><span id="sum-round">0.00</span></div>
                            </div>
                            <div class="bill-grand"><span>Estimated bill</span><strong id="sum-total">0.00</strong></div>
                            <div class="bill-row"><span>Advance now</span><span id="sum-advance">− 0.00</span></div>
                            <div class="bill-grand"><span>Balance at delivery (about)</span><strong id="sum-balance">0.00</strong></div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2">Only an estimate. The bill on delivery uses the actual weight of the finished piece at this locked rate.</p>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Advance</div>
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
                                <label class="form-label" for="advance">Amount ₹</label>
                                <input class="form-control" id="advance" name="advance" value="{{ old('advance') }}" inputmode="decimal" placeholder="Amount" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="reference">Reference</label>
                                <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" placeholder="UPI / cheque">
                            </div>
                        </div>
                        <label class="form-label mt-3" for="notes">Note for the shop</label>
                        <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}" maxlength="1000">
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit" id="book-order"><i class="bi bi-journal-check"></i> Book order and take advance</button>
            </div>
        </div>
    </form>

    @include('commerce.partials.customer-modals', ['document' => 'order', 'note' => 'The customer code is assigned when you save. An order cannot be in the walk-in name.'])

    <script type="application/json" id="order-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
        'branchId' => $branchId,
        'gstPercent' => (float) $gstPercent,
        'makingMode' => $makingMode,
        'makingGst' => (float) $makingGstPercent,
        'taxExclusive' => $taxExclusive,
        'roundRupee' => $roundRupee,
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
    <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
    <script>
        const order = JSON.parse(document.getElementById('order-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const $ = (id) => document.getElementById(id);

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function selected(id) {
            const field = $(id);
            return field.options[field.selectedIndex];
        }

        function currentRate(metalId, purityId) {
            const rows = order.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
            return rows.find((rate) => String(rate.branch_id) === String(order.branchId))
                || rows.find((rate) => rate.branch_id === null)
                || null;
        }

        function syncPurities() {
            const metal = selected('metal');
            const purity = $('purity');
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

        function syncMaking() {
            const none = $('making-method').value === '';
            const value = $('making-value');
            value.disabled = none;
            value.placeholder = none ? '—' : '0';
            if (none) value.value = '';
        }

        function showRow(id, amount, show) {
            $(id + '-row').classList.toggle('d-none', !show);
            $(id).textContent = money.format(amount);
        }

        function renderOrder() {
            syncPurities();
            const metal = selected('metal');
            const purity = selected('purity');
            const rate = metal && purity ? currentRate(metal.dataset.id, purity.dataset.id) : null;
            const rateValue = rate ? Number(rate.rate_per_gram) : 0;
            const weight = Number($('weight').value || 0);
            const metalValue = round2(weight * rateValue);
            const missing = $('rate-missing');
            missing.classList.toggle('d-none', !!rate);
            missing.textContent = rate ? '' : 'No ' + (metal ? metal.text : '') + ' ' + (purity ? purity.text : '') + ' rate yet. Enter today’s rate in Metal rates first.';
            $('order-rate').value = rate ? money.format(rateValue) : '';
            $('order-metal-value').value = rate && weight > 0 ? money.format(metalValue) : '';

            const method = selected('making-method');
            const code = method && method.value ? (method.dataset.code || 'fixed') : '';
            const chargeValue = code ? Number($('making-value').value || 0) : 0;
            const making = round2(code === 'percentage' ? metalValue * chargeValue / 100 : (code === 'per_gram' ? weight * chargeValue : chargeValue));
            $('estimated-making').value = making.toFixed(2);

            const empty = $('order-empty');
            const sums = $('order-sums');
            if (weight <= 0 || !rate) {
                empty.textContent = rate ? 'Enter the expected weight' : 'Set today’s rate for this metal';
                empty.classList.remove('d-none');
                sums.classList.add('d-none');
                return;
            }
            empty.classList.add('d-none');
            sums.classList.remove('d-none');

            const mode = order.makingMode;
            const taxOf = (amount, percent) => percent > 0
                ? round2(order.taxExclusive ? amount * percent / 100 : amount * percent / (percent + 100))
                : 0;
            const subtotal = round2(metalValue + making);
            const taxBase = mode === 'inside' ? subtotal : metalValue;
            const tax = taxOf(taxBase, order.gstPercent);
            const makingTax = mode === 'separate' ? taxOf(making, order.makingGst) : 0;
            const exact = round2(subtotal + (order.taxExclusive ? tax + makingTax : 0));
            const total = order.roundRupee ? Math.round(exact) : exact;
            const roundOff = round2(total - exact);
            const advance = Number($('advance').value || 0);

            $('sum-weight').textContent = weight.toFixed(3) + ' g';
            $('sum-rate').textContent = money.format(rateValue) + ' / g';
            $('sum-metal-label').textContent = 'Metal value ' + metal.text + ' ' + purity.text;
            $('sum-metal').textContent = money.format(metalValue);
            showRow('sum-making', making, making > 0);
            $('sum-making-label').textContent = {
                inside: 'Making (in jewellery GST)',
                separate: 'Making (own GST)',
                processing: 'Processing charge (no GST)',
            }[mode] + (code === 'percentage' && chargeValue ? ' · ' + chargeValue + '% on metal' : '');
            $('sum-subtotal').textContent = money.format(subtotal);
            const taxOn = ['metal'].concat(mode === 'inside' && making > 0 ? ['making'] : []).join(' + ');
            $('sum-tax-label').textContent = 'GST ' + order.gstPercent + '% on ' + taxOn + (order.taxExclusive ? ' ' + money.format(taxBase) : ' (included)');
            $('sum-tax').textContent = money.format(tax);
            $('sum-making-tax-row').classList.toggle('d-none', mode !== 'separate' || making <= 0);
            $('sum-making-tax-label').textContent = 'GST ' + order.makingGst + '% on making' + (order.taxExclusive ? ' ' + money.format(making) : ' (included)');
            $('sum-making-tax').textContent = money.format(makingTax);
            showRow('sum-round', roundOff, roundOff !== 0);
            $('sum-total').textContent = money.format(total);
            $('sum-advance').textContent = '− ' + money.format(advance);
            $('sum-balance').textContent = money.format(round2(Math.max(total - advance, 0)));
        }

        const picker = customerPicker({
            customers: order.customers,
            createUrl: order.customerUrl,
            showUrl: order.customerShowUrl,
            csrf: order.csrf,
            addLabel: 'Add to order',
            chipLabel: 'Order for',
        });
        $('order-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#order-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            $('description').value = button.dataset.name;
            $('weight').focus();
        });
        $('making-method').addEventListener('change', syncMaking);
        $('order-form').addEventListener('input', renderOrder);
        $('order-form').addEventListener('change', renderOrder);
        $('order-form').addEventListener('submit', (event) => {
            renderOrder();
            if (!picker.ensure()) event.preventDefault();
        });
        syncMaking();
        renderOrder();
    </script>
@endpush
