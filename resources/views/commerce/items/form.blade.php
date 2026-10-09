@extends('layouts.app')

@section('title', $item->exists ? 'Edit '.$item->item_code : 'Add piece')

@section('content')
    @php
        $num = function ($value, int $scale = 2): string {
            if ($value === null || $value === '' || (float) $value == 0) {
                return '';
            }

            return rtrim(rtrim(number_format((float) $value, $scale, '.', ''), '0'), '.');
        };
        $field = fn (string $name, $value, int $scale = 2) => old($name, $num($value, $scale));
        $moreFields = ['sku', 'barcode', 'rfid', 'brand_uuid', 'collection_uuid', 'design_uuid', 'certificate_number', 'hallmark', 'selling_price', 'mrp', 'image', 'location_uuid'];
        $moreOpen = $errors->hasAny($moreFields)
            || filled($item->barcode) || filled($item->rfid) || filled($item->certificate_number) || filled($item->hallmark)
            || $item->brand_id || $item->collection_id || $item->design_id
            || (float) $item->selling_price > 0 || (float) $item->mrp > 0;
        $topCategories = $categories->whereNull('parent_id')->take(10);
        $unitLabels = [
            'making' => ['per_gram' => '₹ / g', 'percentage' => '% of metal', 'fixed' => '₹ per piece', 'per_piece' => '₹ per piece'],
            'wastage' => ['per_gram' => '₹ / g', 'percentage' => '% of metal', 'fixed' => '₹ per piece'],
        ];
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <a href="{{ $item->exists ? route('items.show', $item) : route('items.index') }}"><i class="bi bi-arrow-left"></i> {{ $item->exists ? 'Back to '.$item->item_code : 'All pieces' }}</a>
    </div>
    <h1 class="page-title h3 mb-1">{{ $item->exists ? 'Edit '.$item->name : 'Add piece' }}</h1>
    <p class="text-secondary mb-3">Weigh the piece and choose its making. The price on the right follows today’s rate, the same way the bill works it out.</p>

    @if ($fromOldGold ?? null)
        <div class="alert alert-info">
            <i class="bi bi-recycle"></i> Making a stock piece from old gold <a href="{{ route('old-gold.show', $fromOldGold) }}">{{ $fromOldGold->number }}</a>. The weight is taken out of old gold stock. Keep the same metal and purity, and give it an item code.
        </div>
    @elseif ($like ?? null)
        <div class="alert alert-light border">
            <i class="bi bi-copy"></i> Metal, purity, category and making are copied from <a href="{{ route('items.show', $like) }}">{{ $like->item_code }}</a>. Weigh the next piece.
        </div>
    @endif

    <form method="POST" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" enctype="multipart/form-data" id="piece-form" autocomplete="off">
        @csrf
        @if ($item->exists)
            @method('PUT')
            <input type="hidden" name="item_code" value="{{ $item->item_code }}">
            <input type="hidden" name="sku" value="{{ $item->sku }}">
        @endif
        @if ($fromOldGold ?? null)
            <input type="hidden" name="old_gold_uuid" value="{{ $fromOldGold->uuid }}">
        @elseif (old('old_gold_uuid'))
            <input type="hidden" name="old_gold_uuid" value="{{ old('old_gold_uuid') }}">
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white">Piece</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $item->name) }}" maxlength="160" placeholder="Gold ring with stone" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="item_code">Item code</label>
                                <input class="form-control text-uppercase @error('item_code') is-invalid @enderror" id="item_code" name="item_code" value="{{ old('item_code', $item->item_code) }}" maxlength="40" required @disabled($item->exists)>
                            </div>
                        </div>
                        @if ($topCategories->isNotEmpty())
                            <div class="bill-products mt-2" id="category-chips">
                                @foreach ($topCategories as $category)
                                    <button type="button" data-category="{{ $category->uuid }}" data-name="{{ $category->name }}">{{ $category->name }}</button>
                                @endforeach
                            </div>
                        @endif
                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label" for="category_uuid">Category</label>
                                <select class="form-select" id="category_uuid" name="category_uuid">
                                    <option value="">None</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->uuid }}" data-name="{{ $category->name }}" @selected(old('category_uuid', $item->category?->uuid) === $category->uuid)>{{ $category->parent ? $category->parent->name.' / '.$category->name : $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="huid">HUID</label>
                                <input class="form-control text-uppercase" id="huid" name="huid" value="{{ old('huid', $item->huid) }}" maxlength="32" placeholder="6-character hallmark ID">
                            </div>
                        </div>
                        @unless ($item->exists)
                            <div class="form-text">The item code is the next free number. Change it if you print your own tags.</div>
                        @endunless
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white">Metal and weight</div>
                    <div class="card-body">
                        <div class="stone-box mt-0">
                            <div class="metal-row">
                                <div>
                                    <label for="metal_uuid">Metal</label>
                                    <select class="form-select" id="metal_uuid" name="metal_uuid" required>
                                        @foreach ($metals as $metal)
                                            <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected(old('metal_uuid', $item->metalType?->uuid) === $metal->uuid)>{{ $metal->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="purity_uuid">Purity</label>
                                    <select class="form-select" id="purity_uuid" name="purity_uuid" required>
                                        @foreach ($metals as $metal)
                                            @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                                                <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected(old('purity_uuid', $item->purity?->uuid) === $purity->uuid)>{{ $purity->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="gross_weight">Gross g</label>
                                    <input class="form-control metal-net @error('gross_weight') is-invalid @enderror" id="gross_weight" name="gross_weight" value="{{ $field('gross_weight', $item->gross_weight, 3) }}" inputmode="decimal" placeholder="0.000" required>
                                </div>
                                <div>
                                    <label for="other_weight">Other g</label>
                                    <input class="form-control" id="other_weight" name="other_weight" value="{{ $field('other_weight', $item->other_weight, 3) }}" inputmode="decimal" placeholder="0.000">
                                </div>
                                <div>
                                    <label for="net-weight">Net g</label>
                                    <input class="form-control" id="net-weight" readonly tabindex="-1" placeholder="0.000">
                                </div>
                            </div>
                            <div class="form-text">Net = gross less stones and other weight (thread, lac, wax). Making and the metal price are on the net weight.</div>
                        </div>

                        @php
                            $stoneRows = old('stones');
                            if (! is_array($stoneRows)) {
                                $stoneRows = $item->exists
                                    ? $item->stones->map(fn ($stone) => ['name' => $stone->name, 'weight' => $num($stone->weight, 3), 'value' => $num($stone->value), 'rate' => $num($stone->rate), 'rate_unit' => $stone->rate_unit ?? 'fixed'])->all()
                                    : [];
                            }
                        @endphp
                        <div class="stone-box">
                            <div class="stone-box-head">
                                <span class="weigh-section-title mb-0">Stones</span>
                                <button class="btn btn-link btn-sm p-0" id="item-stone-add" type="button"><i class="bi bi-plus-lg"></i> Add stone</button>
                            </div>
                            <input type="hidden" name="stones" value="">
                            <datalist id="stone-type-names">
                                @foreach ($stoneTypes as $stoneType)
                                    <option value="{{ $stoneType }}"></option>
                                @endforeach
                            </datalist>
                            <div id="item-stone-list">
                                @foreach ($stoneRows as $index => $stone)
                                    <div class="stone-row">
                                        <div>
                                            <label for="stone-name-{{ $index }}">Name</label>
                                            <input class="form-control" id="stone-name-{{ $index }}" name="stones[{{ $index }}][name]" value="{{ $stone['name'] ?? '' }}" placeholder="Diamond" list="stone-type-names" autocomplete="off">
                                        </div>
                                        <div>
                                            <label for="stone-weight-{{ $index }}">Weight g</label>
                                            <input class="form-control stone-weight" id="stone-weight-{{ $index }}" name="stones[{{ $index }}][weight]" value="{{ $stone['weight'] ?? '' }}" inputmode="decimal" placeholder="0.000">
                                        </div>
                                        <div>
                                            <label for="stone-unit-{{ $index }}">Per</label>
                                            <select class="form-select stone-unit" id="stone-unit-{{ $index }}" name="stones[{{ $index }}][rate_unit]">
                                                @foreach (['gram' => 'Gram', 'carat' => 'Carat', 'fixed' => 'Fixed'] as $unit => $unitName)
                                                    <option value="{{ $unit }}" @selected(($stone['rate_unit'] ?? 'fixed') === $unit)>{{ $unitName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="stone-rate-{{ $index }}">Rate</label>
                                            <input class="form-control stone-rate" id="stone-rate-{{ $index }}" name="stones[{{ $index }}][rate]" value="{{ $stone['rate'] ?? '' }}" inputmode="decimal" placeholder="0">
                                        </div>
                                        <div>
                                            <label for="stone-value-{{ $index }}">Value ₹</label>
                                            <input class="form-control stone-value" id="stone-value-{{ $index }}" name="stones[{{ $index }}][value]" value="{{ $stone['value'] ?? '' }}" inputmode="decimal" placeholder="0">
                                        </div>
                                        <button class="bill-remove" type="button" aria-label="Remove stone">×</button>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text" id="stone-empty" @if ($stoneRows !== []) hidden @endif>No stones. Use Add stone for diamonds, kundan or other stones. Carat rates use 1 g = 5 ct.</div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white">Making and wastage</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="making_method_uuid">Making</label>
                                <div class="charge-pair">
                                    <select class="form-select" id="making_method_uuid" name="making_method_uuid">
                                        <option value="" data-code="">None</option>
                                        @foreach ($makingMethods as $method)
                                            <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" data-unit="{{ $unitLabels['making'][$method->code] ?? '' }}" @selected(old('making_method_uuid', $item->makingMethod?->uuid) === $method->uuid)>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group">
                                        <input class="form-control" id="making_value" name="making_value" value="{{ $field('making_value', $item->making_value, 4) }}" inputmode="decimal" placeholder="0" aria-label="Making amount">
                                        <span class="input-group-text" id="making-unit"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="wastage_method_uuid">Wastage</label>
                                <div class="charge-pair">
                                    <select class="form-select" id="wastage_method_uuid" name="wastage_method_uuid">
                                        <option value="" data-code="">None</option>
                                        @foreach ($wastageMethods as $method)
                                            <option value="{{ $method->uuid }}" data-code="{{ $method->code }}" data-unit="{{ $unitLabels['wastage'][$method->code] ?? '' }}" @selected(old('wastage_method_uuid', $item->wastageMethod?->uuid) === $method->uuid)>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group">
                                        <input class="form-control" id="wastage_value" name="wastage_value" value="{{ $field('wastage_value', $item->wastage_value, 4) }}" inputmode="decimal" placeholder="0" aria-label="Wastage amount">
                                        <span class="input-group-text" id="wastage-unit"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">These are the piece’s usual charges. They can still be changed on the bill.</div>
                    </div>
                </div>

                <details class="card mb-3 piece-more" @if ($moreOpen) open @endif>
                    <summary class="card-body fw-semibold">More details <span class="text-secondary fw-normal small">· barcode, brand, certificate, tag price, photo</span></summary>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="location_uuid">Kept at</label>
                                <select class="form-select" id="location_uuid" name="location_uuid" required>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->uuid }}" data-branch="{{ $location->branch_id }}" @selected(old('location_uuid', $item->location?->uuid) === $location->uuid)>{{ $location->branch?->name }} / {{ $location->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @unless ($item->exists)
                                <div class="col-md-4">
                                    <label class="form-label" for="sku">SKU</label>
                                    <input class="form-control text-uppercase" id="sku" name="sku" value="{{ old('sku') }}" maxlength="40" placeholder="Same as item code">
                                </div>
                            @endunless
                            <div class="col-md-4">
                                <label class="form-label" for="barcode">Barcode</label>
                                <input class="form-control" id="barcode" name="barcode" value="{{ old('barcode', $item->barcode) }}" maxlength="64">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rfid">RFID</label>
                                <input class="form-control" id="rfid" name="rfid" value="{{ old('rfid', $item->rfid) }}" maxlength="64">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="brand_uuid">Brand</label>
                                <select class="form-select" id="brand_uuid" name="brand_uuid">
                                    <option value="">None</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->uuid }}" @selected(old('brand_uuid', $item->brand?->uuid) === $brand->uuid)>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="collection_uuid">Collection</label>
                                <select class="form-select" id="collection_uuid" name="collection_uuid">
                                    <option value="">None</option>
                                    @foreach ($collections as $collection)
                                        <option value="{{ $collection->uuid }}" @selected(old('collection_uuid', $item->collection?->uuid) === $collection->uuid)>{{ $collection->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="design_uuid">Design</label>
                                <select class="form-select" id="design_uuid" name="design_uuid">
                                    <option value="">None</option>
                                    @foreach ($designs as $design)
                                        <option value="{{ $design->uuid }}" @selected(old('design_uuid', $item->design?->uuid) === $design->uuid)>{{ $design->design_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="hallmark">Hallmark</label>
                                <input class="form-control" id="hallmark" name="hallmark" value="{{ old('hallmark', $item->hallmark) }}" maxlength="40" placeholder="BIS 916">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="certificate_number">Certificate</label>
                                <input class="form-control" id="certificate_number" name="certificate_number" value="{{ old('certificate_number', $item->certificate_number) }}" maxlength="40" placeholder="IGI / GIA number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="selling_price">Tag price ₹</label>
                                <input class="form-control" id="selling_price" name="selling_price" value="{{ $field('selling_price', $item->selling_price) }}" inputmode="decimal" placeholder="Optional">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="mrp">MRP ₹</label>
                                <input class="form-control" id="mrp" name="mrp" value="{{ $field('mrp', $item->mrp) }}" inputmode="decimal" placeholder="Optional">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="image">Photo</label>
                                <input class="form-control" id="image" name="image" type="file" accept="image/*">
                                @if ($item->image_path)
                                    <div class="form-text">A photo is saved. Choose a new one only to replace it.</div>
                                @endif
                            </div>
                        </div>
                        <div class="form-text mt-2">Tag price and MRP are only for your reference. Bills are always priced from the day’s rate.</div>
                    </div>
                </details>
            </div>

            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Price today</div>
                    <div class="card-body bill-sums">
                        <div class="weigh-quote is-empty" id="price-empty">Enter the gross weight</div>
                        <div id="price-sums" class="d-none">
                            <div class="bill-block mt-0">
                                <div class="bill-row bill-row-muted"><span>Net weight</span><span id="sum-net">0.000 g</span></div>
                                <div class="bill-row"><span id="sum-rate-label">Metal</span><span id="sum-metal">₹0.00</span></div>
                                <div class="bill-row d-none" id="sum-wastage-row"><span>Wastage</span><span id="sum-wastage">₹0.00</span></div>
                                <div class="bill-row d-none" id="sum-making-row"><span>Making</span><span id="sum-making">₹0.00</span></div>
                                <div class="bill-row d-none" id="sum-stone-row"><span>Stones</span><span id="sum-stone">₹0.00</span></div>
                            </div>
                            <div class="bill-grand"><span>Price before GST</span><strong id="sum-price">₹0.00</strong></div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2" id="price-note"></p>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Your cost</div>
                    <div class="card-body">
                        <label class="form-label" for="cost_price">Cost price ₹</label>
                        <input class="form-control" id="cost_price" name="cost_price" value="{{ $field('cost_price', $item->cost_price) }}" inputmode="decimal" placeholder="What the piece cost you">
                        <div class="bill-row mt-2 d-none" id="margin-row"><span>Margin at today’s price</span><strong id="margin">₹0.00</strong></div>
                        <label class="form-label mt-3" for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Supplier, tag details, anything to remember">{{ old('notes', $item->notes) }}</textarea>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check-lg"></i> {{ $item->exists ? 'Save changes' : 'Save piece' }}</button>
                @if (! $item->exists && ! ($fromOldGold ?? null))
                    <button class="btn btn-outline-secondary w-100 mt-2" type="submit" name="next" value="another"><i class="bi bi-plus-lg"></i> Save and add another</button>
                @endif
            </div>
        </div>
    </form>
    <script type="application/json" id="piece-config">{!! json_encode([
        'rates' => $rates->map(fn ($rate) => [
            'metal_type_id' => $rate->metal_type_id,
            'purity_id' => $rate->purity_id,
            'branch_id' => $rate->branch_id,
            'rate_per_gram' => (string) $rate->rate_per_gram,
        ])->values(),
        'autoName' => ! $item->exists && blank(old('name', $item->name)),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script>
        (() => {
            const config = JSON.parse(document.getElementById('piece-config').textContent);
            const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
            const form = document.getElementById('piece-form');
            const stoneList = document.getElementById('item-stone-list');
            const nameInput = document.getElementById('name');
            const category = document.getElementById('category_uuid');
            const metal = document.getElementById('metal_uuid');
            const purity = document.getElementById('purity_uuid');
            const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
            const num = (id) => Number(document.getElementById(id).value || 0);
            const selected = (field) => field.options[field.selectedIndex];
            let autoName = config.autoName;

            function syncPurities() {
                const metalId = selected(metal).dataset.id;
                Array.from(purity.options).forEach((option) => {
                    const show = option.dataset.metal === metalId;
                    option.hidden = !show;
                    option.disabled = !show;
                });
                if (selected(purity)?.disabled) {
                    const options = Array.from(purity.options).filter((option) => !option.disabled);
                    const preferred = options.find((option) => option.text === '22K') || options[0];
                    if (preferred) preferred.selected = true;
                }
            }

            function suggestName() {
                if (!autoName) return;
                const option = selected(category);
                const metalName = selected(metal).text;
                nameInput.value = option && option.value ? (metalName + ' ' + option.dataset.name).trim() : '';
            }

            function syncChips() {
                document.querySelectorAll('#category-chips button').forEach((chip) => {
                    chip.classList.toggle('active', chip.dataset.category === category.value);
                });
            }

            function syncUnit(kind) {
                const select = document.getElementById(kind + '_method_uuid');
                const option = selected(select);
                const value = document.getElementById(kind + '_value');
                document.getElementById(kind + '-unit').textContent = option.dataset.unit || '—';
                value.disabled = !option.value;
                if (!option.value) value.value = '';
            }

            function syncStoneRow(row) {
                const unit = row.querySelector('.stone-unit').value;
                const rate = row.querySelector('.stone-rate');
                const value = row.querySelector('.stone-value');
                const fixed = unit === 'fixed';
                rate.disabled = fixed;
                if (fixed) rate.value = '';
                value.readOnly = !fixed;
                if (!fixed) {
                    const weight = Number(row.querySelector('.stone-weight').value || 0);
                    const amount = weight * (unit === 'carat' ? 5 : 1) * Number(rate.value || 0);
                    value.value = amount > 0 ? round2(amount).toFixed(2) : '';
                }
            }

            function bindStoneRow(row) {
                row.addEventListener('input', () => syncStoneRow(row));
                row.addEventListener('change', () => syncStoneRow(row));
                row.querySelector('.bill-remove').addEventListener('click', () => {
                    row.remove();
                    renumberStones();
                    render();
                });
                syncStoneRow(row);
            }

            function renumberStones() {
                const rows = stoneList.querySelectorAll('.stone-row');
                rows.forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace(/stones\[\d+\]/, 'stones[' + index + ']');
                    });
                });
                document.getElementById('stone-empty').hidden = rows.length > 0;
            }

            function addStoneRow() {
                const row = document.createElement('div');
                row.className = 'stone-row';
                [
                    ['Name', 'name', 'Diamond', 'text'],
                    ['Weight g', 'weight', '0.000', 'decimal'],
                    ['Per', 'rate_unit', '', 'unit'],
                    ['Rate', 'rate', '0', 'decimal'],
                    ['Value ₹', 'value', '0', 'decimal'],
                ].forEach(([labelText, key, placeholder, mode]) => {
                    const field = document.createElement('div');
                    const label = document.createElement('label');
                    label.textContent = labelText;
                    let input;
                    if (mode === 'unit') {
                        input = document.createElement('select');
                        input.className = 'form-select stone-unit';
                        [['gram', 'Gram'], ['carat', 'Carat'], ['fixed', 'Fixed']].forEach(([value, text]) => input.add(new Option(text, value)));
                    } else {
                        input = document.createElement('input');
                        input.className = 'form-control' + (key === 'name' ? '' : ' stone-' + key);
                        input.placeholder = placeholder;
                        if (mode === 'decimal') input.inputMode = 'decimal';
                        if (key === 'name') {
                            input.setAttribute('list', 'stone-type-names');
                            input.autocomplete = 'off';
                        }
                    }
                    input.name = 'stones[0][' + key + ']';
                    field.append(label, input);
                    row.appendChild(field);
                });
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'bill-remove';
                remove.setAttribute('aria-label', 'Remove stone');
                remove.textContent = '×';
                row.appendChild(remove);
                stoneList.appendChild(row);
                renumberStones();
                bindStoneRow(row);
                row.querySelector('input').focus();
            }

            function currentRate() {
                const metalId = selected(metal).dataset.id;
                const purityId = selected(purity).dataset.id;
                const branchId = selected(document.getElementById('location_uuid'))?.dataset.branch;
                const rows = config.rates.filter((rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId));
                return rows.find((rate) => String(rate.branch_id) === String(branchId)) || rows.find((rate) => rate.branch_id === null) || null;
            }

            function charge(kind, net, rateValue) {
                const option = selected(document.getElementById(kind + '_method_uuid'));
                const code = option.value ? option.dataset.code : '';
                const value = option.value ? num(kind + '_value') : 0;
                if (!code || value <= 0) return 0;
                if (code === 'per_gram') return net * value;
                if (code === 'percentage') return net * rateValue * value / 100;
                return value;
            }

            function show(id, amount) {
                document.getElementById(id + '-row').classList.toggle('d-none', amount <= 0);
                document.getElementById(id).textContent = money.format(amount);
            }

            function render() {
                syncPurities();
                syncChips();
                syncUnit('making');
                syncUnit('wastage');
                const gross = num('gross_weight');
                let stoneWeight = 0;
                let stoneValue = 0;
                stoneList.querySelectorAll('.stone-row').forEach((row) => {
                    stoneWeight += Number(row.querySelector('.stone-weight').value || 0);
                    stoneValue += Number(row.querySelector('.stone-value').value || 0);
                });
                const net = gross - stoneWeight - num('other_weight');
                const netField = document.getElementById('net-weight');
                netField.value = gross > 0 ? net.toFixed(3) : '';
                netField.classList.toggle('is-invalid', gross > 0 && net <= 0);

                const empty = document.getElementById('price-empty');
                const sums = document.getElementById('price-sums');
                const note = document.getElementById('price-note');
                const marginRow = document.getElementById('margin-row');
                if (gross <= 0 || net <= 0) {
                    empty.textContent = gross > 0 ? 'Stones and other weight are more than the gross' : 'Enter the gross weight';
                    empty.classList.remove('d-none');
                    sums.classList.add('d-none');
                    marginRow.classList.add('d-none');
                    note.textContent = '';
                    return;
                }
                const rate = currentRate();
                const label = selected(metal).text + ' ' + selected(purity).text;
                if (!rate) {
                    empty.textContent = 'No ' + label + ' rate saved yet';
                    empty.classList.remove('d-none');
                    sums.classList.add('d-none');
                    marginRow.classList.add('d-none');
                    note.textContent = 'Add today’s rate in Metal rates to see the price. The piece can still be saved.';
                    return;
                }
                const rateValue = Number(rate.rate_per_gram);
                const metalAmount = round2(net * rateValue);
                const wastage = round2(charge('wastage', net, rateValue));
                const making = round2(charge('making', net, rateValue));
                const stones = round2(stoneValue);
                const price = round2(metalAmount + wastage + making + stones);
                empty.classList.add('d-none');
                sums.classList.remove('d-none');
                document.getElementById('sum-net').textContent = net.toFixed(3) + ' g';
                document.getElementById('sum-rate-label').textContent = 'Metal at ' + money.format(rateValue) + ' / g';
                document.getElementById('sum-metal').textContent = money.format(metalAmount);
                show('sum-wastage', wastage);
                show('sum-making', making);
                show('sum-stone', stones);
                document.getElementById('sum-price').textContent = money.format(price);
                note.textContent = 'Today’s ' + label + ' rate. GST is added on the bill.';
                const cost = num('cost_price');
                marginRow.classList.toggle('d-none', cost <= 0);
                const margin = round2(price - cost);
                const marginField = document.getElementById('margin');
                marginField.textContent = money.format(margin) + (cost > 0 ? ' (' + (margin / cost * 100).toFixed(1) + '%)' : '');
                marginField.classList.toggle('text-danger', margin < 0);
            }

            nameInput.addEventListener('input', () => { autoName = nameInput.value === ''; });
            category.addEventListener('change', suggestName);
            metal.addEventListener('change', () => { syncPurities(); suggestName(); });
            document.querySelectorAll('#category-chips button').forEach((chip) => {
                chip.addEventListener('click', () => {
                    category.value = chip.dataset.category;
                    suggestName();
                    render();
                    if (!autoName || nameInput.value === '') nameInput.focus();
                    else document.getElementById('gross_weight').focus();
                });
            });
            document.getElementById('item-stone-add').addEventListener('click', () => { addStoneRow(); render(); });
            stoneList.querySelectorAll('.stone-row').forEach(bindStoneRow);
            form.addEventListener('input', render);
            form.addEventListener('change', render);
            render();
        })();
    </script>
@endpush
