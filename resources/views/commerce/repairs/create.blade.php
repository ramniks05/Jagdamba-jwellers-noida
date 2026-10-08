@extends('layouts.app')

@section('title', 'Take repair')

@section('content')
    <h1 class="page-title h3 mb-1">Take repair</h1>
    <p class="text-secondary mb-3">Take in the customer’s jewellery for repair. Nothing is charged now. The charge is collected when the piece is delivered.</p>
    <form method="POST" action="{{ route('repairs.store') }}" id="repair-form" autocomplete="off">
        @csrf
        <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
        <div class="row g-3">
            <div class="col-lg-7">
                @include('commerce.partials.customer-picker', ['addLabel' => 'Add to repair'])
                <div class="card mb-3">
                    <div class="card-header bg-white">Piece received</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Piece</div>
                        <div class="bill-products" id="repair-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label" for="description">Name</label>
                                <input class="form-control" id="description" name="description" value="{{ old('description') }}" placeholder="Chain" maxlength="160" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="weight">Weight received</label>
                                <div class="weight-input">
                                    <input class="form-control bill-weight" id="weight" name="gross_weight" value="{{ old('gross_weight') }}" inputmode="decimal" placeholder="0.000" required>
                                    <span>g</span>
                                </div>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Repair</div>
                            <label class="form-label" for="problem">What needs repair</label>
                            <input class="form-control" id="problem" name="problem" value="{{ old('problem') }}" placeholder="Broken clasp, polish, size change" maxlength="500" required>
                            <div class="bill-products mt-2" id="repair-fixes">
                                @foreach (['Broken clasp', 'Solder joint', 'Polish', 'Size change', 'Stone setting', 'Rhodium', 'Hook / lock', 'Cleaning'] as $fix)
                                    <button type="button" data-fix="{{ $fix }}">{{ $fix }}</button>
                                @endforeach
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-4">
                                    <label class="form-label" for="estimate">Estimate ₹</label>
                                    <input class="form-control" id="estimate" name="estimated_cost" value="{{ old('estimated_cost', '0') }}" inputmode="decimal">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="expected">Ready by</label>
                                    <input class="form-control" id="expected" type="date" name="expected_on" value="{{ old('expected_on') }}" min="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="technician">Karigar</label>
                                    <input class="form-control" id="technician" name="technician" value="{{ old('technician') }}" maxlength="80">
                                </div>
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">More</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="shop-piece">Our stock piece?</label>
                                    <select class="form-select" id="shop-piece" name="item_uuid">
                                        <option value="">No, customer’s own jewellery</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item->uuid }}" data-name="{{ $item->name }}" @selected(old('item_uuid') === $item->uuid)>{{ $item->item_code }} · {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="notes">Notes</label>
                                    <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}" placeholder="Stones count, marks, colour" maxlength="1000">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Job slip</div>
                    <div class="card-body bill-sums">
                        <div class="bill-block mt-0 border-0 pt-0" id="slip-lines"></div>
                        <div class="bill-grand"><span>Estimate</span><strong id="slip-estimate">₹0.00</strong></div>
                        <p class="text-secondary small mb-0" id="slip-note"></p>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-tools"></i> Save repair</button>
            </div>
        </div>
    </form>

    @include('commerce.partials.customer-modals', ['document' => 'repair', 'note' => 'The customer code is assigned when you save. Search by the mobile number next time.'])

    <script type="application/json" id="repair-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
        'customers' => $customers->map(fn ($customer) => [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'code' => $customer->code,
            'mobile' => $customer->mobile,
            'walkin' => (bool) $customer->is_system,
        ])->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
    <script>
        const repair = JSON.parse(document.getElementById('repair-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
        const $ = (id) => document.getElementById(id);
        let chosen = null;

        function slipRow(label, value) {
            const row = document.createElement('div');
            row.className = 'bill-row';
            const name = document.createElement('span');
            name.textContent = label;
            const text = document.createElement('span');
            text.className = 'text-end';
            text.textContent = value;
            row.append(name, text);
            return row;
        }

        function renderSlip() {
            const weight = Number($('weight').value || 0);
            const expected = $('expected').value;
            const shopPiece = $('shop-piece').selectedOptions[0];
            const rows = [
                ['Customer', chosen ? chosen.name : '—'],
                ['Piece', $('description').value.trim() || '—'],
                ['Repair', $('problem').value.trim() || '—'],
                ['Weight received', weight > 0 ? weight.toFixed(3) + ' g' : '—'],
            ];
            if (expected) rows.push(['Ready by', expected.split('-').reverse().join('-')]);
            if ($('technician').value.trim()) rows.push(['Karigar', $('technician').value.trim()]);
            if (shopPiece && shopPiece.value) rows.push(['Stock piece', shopPiece.text]);
            if ($('notes').value.trim()) rows.push(['Notes', $('notes').value.trim()]);
            $('slip-lines').replaceChildren(...rows.map(([label, value]) => slipRow(label, value)));
            $('slip-estimate').textContent = money.format(Number($('estimate').value || 0));
            $('slip-note').textContent = !chosen
                ? 'Find the customer by mobile number or name.'
                : (weight <= 0
                    ? 'Weigh the jewellery and enter the weight you are taking in.'
                    : (chosen.walkin
                        ? 'A walk-in repair is paid in full when it is delivered.'
                        : 'Nothing is collected now. The charge is taken on delivery.'));
        }

        const picker = customerPicker({
            customers: repair.customers,
            createUrl: repair.customerUrl,
            showUrl: repair.customerShowUrl,
            csrf: repair.csrf,
            addLabel: 'Add to repair',
            chipLabel: 'Repair for',
            onChange: (row) => {
                chosen = row;
                renderSlip();
            },
        });
        $('repair-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#repair-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            $('description').value = button.dataset.name;
            $('weight').focus();
            renderSlip();
        });
        $('repair-fixes').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const field = $('problem');
            const parts = field.value.split(',').map((part) => part.trim()).filter(Boolean);
            const index = parts.indexOf(button.dataset.fix);
            if (index === -1) parts.push(button.dataset.fix); else parts.splice(index, 1);
            field.value = parts.join(', ');
            button.classList.toggle('active', index === -1);
            renderSlip();
        });
        $('shop-piece').addEventListener('change', () => {
            const option = $('shop-piece').selectedOptions[0];
            if (option && option.value && $('description').value.trim() === '') {
                $('description').value = option.dataset.name || '';
            }
            renderSlip();
        });
        $('repair-form').addEventListener('input', renderSlip);
        $('repair-form').addEventListener('submit', (event) => {
            if (!picker.ensure()) event.preventDefault();
        });
        document.querySelectorAll('#repair-fixes button').forEach((button) => {
            button.classList.toggle('active', $('problem').value.split(',').map((part) => part.trim()).includes(button.dataset.fix));
        });
        renderSlip();
    </script>
@endpush
