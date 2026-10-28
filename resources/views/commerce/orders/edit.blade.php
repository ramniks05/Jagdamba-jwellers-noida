@extends('layouts.app')

@section('title', 'Edit '.$order->number)

@section('content')
    @php
        $makingTitle = $makingMode === 'processing' ? 'Processing charge' : 'Making';
        $modeLabel = [
            'inside' => 'Making inside jewellery GST',
            'separate' => 'Processing charge with GST '.(float) $makingGstPercent.'%',
            'processing' => 'Processing charge, no GST',
        ][$makingMode] ?? 'Making inside jewellery GST';
        $metalLabel = trim(($order->metalType?->name).' '.($order->purity?->name));
        $dueValue = old('due_on', $order->due_on?->toDateString());
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a href="{{ route('orders.show', $order) }}"><i class="bi bi-arrow-left"></i> Order {{ $order->number }}</a>
            <h1 class="page-title h3 mb-0 mt-1">Edit order</h1>
            <p class="text-secondary small mb-0">Customer and metal stay as booked. Change the piece details, locked rate, or delivery date. Advance is changed from the order page.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('orders.update', $order) }}" id="order-form" autocomplete="off">
        @csrf
        @method('PUT')
        <input type="hidden" name="estimated_making" id="estimated-making" value="{{ old('estimated_making', $order->estimated_making) }}">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white">Customer</div>
                    <div class="card-body">
                        <strong>{{ $order->customer?->name }}</strong>
                        @if ($order->customer?->mobile)
                            <div class="text-secondary small">{{ $order->customer->mobile }}</div>
                        @endif
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">What to make</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Piece</div>
                        <div class="bill-products" id="order-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}" @class(['active' => old('description', $order->description) === $category->name])>{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="description">Name</label>
                            <input class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description', $order->description) }}" maxlength="160" required>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Metal</div>
                            <div class="stone-box mt-0">
                                <p class="mb-2"><strong>{{ $metalLabel }}</strong></p>
                                <div class="metal-row">
                                    <div>
                                        <label for="weight">Expected wt g</label>
                                        <input class="form-control metal-net counter-num @error('expected_weight') is-invalid @enderror" id="weight" name="expected_weight" value="{{ old('expected_weight', $order->expected_weight) }}" inputmode="decimal" placeholder="0.000" required>
                                        @error('expected_weight')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="order-rate">Rate / g (locked)</label>
                                        <input class="form-control @error('rate_per_gram') is-invalid @enderror" id="order-rate" name="rate_per_gram" inputmode="decimal" value="{{ old('rate_per_gram', $order->rate_per_gram) }}" required>
                                        @error('rate_per_gram')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="order-metal-value">Value</label>
                                        <input class="form-control" id="order-metal-value" readonly tabindex="-1" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="charges-head">
                                <div class="weigh-section-title">Charges</div>
                                <span class="charges-mode" title="Set in Settings.">{{ $modeLabel }}</span>
                            </div>
                            <div class="weigh-metrics">
                                <div class="weight-field">
                                    <label for="making-method">{{ $makingTitle }}</label>
                                    <div class="input-group">
                                        <select class="form-select charge-method" id="making-method">
                                            <option value="">None</option>
                                            @foreach ($making as $method)
                                                <option value="{{ $method->uuid }}" data-code="{{ $method->code }}">{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control charge-value counter-num" id="making-value" inputmode="decimal" value="" placeholder="0" aria-label="{{ $makingTitle }} amount">
                                    </div>
                                </div>
                                <div class="weight-field">
                                    <label for="due-on">Delivery date</label>
                                    <input class="form-control @error('due_on') is-invalid @enderror" id="due-on" name="due_on" type="date" value="{{ $dueValue }}">
                                    @error('due_on')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label" for="design-notes">Design and size</label>
                                <textarea class="form-control @error('design_notes') is-invalid @enderror" id="design-notes" name="design_notes" rows="2">{{ old('design_notes', $order->design_notes) }}</textarea>
                                @error('design_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
                                <div class="bill-row d-none" id="sum-making-tax-row"><span id="sum-making-tax-label">GST {{ $makingGstPercent }}% on processing charge</span><span id="sum-making-tax">0.00</span></div>
                                <div class="bill-row d-none" id="sum-round-row"><span>Round off</span><span id="sum-round">0.00</span></div>
                            </div>
                            <div class="bill-grand"><span>Estimated bill</span><strong id="sum-total">0.00</strong></div>
                            <div class="bill-row"><span>Advance held</span><span id="sum-advance">− {{ $money($order->advanceHeld()) }}</span></div>
                            <div class="bill-grand"><span>Balance at delivery (about)</span><strong id="sum-balance">0.00</strong></div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2">Reprint the booking slip after you save if the customer needs an updated copy.</p>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Note for the shop</div>
                    <div class="card-body">
                        <input class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" value="{{ old('notes', $order->notes) }}" maxlength="1000">
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-lg"></i> Save changes</button>
            </div>
        </div>
    </form>

    <script type="application/json" id="order-config">{!! json_encode([
        'metalLabel' => $metalLabel,
        'advanceHeld' => (float) $order->advanceHeld(),
        'seedMaking' => (float) old('estimated_making', $order->estimated_making),
        'gstPercent' => (float) $gstPercent,
        'makingMode' => $makingMode,
        'makingGst' => (float) $makingGstPercent,
        'taxExclusive' => $taxExclusive,
        'roundRupee' => $roundRupee,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script>
        const order = JSON.parse(document.getElementById('order-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const $ = (id) => document.getElementById(id);
        let makingEdited = false;

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function selected(id) {
            const field = $(id);
            return field.options[field.selectedIndex];
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
            const rateValue = Number(String($('order-rate').value).replace(/,/g, '')) || 0;
            const weight = Number($('weight').value || 0);
            const metalValue = round2(weight * rateValue);
            $('order-metal-value').value = rateValue > 0 && weight > 0 ? money.format(metalValue) : '';

            const method = selected('making-method');
            const code = method && method.value ? (method.dataset.code || 'fixed') : '';
            const chargeValue = code ? Number($('making-value').value || 0) : 0;
            let making = round2(code === 'percentage' ? metalValue * chargeValue / 100 : (code === 'per_gram' ? weight * chargeValue : chargeValue));
            if (!makingEdited && $('making-method').value === '') {
                making = round2(order.seedMaking);
            }
            $('estimated-making').value = making.toFixed(2);

            const empty = $('order-empty');
            const sums = $('order-sums');
            if (weight <= 0 || rateValue <= 0) {
                empty.textContent = rateValue > 0 ? 'Enter the expected weight' : 'Enter the rate per gram';
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
            const advance = order.advanceHeld;

            $('sum-weight').textContent = weight.toFixed(3) + ' g';
            $('sum-rate').textContent = money.format(rateValue) + ' / g';
            $('sum-metal-label').textContent = 'Metal value ' + order.metalLabel;
            $('sum-metal').textContent = money.format(metalValue);
            showRow('sum-making', making, making > 0);
            $('sum-making-label').textContent = {
                inside: 'Making (in jewellery GST)',
                separate: 'Processing charge (GST ' + order.makingGst + '%)',
                processing: 'Processing charge (no GST)',
            }[mode];
            $('sum-subtotal').textContent = money.format(subtotal);
            const taxOn = ['metal'].concat(mode === 'inside' && making > 0 ? ['making'] : []).join(' + ');
            $('sum-tax-label').textContent = 'GST ' + order.gstPercent + '% on ' + taxOn + (order.taxExclusive ? ' ' + money.format(taxBase) : ' (included)');
            $('sum-tax').textContent = money.format(tax);
            $('sum-making-tax-row').classList.toggle('d-none', mode !== 'separate' || making <= 0);
            $('sum-making-tax-label').textContent = 'GST ' + order.makingGst + '% on processing charge' + (order.taxExclusive ? ' ' + money.format(making) : ' (included)');
            $('sum-making-tax').textContent = money.format(makingTax);
            showRow('sum-round', roundOff, roundOff !== 0);
            $('sum-total').textContent = money.format(total);
            $('sum-balance').textContent = money.format(round2(Math.max(total - advance, 0)));
        }

        $('order-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#order-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            $('description').value = button.dataset.name;
            $('weight').focus();
        });
        $('making-method').addEventListener('change', () => {
            makingEdited = true;
            syncMaking();
            renderOrder();
        });
        $('making-value').addEventListener('input', () => {
            makingEdited = true;
            renderOrder();
        });
        $('order-form').addEventListener('input', renderOrder);
        $('order-form').addEventListener('change', renderOrder);
        syncMaking();
        renderOrder();
    </script>
@endpush
