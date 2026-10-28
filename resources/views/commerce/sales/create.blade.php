@extends('layouts.app')

@section('title', $sale ? 'Edit '.$sale->number : 'New bill')

@inject('billScan', 'App\Services\Commerce\BillScanService')
@inject('shopContext', 'App\Support\CompanyContext')

@section('content')
    <h1 class="page-title h3 mb-3">{{ $sale ? 'Edit '.$sale->number : 'New bill' }}</h1>
    @if ($sale)
        <p class="text-secondary">Invoice {{ $sale->number }} stays the same. Change the weight, the charges, or the payment, then save it again.</p>
    @endif
    @if ($order)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Delivering order {{ $order->number }}</strong> for {{ $order->customer?->name }} · {{ $order->description }}<br>
                <span class="small">{{ $order->metalType?->name }} {{ $order->purity?->name }} is billed at the locked rate {{ $money((string) $order->rate_per_gram) }} / g. The advance of {{ $money($order->advanceHeld()) }} is taken off this bill.</span>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('sales.create') }}">Bill without the order</a>
        </div>
    @endif
    <form method="POST" action="{{ $sale ? route('sales.update', $sale) : route('sales.store') }}" id="bill-form" autocomplete="off">
        @csrf
        @if ($sale)
            @method('PUT')
        @endif
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid', $sale?->customer?->uuid ?? $order?->customer?->uuid ?? request()->query('customer')) }}">
        @if ($order)
            <input type="hidden" name="order_uuid" value="{{ $order->uuid }}">
        @endif
        <div id="bill-piece-inputs"></div>
        <div class="row g-3">
            <div class="col-lg-7">
                @include('commerce.partials.customer-picker', ['addLabel' => 'Add to bill', 'errorText' => 'Choose the customer by name or mobile number, or add them as a new customer.'])
                @if ($trays->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header bg-white">Tray (optional)</div>
                        <div class="card-body">
                            <label class="form-label" for="tray-uuid">Bill from tray</label>
                            <select class="form-select @error('tray_uuid') is-invalid @enderror" id="tray-uuid" name="tray_uuid">
                                <option value="">— Not from a tray —</option>
                                @foreach ($trays as $trayOption)
                                    <option value="{{ $trayOption->uuid }}" @selected(old('tray_uuid', $sale?->stockTray?->uuid) === $trayOption->uuid)>
                                        {{ $trayOption->name }} · {{ rtrim(rtrim(number_format((float) $trayOption->grams, 3, '.', ''), '0'), '.') }} g · {{ $trayOption->pieces }} pcs
                                    </option>
                                @endforeach
                            </select>
                            @error('tray_uuid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">Net weight on the bill comes off this tray (1 piece per bill). Metal stock still uses each line’s metal and purity.</div>
                        </div>
                    </div>
                @endif
                @if (! $sale && ! $order)
                    <div class="card mb-3 d-none" id="customer-open-orders">
                        <div class="card-header bg-white py-2"><span><i class="bi bi-bag-check"></i> Open orders for this customer</span></div>
                        <div class="card-body pt-0 pb-0">
                            <div class="bill-results border-0 rounded-0" id="customer-open-orders-list"></div>
                        </div>
                    </div>
                @endif
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
                @if ($retailEnabled ?? false)
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                        <span><i class="bi bi-basket"></i> Other items (no weight)</span>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#retail-panel" aria-expanded="true">Show / hide</button>
                    </div>
                    <div class="collapse show" id="retail-panel">
                        <div class="card-body">
                            <p class="small text-secondary mb-3">Murti, gifts, and fixed-price sales. These do not change metal stock.</p>
                            @if ($retailProducts->isNotEmpty())
                                <div class="mb-2">
                                    <label class="form-label" for="retail-product">From list (optional)</label>
                                    <select class="form-select" id="retail-product">
                                        <option value="">— Type a one-off name —</option>
                                        @foreach ($retailProducts as $product)
                                            <option value="{{ $product->uuid }}"
                                                data-name="{{ $product->name }}"
                                                data-price="{{ $product->default_unit_price }}"
                                                data-tax-mode="{{ $product->default_tax_mode->value }}"
                                                data-tax-percent="{{ $product->default_tax_percent }}"
                                                data-hsn="{{ $product->hsn_code }}">
                                                {{ $product->name }} @if ($product->default_unit_price) · ₹ {{ number_format((float) $product->default_unit_price, 2) }} @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label" for="retail-name">Name</label>
                                    <input class="form-control" id="retail-name" maxlength="160" placeholder="Ganesh murti">
                                </div>
                                <div class="col-md-2 col-4">
                                    <label class="form-label" for="retail-qty">Qty</label>
                                    <input class="form-control" id="retail-qty" type="number" min="1" value="1">
                                </div>
                                <div class="col-md-4 col-8">
                                    <label class="form-label" for="retail-price">Price (₹)</label>
                                    <input class="form-control counter-num" id="retail-price" inputmode="decimal" placeholder="0.00">
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label" for="retail-tax-mode">GST</label>
                                    <select class="form-select" id="retail-tax-mode">
                                        <option value="none">No GST</option>
                                        <option value="inclusive">With GST (in price)</option>
                                        <option value="exclusive">With GST (extra)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 retail-tax-field">
                                    <label class="form-label" for="retail-tax-percent">GST %</label>
                                    <input class="form-control" id="retail-tax-percent" inputmode="decimal" placeholder="Shop {{ rtrim(rtrim(number_format((float) $gstPercent, 2), '0'), '.') }}%">
                                </div>
                                <div class="col-md-4 retail-tax-field">
                                    <label class="form-label" for="retail-tax-amount">Or GST ₹ (manual)</label>
                                    <input class="form-control" id="retail-tax-amount" inputmode="decimal" placeholder="Optional">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                <button class="btn btn-outline-primary" type="button" id="retail-add"><i class="bi bi-plus-lg"></i> Add other item</button>
                                <span class="small text-secondary" id="retail-preview"></span>
                            </div>
                            <div class="small text-danger d-none mt-2" id="retail-error"></div>
                        </div>
                    </div>
                </div>
                @endif
                <div class="card mb-3">
                    <div class="card-header bg-white">Weigh and bill</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Category</div>
                        <div class="bill-products" id="weigh-products">
                            @foreach ($categories as $category)
                                <button type="button" data-uuid="{{ $category->uuid }}" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="weigh-name">Piece name</label>
                            <input class="form-control" id="weigh-name" placeholder="Choose a category, then type the name if it is different" value="{{ $order?->description }}">
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="weigh-huid">HUID</label>
                            <input class="form-control text-uppercase" id="weigh-huid" maxlength="32" placeholder="Type the hallmark ID if this piece is not in stock" autocomplete="off">
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Weight</div>
                            <div class="stone-box mt-0">
                                <div class="metal-row sale-weights">
                                    <div>
                                        <label for="weigh-metal">Metal</label>
                                        <select class="form-select" id="weigh-metal">
                                            @foreach ($metals as $metal)
                                                <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" data-hsn="{{ $metal->hsn_code }}" @selected($order && (int) $order->metal_type_id === (int) $metal->id)>{{ $metal->name }}</option>
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
                                        <label for="weigh-gross">Gross g</label>
                                        <input class="form-control metal-net bill-weight counter-num" id="weigh-gross" inputmode="decimal" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="weigh-stone">Stone g</label>
                                        <input class="form-control counter-num" id="weigh-stone" inputmode="decimal" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="weigh-other-g">Other g</label>
                                        <input class="form-control counter-num" id="weigh-other-g" inputmode="decimal" placeholder="0.000">
                                    </div>
                                    <div>
                                        <label for="weigh-net">Net g</label>
                                        <input class="form-control" id="weigh-net" readonly tabindex="-1" placeholder="0.000">
                                    </div>
                                </div>
                                <div class="metal-row mt-2">
                                    <div>
                                        <label for="weigh-metal-rate">Rate / g</label>
                                        <input class="form-control counter-num" id="weigh-metal-rate" inputmode="decimal" placeholder="Today's rate">
                                    </div>
                                    <div>
                                        <label for="weigh-metal-value">Value</label>
                                        <input class="form-control" id="weigh-metal-value" readonly tabindex="-1" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">Type the scale weight. Leave stone and other blank for plain gold. Net = gross less stone and other, and the rate is on the net weight.</div>
                        </div>
                        <div class="weigh-section">
                            <div class="charges-head">
                                <div class="weigh-section-title">Charges</div>
                                <select class="form-select form-select-sm" id="making-mode" name="making_mode" aria-label="GST on processing charge">
                                    <option value="separate" @selected($makingMode === 'separate')>Processing charge with GST {{ (float) $makingGstPercent }}%</option>
                                    <option value="processing" @selected($makingMode === 'processing')>Processing charge, no GST</option>
                                    <option value="inside" @selected($makingMode === 'inside')>Making inside jewellery GST</option>
                                </select>
                            </div>
                            <div class="weigh-metrics">
                                <div class="weight-field">
                                    <label for="weigh-making" id="weigh-making-label">{{ $makingMode === 'inside' ? 'Making' : 'Processing charge' }}</label>
                                    <div class="input-group">
                                        <select class="form-select charge-method" id="weigh-making">
                                            <option value="">None</option>
                                            @foreach ($making as $method)
                                                <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'per_gram')>{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control charge-value counter-num" id="weigh-making-value" inputmode="decimal" value="" placeholder="0" aria-label="Making amount">
                                    </div>
                                </div>
                                <div class="weight-field">
                                    <label for="weigh-wastage">Hallmark</label>
                                    <div class="input-group">
                                        <select class="form-select charge-method" id="weigh-wastage">
                                            <option value="">None</option>
                                            @foreach ($wastage as $method)
                                                <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" @selected($method->code === 'fixed')>{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control charge-value counter-num" id="weigh-wastage-value" inputmode="decimal" value="" placeholder="0" aria-label="Hallmark amount">
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="weigh-own">
                                <label class="form-check-label" for="weigh-own">Own</label>
                            </div>
                            <div class="form-text">Tick Own when this gold is only for the bill. It is not added to stock, and the main stock is not reduced. Those pieces are listed under Own gold. Untick it only when the weight should come off the main stock.</div>
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
                            <div class="bill-sold-at-wrap">
                                <label class="form-label mb-0" for="sold-at">Bill date &amp; time</label>
                                <input class="form-control form-control-sm @error('sold_at') is-invalid @enderror" id="sold-at" name="sold_at" type="datetime-local" value="{{ old('sold_at', ($sale?->sold_at ?? now())->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" step="60">
                                @error('sold_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <label class="form-label mb-0" for="customer-pays">Customer will pay ₹</label>
                            <input class="form-control form-control-sm" id="customer-pays" inputmode="decimal" placeholder="Final amount" autocomplete="off">
                            <label class="form-label mb-0" for="discount">Discount ₹</label>
                            @php
                                $discountDefault = old('discount');
                                if ($discountDefault === null) {
                                    $discountDefault = $sale && (float) $sale->discount_amount > 0
                                        ? (string) $sale->discount_amount
                                        : '';
                                }
                            @endphp
                            <input class="form-control form-control-sm counter-num" id="discount" name="discount" inputmode="decimal" value="{{ $discountDefault }}" placeholder="0">
                        </div>
                        <p class="text-secondary small mb-2">Change bill date only when you need a backdated invoice; default is now.</p>
                        <p class="text-secondary small d-none mb-2" id="customer-pays-note"></p>
                        <div class="bill-block">
                            <div class="bill-row bill-row-muted"><span>Net weight</span><span id="bill-net-weight">0.000 g</span></div>
                            <div class="bill-row"><span>Metal value</span><span id="bill-metal">0.00</span></div>
                            <div class="bill-row d-none" id="bill-stone-row"><span>Stones</span><span id="bill-stone">0.00</span></div>
                            <div class="bill-row d-none" id="bill-wastage-row"><span>Hallmark</span><span id="bill-wastage">0.00</span></div>
                            <div class="bill-row d-none" id="bill-making-row"><span id="bill-making-label">{{ $makingMode === 'inside' ? 'Making' : 'Processing charge' }}</span><span id="bill-making">0.00</span></div>
                            <div class="bill-row bill-row-sub"><span>Jewellery total</span><span id="bill-subtotal">0.00</span></div>
                            <div class="bill-row d-none" id="bill-retail-row"><span>Other items</span><span id="bill-retail">0.00</span></div>
                        </div>
                        <div class="bill-block">
                            <div class="bill-row d-none" id="bill-discount-row"><span>Discount</span><span id="bill-discount">0.00</span></div>
                            <div class="bill-row d-none" id="bill-after-discount-row"><span>After discount</span><span id="bill-after-discount">0.00</span></div>
                            <div class="bill-row"><span id="bill-tax-label">GST {{ $gstPercent }}%</span><span id="bill-tax">0.00</span></div>
                            <div class="bill-row d-none" id="bill-making-tax-row"><span id="bill-making-tax-label">GST {{ $makingGstPercent }}% on processing charge</span><span id="bill-making-tax">0.00</span></div>
                            <div class="bill-row d-none" id="bill-hallmark-row"><span>Hallmark (no GST)</span><span id="bill-hallmark">0.00</span></div>
                            <div class="bill-row d-none" id="bill-round-row"><span>Round off</span><span id="bill-round">0.00</span></div>
                        </div>
                        <div class="bill-grand"><span>Total</span><strong id="bill-total">0.00</strong></div>
                        @if ($order)
                            <div class="bill-row"><span>Advance {{ $order->number }}</span><span id="bill-advance">0.00</span></div>
                        @endif
                        <div class="bill-credit d-none" id="bill-old-due-row">
                            <label class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="include-previous" name="include_previous" value="1" @checked(old('include_previous', '1') == '1')>
                                <span class="form-check-label">Add old due <small id="bill-old-due-label"></small></span>
                            </label>
                            <span id="bill-old-due">0.00</span>
                        </div>
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
                        <input class="form-control" id="notes" name="notes" value="{{ old('notes', $sale?->notes) }}">
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span>Payment</span>
                        <button class="btn btn-outline-secondary btn-sm" id="use-total" type="button">Cash</button>
                    </div>
                    <div class="card-body">
                        @php($savedPayments = ($draft ?? [])['payments'] ?? [])
                        @foreach (range(0, max(2, count($savedPayments) - 1)) as $slot)
                            @php($savedPayment = is_array(old('payments.'.$slot)) ? old('payments.'.$slot) : ($savedPayments[$slot] ?? []))
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <select class="form-select" name="payments[{{ $slot }}][method]">
                                        @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank', 'cheque' => 'Cheque'] as $payMethod => $payLabel)
                                            <option value="{{ $payMethod }}" @selected(($savedPayment['method'] ?? ($slot === 0 ? 'cash' : '')) === $payMethod)>{{ $payLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4"><input class="form-control bill-pay counter-num" name="payments[{{ $slot }}][amount]" value="{{ $savedPayment['amount'] ?? '' }}" placeholder="Amount"></div>
                                <div class="col-4"><input class="form-control" name="payments[{{ $slot }}][reference]" value="{{ $savedPayment['reference'] ?? '' }}" placeholder="Reference"></div>
                            </div>
                        @endforeach
                        <p class="small d-none mb-0" id="pay-extra-note"></p>
                    </div>
                </div>
                <button class="btn btn-outline-primary w-100 mb-2" type="button" id="bill-preview"><i class="bi bi-eye"></i> Preview bill</button>
                <button class="btn btn-primary w-100" type="submit">{{ $sale ? 'Update '.$sale->number : 'Save invoice' }}</button>
            </div>
        </div>
    </form>

    @include('commerce.partials.customer-modals', ['document' => 'bill'])

    <div class="modal fade" id="bill-preview-modal" tabindex="-1" aria-labelledby="bill-preview-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="bill-preview-title">Bill preview</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bill-preview-body" id="bill-preview-body"></div>
                <div class="modal-footer">
                    <span class="small text-secondary me-auto">Nothing is saved until you press {{ $sale ? 'Update' : 'Save invoice' }}.</span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to bill</button>
                    <button type="button" class="btn btn-primary" id="bill-preview-save">{{ $sale ? 'Update '.$sale->number : 'Save invoice' }}</button>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="bill-config">{!! json_encode([
        'csrf' => csrf_token(),
        'previewUrl' => $sale ? route('sales.preview.edit', $sale) : route('sales.preview'),
        'customerUrl' => route('customers.store'),
        'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
        'gst' => (float) $gstPercent,
        'makingGst' => (float) $makingGstPercent,
        'hallmarkOutside' => ! $sale || $sale->hallmark_amount !== null,
        'exclusive' => $taxExclusive,
        'round' => $roundRupee,
        'order' => $order ? [
            'metal_type_id' => $order->metal_type_id,
            'purity_id' => $order->purity_id,
            'rate' => (string) $order->rate_per_gram,
            'advance' => (float) $order->advanceHeld(),
        ] : null,
        'customers' => $customers->map(function ($customer) use ($credits, $openDues, $ledgerDues, $money) {
            $openBills = (float) ($openDues[$customer->id]['amount'] ?? 0);
            $owed = $ledgerDues === null ? $openBills : (float) ($ledgerDues[$customer->id] ?? 0);

            return [
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'code' => $customer->code,
                'mobile' => $customer->mobile,
                'walkin' => (bool) $customer->is_system,
                'is_premium' => (bool) $customer->is_premium,
                'credit' => (float) ($credits[$customer->id] ?? 0),
                'due' => $owed > 0 ? 'Old due '.$money(number_format($owed, 2, '.', '')) : '',
                'advance' => $owed <= 0 && isset($credits[$customer->id]) ? 'Advance '.$money((string) $credits[$customer->id]) : '',
                'due_amount' => min($openBills, $owed),
                'due_bills' => (int) ($openDues[$customer->id]['bills'] ?? 0),
            ];
        })->values(),
        'scanUrl' => route('sales.scan'),
        'orderUuid' => $order?->uuid,
        'openOrders' => $openOrdersByCustomer ?? [],
        'pieces' => $rows->map(fn ($row) => $billScan->present($row['item'], $row['quote'], $shopContext->company()))->values(),
        'rates' => $rates->map(fn ($rate) => [
            'metal_type_id' => $rate->metal_type_id,
            'purity_id' => $rate->purity_id,
            'branch_id' => $rate->branch_id,
            'rate_per_gram' => (string) $rate->rate_per_gram,
        ])->values(),
        'lockedRates' => ($draft ?? [])['lockedRates'] ?? [],
        'retailProducts' => $retailProducts->map(fn ($product) => [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'default_unit_price' => $product->default_unit_price !== null ? (string) $product->default_unit_price : '',
            'default_tax_mode' => $product->default_tax_mode->value,
            'default_tax_percent' => $product->default_tax_percent !== null ? (string) $product->default_tax_percent : '',
            'hsn_code' => (string) ($product->hsn_code ?? ''),
        ])->values(),
        'editing' => $draft ? ['stock' => $draft['stock'], 'fresh' => $draft['fresh'], 'retail' => $draft['retail'] ?? []] : null,
        'fixedPrevious' => ($draft ?? [])['fixedPrevious'] ?? null,
        'retailEnabled' => $retailEnabled ?? false,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
    <script>
        const bill = JSON.parse(document.getElementById('bill-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const stockLines = [];
        const freshLines = [];
        const retailLines = [];
        let editingIndex = null;
        let weighDefaults = null;
        let rateEdited = false;
        let customer = bill.customers.find((row) => row.uuid === document.getElementById('customer-uuid').value) || null;
        let payState = { total: 0, advance: 0, oldDue: 0, creditUsed: 0 };
        const picker = customerPicker({
            customers: bill.customers,
            createUrl: bill.customerUrl,
            showUrl: bill.customerShowUrl,
            csrf: bill.csrf,
            addLabel: 'Add to bill',
            chipLabel: 'Bill to',
            onChange: (row) => {
                customer = row;
                if (!bill.fixedPrevious) {
                    const oldDueBox = document.getElementById('include-previous');
                    if (oldDueBox) oldDueBox.checked = !!(row && Number(row.due_amount) > 0);
                }
                renderCustomerOpenOrders(row);
                renderTotals();
            },
        });
        renderCustomerOpenOrders(customer);

        function renderCustomerOpenOrders(row) {
            const panel = document.getElementById('customer-open-orders');
            const list = document.getElementById('customer-open-orders-list');
            if (!panel || !list) return;
            if (!row || row.walkin) {
                panel.classList.add('d-none');
                list.replaceChildren();
                return;
            }
            const orders = (bill.openOrders || {})[row.uuid] || [];
            if (orders.length === 0) {
                panel.classList.add('d-none');
                list.replaceChildren();
                return;
            }
            list.replaceChildren();
            orders.forEach((order) => {
                const link = document.createElement('a');
                link.href = order.billUrl;
                const info = document.createElement('span');
                info.className = 'result-info';
                const title = document.createElement('strong');
                title.textContent = order.number;
                const detail = document.createElement('small');
                detail.textContent = [order.description, order.status, 'Advance ' + order.advance].filter(Boolean).join(' · ');
                info.append(title, detail);
                const action = document.createElement('span');
                action.className = 'result-add';
                action.innerHTML = '<i class="bi bi-receipt"></i> Make the bill';
                link.append(info, action);
                list.appendChild(link);
            });
            panel.classList.remove('d-none');
        }

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function round3(value) {
            return Math.round((value + Number.EPSILON) * 1000) / 1000;
        }

        function option(id) {
            const field = document.getElementById(id);
            return field.options[field.selectedIndex];
        }

        function currentRate(metalId, purityId, branchId) {
            if (bill.order && String(bill.order.metal_type_id) === String(metalId) && String(bill.order.purity_id) === String(purityId)) {
                return { rate_per_gram: bill.order.rate };
            }
            const locked = (bill.lockedRates || []).find((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
            if (locked) return locked;
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
                if (!show && row.selected) {
                    row.selected = false;
                }
            });
            const visible = Array.from(purity.options).filter((row) => !row.disabled);
            const selected = visible.find((row) => row.selected);
            if (selected) {
                return;
            }
            const location = document.getElementById('weigh-location');
            const branch = location && location.selectedIndex >= 0 ? location.options[location.selectedIndex].dataset.branch : '';
            const priced = visible.find((row) => currentRate(metal.dataset.id, row.dataset.id, branch));
            const next = priced || visible[0];
            if (next) {
                next.selected = true;
            }
        }

        function emptyQuote(message) {
            const preview = document.getElementById('weigh-price');
            preview.className = 'weigh-quote is-empty';
            preview.textContent = message;
            document.getElementById('weigh-split').replaceChildren();
            return null;
        }

        function collectStones() {
            return [];
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

        function readRate() {
            const typed = Number(String(document.getElementById('weigh-metal-rate').value).replace(/,/g, ''));
            return Number.isFinite(typed) && typed > 0 ? typed : 0;
        }

        function previewWeigh() {
            syncPurities();
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            const location = option('weigh-location');
            const gross = Number(document.getElementById('weigh-gross').value || 0);
            const stoneWeight = Number(document.getElementById('weigh-stone').value || 0);
            const other = Number(document.getElementById('weigh-other-g').value || 0);
            const net = round3(gross - stoneWeight - other);
            const netInput = document.getElementById('weigh-net');
            netInput.value = gross > 0 ? net.toFixed(3) : '';
            netInput.classList.toggle('is-invalid', gross > 0 && net <= 0);
            const shownRate = metal && purity && location ? currentRate(metal.dataset.id, purity.dataset.id, location.dataset.branch) : null;
            const rateField = document.getElementById('weigh-metal-rate');
            if (!rateEdited) {
                rateField.value = shownRate ? Number(shownRate.rate_per_gram).toFixed(2) : '';
            }
            const rateValue = readRate();
            document.getElementById('weigh-metal-value').value = rateValue > 0 && net > 0 ? money.format(round2(net * rateValue)) : '';
            if (gross <= 0) {
                return emptyQuote('Enter the gross weight');
            }
            if (stoneWeight < 0 || other < 0 || net <= 0) {
                return emptyQuote('Stone and other weight are more than the gross');
            }
            if (!metal || !purity || !location) {
                return emptyQuote('Choose metal and purity');
            }
            if (rateValue <= 0) {
                return emptyQuote('Enter the rate per gram');
            }
            const making = option('weigh-making');
            const wastage = option('weigh-wastage');
            const makingCode = making && making.value ? (making.dataset.code || 'fixed') : 'fixed';
            const wastageCode = wastage && wastage.value ? (wastage.dataset.code || 'fixed') : 'fixed';
            const makingValue = making && making.value ? Number(document.getElementById('weigh-making-value').value || 0) : 0;
            const wastageValue = wastage && wastage.value ? Number(document.getElementById('weigh-wastage-value').value || 0) : 0;
            const stones = collectStones();
            const stone = stones.reduce((sum, row) => sum + Number(row.value || 0), 0);
            const metalAmount = round2(net * rateValue);
            const wastageAmount = wastageCode === 'percentage' ? net * wastageValue / 100 * rateValue : (wastageCode === 'per_gram' ? net * wastageValue : wastageValue);
            const makingAmount = makingCode === 'per_gram' ? net * makingValue : (makingCode === 'percentage' ? metalAmount * makingValue / 100 : makingValue);
            const line = round2(metalAmount + wastageAmount + makingAmount + stone);
            const makingLabel = makingName();
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
                ...(wastageAmount > 0 ? [chargeRow('Hallmark', wastageCode, wastageValue, round2(wastageAmount), 'wastage')] : []),
                ...(makingAmount > 0 ? [chargeRow(makingLabel, makingCode, makingValue, round2(makingAmount), 'making')] : []),
            ];
            showBreakdown(parts, line, { gross, net, stone: stoneWeight, other });
            return { line, gross, net, metal: metalAmount, making: round2(makingAmount), wastage: round2(wastageAmount), stone: round2(stone), parts, stones };
        }

        function weighState() {
            const field = (id) => document.getElementById(id).value;
            const product = document.querySelector('#weigh-products button.active');
            return {
                name: field('weigh-name'),
                huid: field('weigh-huid'),
                product: product ? product.dataset.uuid : '',
                metal: field('weigh-metal'),
                purity: field('weigh-purity'),
                location: field('weigh-location'),
                net: field('weigh-net'),
                gross: field('weigh-gross'),
                stone: field('weigh-stone'),
                other: field('weigh-other-g'),
                making: field('weigh-making'),
                makingValue: field('weigh-making-value'),
                wastage: field('weigh-wastage'),
                wastageValue: field('weigh-wastage-value'),
                own: document.getElementById('weigh-own').checked ? '1' : '0',
                rate: field('weigh-metal-rate'),
                stones: [],
            };
        }

        function applyWeigh(state, keepRate) {
            const set = (id, value) => { document.getElementById(id).value = value; };
            set('weigh-name', state.name);
            set('weigh-huid', state.huid || '');
            document.querySelectorAll('#weigh-products button').forEach((row) => row.classList.toggle('active', !!state.product && row.dataset.uuid === state.product));
            set('weigh-metal', state.metal);
            set('weigh-purity', state.purity);
            set('weigh-location', state.location);
            set('weigh-gross', state.gross);
            set('weigh-stone', state.stone || '');
            set('weigh-other-g', state.other || '');
            set('weigh-making', state.making);
            set('weigh-making-value', state.makingValue);
            set('weigh-wastage', state.wastage);
            set('weigh-wastage-value', state.wastageValue);
            document.getElementById('weigh-own').checked = state.own === '1' || state.own === true;
            rateEdited = keepRate === true;
            if (rateEdited) set('weigh-metal-rate', state.rate || '');
            syncCharge('making');
            syncCharge('wastage');
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
            applyWeigh(weighDefaults, false);
            showEditing();
        }

        function editLine(index) {
            editingIndex = index;
            applyWeigh(freshLines[index].form, true);
            showEditing();
            renderBill();
            document.getElementById('weigh-name').closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('weigh-gross').focus({ preventScroll: true });
        }

        function makingName() {
            const mode = document.getElementById('making-mode').value;
            return mode === 'inside' ? 'Making' : 'Processing charge';
        }

        function partLabel(part) {
            const name = part.kind === 'making' ? makingName() : part.name;
            if (part.weight && part.rate && part.rate !== 'Fixed') return name + ' · ' + part.weight + ' × ' + part.rate;
            return part.rate ? name + ' · ' + part.rate : name;
        }

        function lineParts(line) {
            if (line.kind === 'retail') return [];
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
            if (row.wastageAmount) parts.push({ kind: 'wastage', name: 'Hallmark', amount: row.wastageAmount });
            if (row.makingAmount) parts.push({ kind: 'making', name: 'Making', amount: row.makingAmount });
            return parts;
        }

        function parseAmount(value) {
            const trimmed = String(value ?? '').trim().replace(/,/g, '');
            if (trimmed === '') return 0;
            const num = Number(trimmed);
            return Number.isFinite(num) ? num : 0;
        }

        function priceRetailLine(row) {
            const qty = Math.max(1, parseInt(String(row.quantity || 1), 10) || 1);
            const unit = round2(parseAmount(row.unit_price));
            if (!(unit > 0)) return null;
            const subtotal = round2(unit * qty);
            const mode = row.tax_mode || 'none';
            const manualTax = String(row.tax_amount ?? '').trim();
            if (mode === 'none') {
                return { line_amount: subtotal, tax_amount: 0, taxable_amount: subtotal };
            }
            if (manualTax !== '') {
                const tax = round2(parseAmount(manualTax));
                if (mode === 'exclusive') {
                    return { line_amount: round2(subtotal + tax), tax_amount: tax, taxable_amount: subtotal };
                }
                return { line_amount: subtotal, tax_amount: tax, taxable_amount: round2(subtotal - tax) };
            }
            const percent = Number(String(row.tax_percent ?? '').trim() || bill.gst || 0);
            if (!(percent > 0)) {
                return { line_amount: subtotal, tax_amount: 0, taxable_amount: subtotal };
            }
            if (mode === 'exclusive') {
                const tax = round2(subtotal * percent / 100);
                return { line_amount: round2(subtotal + tax), tax_amount: tax, taxable_amount: subtotal };
            }
            const tax = round2(subtotal * percent / (100 + percent));
            return { line_amount: subtotal, tax_amount: tax, taxable_amount: round2(subtotal - tax) };
        }

        function retailTotals() {
            return retailLines.reduce((acc, row) => {
                const priced = priceRetailLine(row);
                if (!priced) return acc;
                acc.line += priced.line_amount;
                acc.tax += priced.tax_amount;
                return acc;
            }, { line: 0, tax: 0 });
        }

        function syncRetailTaxFields() {
            const mode = document.getElementById('retail-tax-mode');
            if (!mode) return;
            const show = mode.value !== 'none';
            document.querySelectorAll('.retail-tax-field').forEach((row) => row.classList.toggle('d-none', !show));
        }

        function previewRetail() {
            const preview = document.getElementById('retail-preview');
            if (!preview) return;
            const priced = priceRetailLine({
                quantity: document.getElementById('retail-qty')?.value,
                unit_price: document.getElementById('retail-price')?.value,
                tax_mode: document.getElementById('retail-tax-mode')?.value,
                tax_percent: document.getElementById('retail-tax-percent')?.value,
                tax_amount: document.getElementById('retail-tax-amount')?.value,
            });
            preview.textContent = priced ? 'Line total ' + money.format(priced.line_amount) + (priced.tax_amount > 0 ? ' · GST ' + money.format(priced.tax_amount) : '') : '';
        }

        function bindRetailPanel() {
            const retailAdd = document.getElementById('retail-add');
            if (!retailAdd || retailAdd.dataset.bound === '1') return;
            retailAdd.dataset.bound = '1';
            const retailProductSelect = document.getElementById('retail-product');
            if (retailProductSelect) {
                retailProductSelect.addEventListener('change', () => {
                    const option = retailProductSelect.selectedOptions[0];
                    if (!option || !option.value) return;
                    document.getElementById('retail-name').value = option.dataset.name || '';
                    if (option.dataset.price) document.getElementById('retail-price').value = option.dataset.price;
                    document.getElementById('retail-tax-mode').value = option.getAttribute('data-tax-mode') || option.dataset.taxMode || 'none';
                    const taxPercent = option.getAttribute('data-tax-percent') || option.dataset.taxPercent;
                    if (taxPercent) document.getElementById('retail-tax-percent').value = taxPercent;
                    document.getElementById('retail-tax-amount').value = '';
                    syncRetailTaxFields();
                    previewRetail();
                });
            }
            ['retail-qty', 'retail-price', 'retail-tax-mode', 'retail-tax-percent', 'retail-tax-amount'].forEach((id) => {
                const field = document.getElementById(id);
                if (!field) return;
                field.addEventListener('input', previewRetail);
                if (id === 'retail-tax-mode') field.addEventListener('change', syncRetailTaxFields);
            });
            syncRetailTaxFields();
            retailAdd.addEventListener('click', () => {
                const error = document.getElementById('retail-error');
                const nameEl = document.getElementById('retail-name');
                const priceEl = document.getElementById('retail-price');
                const qtyEl = document.getElementById('retail-qty');
                const taxModeEl = document.getElementById('retail-tax-mode');
                const taxPercentEl = document.getElementById('retail-tax-percent');
                const taxAmountEl = document.getElementById('retail-tax-amount');
                if (!nameEl || !priceEl || !taxModeEl) return;
                const name = nameEl.value.trim();
                const row = {
                    product_uuid: retailProductSelect && retailProductSelect.value ? retailProductSelect.value : '',
                    name,
                    quantity: qtyEl ? (qtyEl.value || '1') : '1',
                    unit_price: priceEl.value.trim(),
                    tax_mode: taxModeEl.value,
                    tax_percent: taxPercentEl ? taxPercentEl.value.trim() : '',
                    tax_amount: taxAmountEl ? taxAmountEl.value.trim() : '',
                    hsn_code: retailProductSelect && retailProductSelect.selectedOptions[0] ? (retailProductSelect.selectedOptions[0].dataset.hsn || '') : '',
                };
                const priced = priceRetailLine(row);
                if (!error) return;
                if (name === '' || !priced) {
                    error.textContent = name === '' ? 'Type the item name.' : 'Enter a price greater than zero.';
                    error.classList.remove('d-none');
                    return;
                }
                error.classList.add('d-none');
                row.unit_price = String(parseAmount(row.unit_price));
                retailLines.push(row);
                nameEl.value = '';
                priceEl.value = '';
                if (taxAmountEl) taxAmountEl.value = '';
                if (qtyEl) qtyEl.value = '1';
                if (retailProductSelect) retailProductSelect.value = '';
                previewRetail();
                renderBill();
            });
        }

        bindRetailPanel();

        function renderBill() {
            const body = document.getElementById('bill-lines');
            const inputs = document.getElementById('bill-piece-inputs');
            body.innerHTML = '';
            inputs.innerHTML = '';
            const lines = [
                ...stockLines.map((row) => ({ kind: 'stock', title: row.code + ' · ' + row.name, amount: row.line, row })),
                ...freshLines.map((row, index) => ({ kind: 'fresh', title: row.name + ' · ' + row.metal, amount: row.line, row, index })),
                ...retailLines.map((row, index) => {
                    const priced = priceRetailLine(row) || { line_amount: 0, tax_amount: 0 };
                    return { kind: 'retail', title: row.name, amount: priced.line_amount, row, index, priced };
                }),
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
                const huid = line.kind === 'stock' ? line.row.huid : line.row.fields?.huid;
                meta.textContent = line.kind === 'retail'
                    ? ['Other item', 'Qty ' + (line.row.quantity || 1), line.row.tax_mode === 'none' ? 'No GST' : (line.row.tax_mode === 'exclusive' ? 'GST extra' : 'GST in price')].filter(Boolean).join(' · ')
                    : line.kind === 'stock'
                    ? [line.row.code, huid ? 'HUID ' + huid : '', line.row.metal, line.row.gross ? 'Gross ' + line.row.gross : '', line.row.net ? 'Net ' + line.row.net : ''].filter(Boolean).join(' · ')
                    : [line.row.category, line.row.metal, huid ? 'HUID ' + huid : '', (line.row.fields.own === '1' || line.row.fields.own === true) ? 'Own' : '', 'Gross ' + Number(line.row.fields.gross_weight || 0).toFixed(3) + ' g'].filter(Boolean).join(' · ');
                nameCell.append(head, meta);
                if (line.kind !== 'retail') {
                    const split = document.createElement('div');
                    split.className = 'piece-split bill-line-split';
                    showSplit(split, lineParts(line).map((part) => ({ label: partLabel(part), value: money.format(part.amount) })));
                    nameCell.appendChild(split);
                }
                const action = document.createElement('td');
                action.className = 'text-end bill-line-cell';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'bill-remove';
                button.setAttribute('aria-label', 'Remove');
                button.title = 'Remove';
                button.textContent = '×';
                if (line.kind === 'retail' && line.priced.tax_amount > 0) {
                    const gst = document.createElement('div');
                    gst.className = 'bill-line-meta';
                    gst.textContent = 'GST ' + money.format(line.priced.tax_amount);
                    nameCell.appendChild(gst);
                }
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
                        clearScanMessage(line.row.uuid);
                        setTimeout(() => scanInput.focus(), 0);
                    } else if (line.kind === 'retail') {
                        retailLines.splice(line.index, 1);
                    } else {
                        freshLines.splice(line.index, 1);
                        if (editingIndex === line.index) {
                            resetWeigh();
                        } else if (editingIndex !== null && editingIndex > line.index) {
                            editingIndex -= 1;
                        }
                    }
                    renderBill();
                });
                body.appendChild(tr);
                if (line.kind === 'stock') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'item_ids[]';
                    input.value = line.row.uuid;
                    inputs.appendChild(input);
                } else if (line.kind === 'retail') {
                    ['product_uuid', 'name', 'quantity', 'unit_price', 'tax_mode', 'tax_percent', 'tax_amount', 'hsn_code'].forEach((key) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'retail_items[' + line.index + '][' + key + ']';
                        input.value = line.row[key] ?? '';
                        inputs.appendChild(input);
                    });
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

        function pieceBase() {
            const rows = [...stockLines, ...freshLines];
            const subtotal = round2(rows.reduce((sum, row) => sum + Number(row.line || row.amount || 0), 0));

            return {
                rows,
                subtotal,
                making: Math.min(subtotal, round2(rows.reduce((sum, row) => sum + Number(row.makingAmount || 0), 0))),
                stoneTotal: round2(rows.reduce((sum, row) => sum + Number(row.stoneAmount || 0), 0)),
                wastageTotal: round2(rows.reduce((sum, row) => sum + Number(row.wastageAmount || 0), 0)),
                netWeight: rows.reduce((sum, row) => sum + Number(row.netWeight || 0), 0),
                metalValue: round2(rows.reduce((sum, row) => sum + Number(row.metalAmount || 0), 0)),
            };
        }

        function pricedBill(discountValue) {
            const base = pieceBase();
            const mode = document.getElementById('making-mode').value;
            const hallmark = bill.hallmarkOutside ? Math.min(base.subtotal, base.wastageTotal) : 0;
            const taxedSubtotal = round2(base.subtotal - hallmark);
            const making = Math.min(taxedSubtotal, base.making);
            const discount = Math.min(taxedSubtotal, Math.max(0, round2(parseAmount(discountValue))));
            const taxOf = (amount, percent) => percent > 0
                ? round2(bill.exclusive ? amount * percent / 100 : amount * percent / (percent + 100))
                : 0;
            let tax = 0;
            let makingTax = 0;
            let taxBase = round2(taxedSubtotal - discount);
            let makingBase = 0;
            let exact = taxBase;
            if (mode === 'inside') {
                tax = taxOf(exact, bill.gst);
                if (bill.exclusive) exact = round2(exact + tax);
            } else {
                const jewellery = round2(taxedSubtotal - making);
                const jewelleryDiscount = Math.min(discount, jewellery);
                const jewelleryNet = round2(jewellery - jewelleryDiscount);
                const makingNet = round2(making - (discount - jewelleryDiscount));
                taxBase = jewelleryNet;
                makingBase = makingNet;
                tax = taxOf(jewelleryNet, bill.gst);
                makingTax = mode === 'separate' ? taxOf(makingNet, bill.makingGst) : 0;
                exact = round2(jewelleryNet + makingNet + (bill.exclusive ? tax + makingTax : 0));
            }
            exact = round2(exact + hallmark);
            let total = exact;
            let roundOff = 0;
            if (bill.round) {
                total = Math.round(exact);
                roundOff = round2(total - exact);
            }
            const retail = retailTotals();
            exact = round2(exact + retail.line);
            total = round2(total + retail.line);
            tax = round2(tax + retail.tax);

            return { ...base, making, mode, hallmark, taxedSubtotal, discount, tax, makingTax, taxBase, makingBase, exact, total, roundOff, retail };
        }

        function discountForPayable(target) {
            const subtotal = pieceBase().subtotal;
            if (subtotal <= 0 || !(target > 0)) {
                return 0;
            }
            const desired = bill.round ? Math.round(target) : round2(target);
            if (desired >= pricedBill(0).total) {
                return 0;
            }
            let low = 0;
            let high = Math.round(subtotal * 100);
            let edge = high;
            while (low <= high) {
                const mid = Math.floor((low + high) / 2);
                if (pricedBill(mid / 100).total > desired) {
                    low = mid + 1;
                } else {
                    edge = mid;
                    high = mid - 1;
                }
            }
            let best = edge;
            let bestGap = Math.abs(pricedBill(edge / 100).exact - desired);
            const ceiling = Math.round(subtotal * 100);
            for (let step = edge; step <= ceiling; step += 1) {
                const quote = pricedBill(step / 100);
                if (quote.total !== desired) {
                    break;
                }
                const gap = Math.abs(quote.exact - desired);
                if (gap < bestGap) {
                    best = step;
                    bestGap = gap;
                }
                if (gap < 0.001) {
                    break;
                }
            }

            return best / 100;
        }

        let payLocked = false;

        function renderTotals() {
            if (payLocked) {
                const raw = String(document.getElementById('customer-pays').value).replace(/,/g, '').trim();
                const target = Number(raw);
                if (raw !== '' && Number.isFinite(target) && target >= 0) {
                    document.getElementById('discount').value = discountForPayable(target).toFixed(2);
                    document.getElementById('discount').readOnly = true;
                }
            }
            const priced = pricedBill(document.getElementById('discount').value);
            const rows = priced.rows;
            const subtotal = priced.taxedSubtotal;
            const hallmark = priced.hallmark;
            const mode = priced.mode;
            const making = priced.making;
            const discount = priced.discount;
            const tax = priced.tax;
            const makingTax = priced.makingTax;
            const taxBase = priced.taxBase;
            const makingBase = priced.makingBase;
            const roundOff = priced.roundOff;
            const total = priced.total;
            const stoneTotal = priced.stoneTotal;
            const wastageTotal = priced.wastageTotal;
            const showRow = (id, amount, show) => {
                const row = document.getElementById(id + '-row');
                const cell = document.getElementById(id);
                if (!row || !cell) return;
                row.classList.toggle('d-none', !show);
                cell.textContent = money.format(amount);
            };
            showRow('bill-stone', stoneTotal, stoneTotal > 0);
            showRow('bill-wastage', wastageTotal, wastageTotal > 0 && !bill.hallmarkOutside);
            showRow('bill-hallmark', hallmark, hallmark > 0);
            showRow('bill-making', making, making > 0);
            showRow('bill-discount', discount, discount > 0);
            document.getElementById('bill-discount').textContent = '− ' + money.format(discount);
            showRow('bill-after-discount', round2(subtotal - discount), discount > 0);
            document.getElementById('bill-making-label').textContent = {
                inside: 'Making (in jewellery GST)',
                separate: 'Processing charge (GST ' + bill.makingGst + '%)',
                processing: 'Processing charge (no GST)',
            }[mode];
            const taxParts = ['metal'];
            if (stoneTotal > 0) taxParts.push('stones');
            if (wastageTotal > 0 && !bill.hallmarkOutside) taxParts.push('hallmark');
            if (mode === 'inside' && making > 0) taxParts.push('making');
            const taxOn = taxParts.join(' + ') + (discount > 0 ? ' after discount' : '');
            const retailTax = priced.retail ? priced.retail.tax : 0;
            const taxNote = subtotal <= 0 && retailTax <= 0 ? '' : (subtotal <= 0 ? ' incl. other items' : ' on ' + taxOn + (bill.exclusive ? ' ' + money.format(taxBase) : ' (included)'));
            document.getElementById('bill-tax-label').textContent = 'GST ' + bill.gst + '%' + taxNote;
            document.getElementById('bill-making-tax-row').classList.toggle('d-none', mode !== 'separate' || making <= 0);
            document.getElementById('bill-making-tax-label').textContent = 'GST ' + bill.makingGst + '% on processing charge' + (subtotal > 0 ? (bill.exclusive ? ' ' + money.format(makingBase) : ' (included)') : '');
            document.getElementById('bill-making-tax').textContent = money.format(makingTax);
            document.getElementById('weigh-making-label').textContent = makingName();
            document.getElementById('weigh-making-value').setAttribute('aria-label', makingName() + ' amount');
            const netWeight = rows.reduce((sum, row) => sum + Number(row.netWeight || 0), 0);
            const metalValue = round2(rows.reduce((sum, row) => sum + Number(row.metalAmount || 0), 0));
            document.getElementById('bill-net-weight').textContent = netWeight.toFixed(3) + ' g';
            document.getElementById('bill-metal').textContent = money.format(metalValue);
            document.getElementById('bill-subtotal').textContent = money.format(subtotal);
            const retailAmount = priced.retail ? priced.retail.line : 0;
            showRow('bill-retail', retailAmount, retailAmount > 0);
            document.getElementById('bill-tax').textContent = money.format(tax);
            showRow('bill-round', roundOff, roundOff !== 0);
            document.getElementById('bill-total').textContent = money.format(total);
            document.getElementById('bill-form').dataset.total = String(total);
            const payNote = document.getElementById('customer-pays-note');
            if (payLocked) {
                const typed = Number(String(document.getElementById('customer-pays').value).replace(/,/g, ''));
                const desired = bill.round ? Math.round(typed) : round2(typed);
                if (desired > pricedBill(0).total) {
                    payNote.textContent = 'Above this bill, so the discount stays zero.';
                    payNote.classList.remove('d-none');
                } else if (total !== desired) {
                    payNote.textContent = 'Closest total is ' + money.format(total) + '.';
                    payNote.classList.remove('d-none');
                } else {
                    payNote.textContent = '';
                    payNote.classList.add('d-none');
                }
            } else {
                payNote.textContent = '';
                payNote.classList.add('d-none');
                document.getElementById('discount').readOnly = false;
            }
            let payNow = total;
            if (bill.order) {
                const advance = Math.min(bill.order.advance, total);
                payNow = round2(total - advance);
                document.getElementById('bill-advance').textContent = '− ' + money.format(advance);
            }
            const oldBox = document.getElementById('include-previous');
            const oldDue = bill.fixedPrevious
                ? Number(bill.fixedPrevious.amount || 0)
                : (customer && !customer.walkin && oldBox && oldBox.checked ? Number(customer.due_amount || 0) : 0);
            document.getElementById('bill-old-due-row').classList.toggle('d-none', bill.fixedPrevious ? oldDue <= 0 : !(customer && Number(customer.due_amount) > 0 && !customer.walkin));
            document.getElementById('bill-old-due-label').textContent = bill.fixedPrevious
                ? '(' + bill.fixedPrevious.bills + (bill.fixedPrevious.bills === 1 ? ' bill already on this invoice)' : ' bills already on this invoice)')
                : (customer && customer.due_bills ? '(' + customer.due_bills + (customer.due_bills === 1 ? ' bill)' : ' bills)') : '');
            document.getElementById('bill-old-due').textContent = money.format(oldDue);
            const credit = customer && !customer.walkin ? Number(customer.credit || 0) : 0;
            const creditToggle = document.getElementById('use-credit-toggle');
            const creditUsed = credit > 0 && creditToggle && creditToggle.checked ? round2(Math.min(credit, payNow)) : 0;
            document.getElementById('bill-credit-row').classList.toggle('d-none', credit <= 0);
            document.getElementById('bill-credit-available').textContent = money.format(credit) + ' on account';
            document.getElementById('bill-credit').textContent = '− ' + money.format(creditUsed);
            document.getElementById('use-credit').value = creditUsed.toFixed(2);
            payNow = round2(payNow - creditUsed + oldDue);
            document.getElementById('bill-pay-now-row').classList.toggle('d-none', !bill.order && creditUsed <= 0 && oldDue <= 0);
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
            payState = {
                total,
                advance: bill.order ? Math.min(bill.order.advance, total) : 0,
                oldDue,
                creditUsed,
            };
            renderPayExtra();
        }

        function renderPayExtra() {
            const note = document.getElementById('pay-extra-note');
            const paid = round2(Array.from(document.querySelectorAll('.bill-pay'))
                .reduce((sum, field) => sum + (Number(String(field.value).replace(/,/g, '')) || 0), 0));
            const remaining = Math.max(payState.total - paid - payState.advance, 0);
            const credit = Math.min(payState.creditUsed, remaining);
            const extra = round2(paid + payState.advance + credit - payState.total - payState.oldDue);
            if (!customer || payState.total <= 0 || extra <= 0) {
                note.textContent = '';
                note.className = 'small d-none mb-0';
                return;
            }
            if (customer.walkin) {
                note.textContent = '₹ ' + money.format(extra) + ' more than the bill. A walk-in pays the exact total, so give the change back.';
                note.className = 'small text-danger mb-0';
                return;
            }
            note.textContent = '₹ ' + money.format(extra) + ' more than the bill. It stays on ' + customer.name + '\'s account as advance and can be taken off the next bill.';
            note.className = 'small text-success fw-semibold mb-0';
        }

        const scanInput = document.getElementById('scan-code');
        const scanQueue = [];
        let scanRunning = false;

        function scanMessage(kind, text, uuid = '') {
            const status = document.getElementById('scan-status');
            status.className = 'scan-status is-' + kind;
            status.textContent = text;
            status.dataset.uuid = uuid;
        }

        function clearScanMessage(uuid) {
            const status = document.getElementById('scan-status');
            if (!status.dataset.uuid || status.dataset.uuid !== uuid) return;
            status.className = 'scan-status';
            status.textContent = '';
            status.dataset.uuid = '';
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
                scanMessage('warning', already.code + ' is already on this bill.', already.uuid);
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
                scanMessage('warning', piece.code + ' is already on this bill.', piece.uuid);
                return;
            }
            const known = bill.pieces.findIndex((row) => row.uuid === piece.uuid);
            if (known === -1) bill.pieces.push(piece); else bill.pieces[known] = piece;
            stockLines.push(piece);
            renderBill();
            scanMessage('success', 'Added ' + [piece.code, piece.name, piece.metal, piece.net ? 'Net ' + piece.net : '', piece.label].filter(Boolean).join(' · '), piece.uuid);
        }

        if (scanInput) {
            scanInput.addEventListener('keydown', (event) => {
                if (event.isComposing) return;
                if (event.key === 'Enter' || (event.key === 'Tab' && !event.shiftKey && scanInput.value.trim() !== '')) {
                    event.preventDefault();
                    queueScan();
                }
            });
        }
        const scanAdd = document.getElementById('scan-add');
        if (scanAdd && scanInput) {
            scanAdd.addEventListener('click', () => {
                queueScan();
                scanInput.focus();
            });
        }
        document.getElementById('bill-form').addEventListener('keydown', (event) => {
            const field = event.target;
            if (event.key !== 'Enter' || field === scanInput || field.id === 'customer-search' || field.tagName !== 'INPUT') return;
            if (['button', 'submit', 'checkbox', 'radio'].includes(field.type)) return;
            event.preventDefault();
        });

        document.getElementById('discount').addEventListener('input', () => {
            payLocked = false;
            document.getElementById('customer-pays').value = '';
            document.getElementById('discount').readOnly = false;
            renderTotals();
        });
        document.getElementById('customer-pays').addEventListener('input', () => {
            const raw = String(document.getElementById('customer-pays').value).replace(/,/g, '').trim();
            payLocked = raw !== '';
            renderTotals();
        });
        document.getElementById('use-credit-toggle').addEventListener('change', renderTotals);
        document.getElementById('include-previous').addEventListener('change', renderTotals);
        document.getElementById('making-mode').addEventListener('change', () => {
            renderBill();
            previewWeigh();
        });
        document.getElementById('bill-form').addEventListener('input', (event) => {
            if (event.target.id === 'weigh-metal' || event.target.id === 'weigh-purity') rateEdited = false;
            if (event.target.id === 'weigh-metal-rate') rateEdited = true;
            if ((event.target.id && event.target.id.startsWith('weigh-')) || event.target.closest('#stone-list')) previewWeigh();
        });
        document.getElementById('weigh-metal').addEventListener('change', () => {
            rateEdited = false;
            previewWeigh();
        });
        document.getElementById('weigh-purity').addEventListener('change', () => {
            rateEdited = false;
            previewWeigh();
        });
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
            const nameField = document.getElementById('weigh-name');
            const categoryNames = Array.from(document.querySelectorAll('#weigh-products button')).map((row) => row.dataset.name);
            document.querySelectorAll('#weigh-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            if (nameField.value.trim() === '' || categoryNames.includes(nameField.value.trim())) nameField.value = button.dataset.name;
            document.getElementById('weigh-gross').focus();
            previewWeigh();
        });
        document.getElementById('use-total').addEventListener('click', () => {
            const field = document.querySelector('.bill-pay');
            const pay = Number(document.getElementById('bill-form').dataset.pay || 0);
            if (field) field.value = pay > 0 ? pay.toFixed(2) : '';
            renderPayExtra();
        });
        document.querySelectorAll('.bill-pay').forEach((field) => field.addEventListener('input', renderPayExtra));
        document.getElementById('weigh-add').addEventListener('click', () => {
            const error = document.getElementById('weigh-error');
            const quote = previewWeigh();
            const name = document.getElementById('weigh-name').value.trim();
            const stones = collectStones();
            const keptStones = stones.length ? stones : (editingIndex !== null ? (freshLines[editingIndex].stones || []) : []);
            const category = document.querySelector('#weigh-products button.active');
            const needsCategory = document.querySelectorAll('#weigh-products button').length > 0 && !category;
            if (needsCategory || name === '' || quote === null) {
                error.textContent = needsCategory ? 'Choose a category.' : (name === '' ? 'Type the piece name.' : document.getElementById('weigh-price').textContent);
                error.classList.remove('d-none');
                return;
            }
            error.classList.add('d-none');
            const metal = option('weigh-metal');
            const purity = option('weigh-purity');
            const typedStone = document.getElementById('weigh-stone').value.trim();
            const stoneWeightTotal = typedStone !== '' ? Number(typedStone) : keptStones.reduce((sum, stone) => sum + Number(stone.weight || 0), 0);
            const stoneValueTotal = keptStones.reduce((sum, stone) => sum + Number(stone.value || 0), 0);
            const entry = {
                name,
                category: category ? category.dataset.name : '',
                form: weighState(),
                metal: metal.text + ' ' + purity.text,
                line: quote.line,
                netWeight: quote.net,
                metalAmount: quote.metal,
                makingAmount: quote.making,
                wastageAmount: quote.wastage,
                stoneAmount: quote.stone,
                parts: quote.parts,
                stones: keptStones,
                fields: {
                    name,
                    category_uuid: category ? category.dataset.uuid : '',
                    huid: document.getElementById('weigh-huid').value.trim().toUpperCase(),
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
                    own: document.getElementById('weigh-own').checked ? '1' : '0',
                    rate_per_gram: readRate().toFixed(2),
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
            if (stockLines.length === 0 && freshLines.length === 0 && retailLines.length === 0) {
                event.preventDefault();
                scanInput.focus();
            }
        });
        document.getElementById('bill-preview').addEventListener('click', async () => {
            if (!picker.ensure()) return;
            if (stockLines.length === 0 && freshLines.length === 0 && retailLines.length === 0) {
                scanInput.focus();
                return;
            }
            const button = document.getElementById('bill-preview');
            const body = document.getElementById('bill-preview-body');
            const saveButton = document.getElementById('bill-preview-save');
            body.innerHTML = '<div class="text-secondary small">Making the preview…</div>';
            saveButton.disabled = true;
            button.disabled = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('bill-preview-modal')).show();
            try {
                const response = await fetch(bill.previewUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': bill.csrf },
                    body: new FormData(document.getElementById('bill-form')),
                });
                if (response.status === 422) {
                    const payload = await response.json();
                    const box = document.createElement('div');
                    box.className = 'alert alert-danger mb-0';
                    const title = document.createElement('div');
                    title.className = 'fw-semibold mb-1';
                    title.textContent = 'Fix these before saving:';
                    const list = document.createElement('ul');
                    list.className = 'mb-0';
                    [...new Set(Object.values(payload.errors || {}).flat())].forEach((message) => {
                        const item = document.createElement('li');
                        item.textContent = message;
                        list.appendChild(item);
                    });
                    box.append(title, list);
                    body.replaceChildren(box);
                    return;
                }
                if (!response.ok) throw new Error();
                body.innerHTML = await response.text();
                saveButton.disabled = false;
            } catch (error) {
                body.innerHTML = '<div class="alert alert-danger mb-0">The preview could not be made. Check the connection and try again.</div>';
            } finally {
                button.disabled = false;
            }
        });
        document.getElementById('bill-preview-save').addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('bill-preview-modal')).hide();
            document.getElementById('bill-form').requestSubmit();
        });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) resetWeigh();
        });
        try {
            weighDefaults = weighState();
        } catch (error) {
            weighDefaults = null;
        }
        if (bill.editing) {
            bill.editing.stock.forEach((uuid) => {
                const row = bill.pieces.find((piece) => piece.uuid === uuid);
                if (row) stockLines.push(row);
            });
            bill.editing.fresh.forEach((row) => freshLines.push(row));
            (bill.editing.retail || []).forEach((row) => retailLines.push(row));
            if (bill.fixedPrevious) document.getElementById('include-previous').checked = false;
        }
        renderBill();
        previewWeigh();
        if (scanInput && (document.activeElement === document.body || document.activeElement === null)) scanInput.focus();
    </script>
@endpush
