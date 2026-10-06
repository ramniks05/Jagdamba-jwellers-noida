@extends('layouts.app')

@section('title', 'Receive purchase')

@section('content')
    <h1 class="page-title h3 mb-2">Receive purchase</h1>
    <p class="text-secondary">The piece value can follow today's metal rate, or you can type the amount from the supplier bill. The GST and total are shown before you save.</p>
    <form method="POST" action="{{ route('purchases.store') }}" id="purchase-form">
        @csrf
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white">Piece</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="supplier">Supplier</label>
                            <select class="form-select" id="supplier" name="supplier_uuid" required>
                                <option value="">Choose</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->uuid }}" @selected(old('supplier_uuid') === $supplier->uuid)>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="piece-name">Piece name</label>
                            <input class="form-control" id="piece-name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="item-code">Item code</label>
                            <input class="form-control" id="item-code" name="item_code" value="{{ old('item_code') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="metal">Metal</label>
                            <select class="form-select" id="metal" name="metal_uuid" required>
                                @foreach ($metals as $metal)
                                    <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="purity">Purity</label>
                            <select class="form-select" id="purity" name="purity_uuid" required>
                                @foreach ($metals as $metal)
                                    @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                        <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="location">Location</label>
                            <select class="form-select" id="location" name="location_uuid" required>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->uuid }}" data-branch="{{ $location->branch_id }}" @selected(old('location_uuid') === $location->uuid)>{{ $location->branch?->name }} / {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gross">Gross weight (g)</label>
                            <input class="form-control bill-weight" id="gross" name="gross_weight" value="{{ old('gross_weight') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="stone-g">Stone g</label>
                            <input class="form-control" id="stone-g" name="stone_weight" value="{{ old('stone_weight', '0') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="other-g">Other g</label>
                            <input class="form-control" id="other-g" name="other_weight" value="{{ old('other_weight', '0') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="stone-value">Stone value</label>
                            <input class="form-control" id="stone-value" name="stone_value" value="{{ old('stone_value', '0') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="making">Making</label>
                            <select class="form-select" id="making" name="making_method_uuid">
                                <option value="">None</option>
                                @foreach ($making as $method)
                                    <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected(old('making_method_uuid', $making->firstWhere('code', 'per_gram')?->uuid) === $method->uuid)>{{ $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="making-value">Making value</label>
                            <input class="form-control" id="making-value" name="making_value" value="{{ old('making_value', '0') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="wastage">Wastage</label>
                            <select class="form-select" id="wastage" name="wastage_method_uuid">
                                <option value="">None</option>
                                @foreach ($wastage as $method)
                                    <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected(old('wastage_method_uuid', $wastage->firstWhere('code', 'percentage')?->uuid) === $method->uuid)>{{ $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="wastage-value">Wastage value</label>
                            <input class="form-control" id="wastage-value" name="wastage_value" value="{{ old('wastage_value', '0') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="discount">Discount</label>
                            <input class="form-control" id="discount" name="discount" value="{{ old('discount', '0') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notes</label>
                            <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Purchase value</div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="pricing" id="pricing-rate" value="rate" @checked(old('pricing', 'rate') !== 'amount')>
                            <label class="form-check-label" for="pricing-rate">Calculate from today's rate</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="pricing" id="pricing-amount" value="amount" @checked(old('pricing') === 'amount')>
                            <label class="form-check-label" for="pricing-amount">Enter one purchase value</label>
                        </div>
                        <div class="mb-3 {{ old('pricing') === 'amount' ? '' : 'd-none' }}" id="amount-wrap">
                            <label class="form-label" for="purchase-amount">Purchase value</label>
                            <input class="form-control" id="purchase-amount" name="purchase_amount" value="{{ old('purchase_amount') }}" placeholder="Amount on the supplier bill">
                            <div class="form-text">This is the piece value. GST is added below.</div>
                        </div>
                        <ul class="list-group list-group-flush mb-3" id="price-lines"></ul>
                        <p class="fw-semibold mb-0" id="price-note"></p>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Payment now</span>
                        <button class="btn btn-outline-secondary btn-sm" id="use-total" type="button">Put the total here</button>
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <select class="form-select" name="payments[0][method]">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4"><input class="form-control" id="pay-amount" name="payments[0][amount]" placeholder="Amount" value="{{ old('payments.0.amount') }}"></div>
                        <div class="col-md-4"><input class="form-control" name="payments[0][reference]" placeholder="Reference" value="{{ old('payments.0.reference') }}"></div>
                        <div class="col-12 text-secondary small">Leave the amount blank if the supplier is not paid today. The balance stays on the supplier.</div>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Save purchase</button>
            </div>
        </div>
    </form>
    <script type="application/json" id="purchase-config">{!! json_encode([
        'gst' => (float) $gstPercent,
        'exclusive' => $taxExclusive,
        'round' => $roundRupee,
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
        const purchase = JSON.parse(document.getElementById('purchase-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function selected(id) {
            const field = document.getElementById(id);
            return field.options[field.selectedIndex];
        }

        function currentRate(metalId, purityId, branchId) {
            const rows = purchase.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
            return rows.find((rate) => String(rate.branch_id) === String(branchId))
                || rows.find((rate) => rate.branch_id === null)
                || null;
        }

        function syncPurities() {
            const metal = selected('metal');
            const purity = document.getElementById('purity');
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

        function lineAmount(net, rateValue) {
            const making = selected('making');
            const wastage = selected('wastage');
            const makingCode = making && making.value ? (making.dataset.code || 'fixed') : 'fixed';
            const wastageCode = wastage && wastage.value ? (wastage.dataset.code || 'fixed') : 'fixed';
            const makingValue = making && making.value ? Number(document.getElementById('making-value').value || 0) : 0;
            const wastageValue = wastage && wastage.value ? Number(document.getElementById('wastage-value').value || 0) : 0;
            const stone = Number(document.getElementById('stone-value').value || 0);
            const metalAmount = net * rateValue;
            const wastageAmount = wastageCode === 'percentage' ? net * wastageValue / 100 * rateValue : (wastageCode === 'per_gram' ? net * wastageValue : wastageValue);
            const makingAmount = makingCode === 'per_gram' ? net * makingValue : (makingCode === 'percentage' ? metalAmount * makingValue / 100 : makingValue);
            return {
                metal: round2(metalAmount),
                wastage: round2(wastageAmount),
                making: round2(makingAmount),
                stone: round2(stone),
                line: round2(metalAmount + wastageAmount + makingAmount + stone),
            };
        }

        function renderPrice() {
            syncPurities();
            const useAmount = document.getElementById('pricing-amount').checked;
            document.getElementById('amount-wrap').classList.toggle('d-none', !useAmount);
            const note = document.getElementById('price-note');
            const list = document.getElementById('price-lines');
            const gross = Number(document.getElementById('gross').value || 0);
            const net = gross - Number(document.getElementById('stone-g').value || 0) - Number(document.getElementById('other-g').value || 0);
            const rows = [];
            if (net <= 0) {
                list.innerHTML = '';
                note.textContent = 'Enter a gross weight greater than the stone and other weight.';
                document.getElementById('purchase-form').dataset.total = '';
                return;
            }
            const metal = selected('metal');
            const purity = selected('purity');
            const location = selected('location');
            const rate = currentRate(metal.dataset.id, purity.dataset.id, location.dataset.branch);
            let piece = 0;
            if (useAmount) {
                piece = Number(document.getElementById('purchase-amount').value || 0);
                if (piece <= 0) {
                    list.innerHTML = '';
                    note.textContent = 'Type the purchase value from the supplier bill.';
                    document.getElementById('purchase-form').dataset.total = '';
                    return;
                }
                rows.push(['Purchase value', piece]);
                rows.push(['Worked rate / g', piece / net]);
            } else if (!rate) {
                list.innerHTML = '';
                note.textContent = 'No ' + metal.text + ' ' + purity.text + ' rate is saved. Enter one purchase value, or add the rate first.';
                document.getElementById('purchase-form').dataset.total = '';
                return;
            } else {
                const parts = lineAmount(net, Number(rate.rate_per_gram));
                piece = parts.line;
                rows.push(['Net weight', net.toFixed(3) + ' g']);
                rows.push(['Today\'s rate / g', Number(rate.rate_per_gram)]);
                rows.push(['Metal', parts.metal]);
                rows.push(['Wastage', parts.wastage]);
                rows.push(['Making', parts.making]);
                rows.push(['Stone', parts.stone]);
                rows.push(['Piece value', parts.line]);
            }
            const discount = Math.min(piece, Math.max(0, Number(document.getElementById('discount').value || 0)));
            const after = round2(piece - discount);
            let tax = 0;
            let exact = after;
            if (purchase.gst > 0 && purchase.exclusive) {
                tax = round2(after * purchase.gst / 100);
                exact = round2(after + tax);
            } else if (purchase.gst > 0) {
                tax = round2(after * purchase.gst / (purchase.gst + 100));
            }
            let total = exact;
            let roundOff = 0;
            if (purchase.round) {
                total = Math.round(exact);
                roundOff = round2(total - exact);
            }
            if (discount > 0) rows.push(['Discount', discount]);
            rows.push(['GST ' + purchase.gst + '%', tax]);
            if (roundOff !== 0) rows.push(['Round off', roundOff]);
            rows.push(['Total', total]);
            list.innerHTML = rows.map((row) => {
                const value = typeof row[1] === 'number' ? money.format(row[1]) : row[1];
                const strong = row[0] === 'Total' ? ' fw-semibold' : '';
                return '<li class="list-group-item d-flex justify-content-between px-0' + strong + '"><span>' + row[0] + '</span><span>' + value + '</span></li>';
            }).join('');
            note.textContent = useAmount
                ? 'GST is added to the purchase value you typed.'
                : 'Calculated from today\'s ' + metal.text + ' ' + purity.text + ' rate.';
            document.getElementById('purchase-form').dataset.total = total.toFixed(2);
        }

        document.getElementById('purchase-form').addEventListener('input', renderPrice);
        document.getElementById('purchase-form').addEventListener('change', renderPrice);
        document.getElementById('use-total').addEventListener('click', () => {
            const total = document.getElementById('purchase-form').dataset.total;
            if (total) document.getElementById('pay-amount').value = total;
        });
        renderPrice();
    </script>
@endpush
