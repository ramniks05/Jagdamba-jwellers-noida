@extends('layouts.app')

@section('title', 'Take repair')

@section('content')
    <h1 class="page-title h3 mb-2">Take repair</h1>
    <p class="text-secondary">Choose the customer, the piece, and the weight you received. The estimate is shown on the job slip before you save. The charge is collected when the piece is delivered.</p>
    <form method="POST" action="{{ route('repairs.store') }}" id="repair-form">
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
                        <div class="text-danger small mt-2 d-none" id="customer-error">Choose a customer before saving.</div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-white">Piece received</div>
                    <div class="card-body">
                        <p class="text-secondary">This is the customer's own jewellery. Pick the type, then type what is wrong and the weight from the scale.</p>
                        <div class="bill-products mb-3" id="repair-products">
                            @foreach ($categories as $category)
                                <button type="button" data-name="{{ $category->name }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="description">Piece</label>
                                <input class="form-control" id="description" name="description" value="{{ old('description') }}" placeholder="Chain" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="problem">What needs repair</label>
                                <input class="form-control" id="problem" name="problem" value="{{ old('problem') }}" placeholder="Broken clasp" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="weight">Weight received (g)</label>
                                <input class="form-control bill-weight" id="weight" name="gross_weight" value="{{ old('gross_weight') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="estimate">Estimate</label>
                                <input class="form-control" id="estimate" name="estimated_cost" value="{{ old('estimated_cost', '0') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="expected">Expected on</label>
                                <input class="form-control" id="expected" type="date" name="expected_on" value="{{ old('expected_on') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="technician">Karigar</label>
                                <input class="form-control" id="technician" name="technician" value="{{ old('technician') }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="shop-piece">Shop piece, only if it is our stock</label>
                                <select class="form-select" id="shop-piece" name="item_uuid">
                                    <option value="">Customer's own jewellery</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->uuid }}" data-name="{{ $item->name }}" @selected(old('item_uuid') === $item->uuid)>{{ $item->item_code }} · {{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Notes</label>
                                <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}" placeholder="Stones, polish, size change">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">Job slip</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3" id="slip-lines"></ul>
                        <p class="fw-semibold mb-0" id="slip-note"></p>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-tools"></i> Save repair</button>
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
                    <p class="text-secondary">The customer code is assigned when you save. Search by the mobile number next time.</p>
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
                    <button class="btn btn-primary" id="save-customer" type="button">Save and use on this repair</button>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="repair-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
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
    <script>
        const repair = JSON.parse(document.getElementById('repair-config').textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
        let chosen = null;

        function chooseCustomer(customer) {
            chosen = customer;
            document.getElementById('customer-uuid').value = customer.uuid;
            document.getElementById('customer-error').classList.add('d-none');
            document.getElementById('customer-results').classList.add('d-none');
            const box = document.getElementById('customer-chosen');
            box.classList.remove('d-none');
            box.textContent = customer.name + (customer.mobile ? ' · ' + customer.mobile : '') + (customer.code ? ' · ' + customer.code : '');
            renderSlip();
        }

        function renderCustomers() {
            const query = document.getElementById('customer-search').value.trim().toLowerCase();
            const box = document.getElementById('customer-results');
            if (query.length < 1) {
                box.classList.add('d-none');
                box.innerHTML = '';
                return;
            }
            const rows = repair.customers.filter((customer) => [customer.name, customer.mobile, customer.code].join(' ').toLowerCase().includes(query)).slice(0, 8);
            box.classList.remove('d-none');
            box.innerHTML = rows.length
                ? rows.map((customer) => '<button type="button" data-uuid="' + customer.uuid + '">' + customer.name + (customer.mobile ? ' · ' + customer.mobile : '') + '</button>').join('')
                : '<div class="p-2 text-secondary">No customer found. Add one with New customer.</div>';
        }

        function renderSlip() {
            const piece = document.getElementById('description').value.trim();
            const problem = document.getElementById('problem').value.trim();
            const weight = Number(document.getElementById('weight').value || 0);
            const estimate = Number(document.getElementById('estimate').value || 0);
            const expected = document.getElementById('expected').value;
            const technician = document.getElementById('technician').value.trim();
            const notes = document.getElementById('notes').value.trim();
            const rows = [];
            rows.push(['Customer', chosen ? chosen.name : 'Choose a customer']);
            rows.push(['Piece', piece || '—']);
            rows.push(['Repair', problem || '—']);
            rows.push(['Weight received', weight > 0 ? weight.toFixed(3) + ' g' : '—']);
            rows.push(['Estimate', estimate]);
            if (expected) rows.push(['Expected', expected]);
            if (technician) rows.push(['Karigar', technician]);
            if (notes) rows.push(['Notes', notes]);
            document.getElementById('slip-lines').innerHTML = rows.map((row) => {
                const value = typeof row[1] === 'number' ? money.format(row[1]) : row[1];
                const strong = row[0] === 'Estimate' ? ' fw-semibold' : '';
                return '<li class="list-group-item d-flex justify-content-between px-0' + strong + '"><span>' + row[0] + '</span><span>' + value + '</span></li>';
            }).join('');
            const note = document.getElementById('slip-note');
            if (!chosen) {
                note.textContent = 'Search the customer by mobile number or name.';
            } else if (weight <= 0) {
                note.textContent = 'Enter the weight of the jewellery you are taking in.';
            } else if (chosen.walkin) {
                note.textContent = 'A walk-in repair must be paid in full when it is delivered.';
            } else {
                note.textContent = 'The estimate is not collected now. The charge is taken on delivery.';
            }
        }

        document.getElementById('customer-search').addEventListener('input', renderCustomers);
        document.getElementById('customer-results').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const customer = repair.customers.find((row) => row.uuid === button.dataset.uuid);
            if (customer) chooseCustomer(customer);
        });
        document.getElementById('repair-products').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            document.querySelectorAll('#repair-products button').forEach((row) => row.classList.remove('active'));
            button.classList.add('active');
            document.getElementById('description').value = button.dataset.name;
            document.getElementById('problem').focus();
            renderSlip();
        });
        document.getElementById('shop-piece').addEventListener('change', () => {
            const option = document.getElementById('shop-piece').selectedOptions[0];
            if (option && option.value && document.getElementById('description').value.trim() === '') {
                document.getElementById('description').value = option.dataset.name || '';
            }
            renderSlip();
        });
        document.getElementById('repair-form').addEventListener('input', renderSlip);
        document.getElementById('repair-form').addEventListener('submit', (event) => {
            if (!document.getElementById('customer-uuid').value) {
                event.preventDefault();
                document.getElementById('customer-error').classList.remove('d-none');
                document.getElementById('customer-search').focus();
            }
        });
        document.getElementById('save-customer').addEventListener('click', async () => {
            const error = document.getElementById('customer-modal-error');
            error.classList.add('d-none');
            const response = await fetch(repair.customerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': repair.csrf,
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
            repair.customers.push(payload);
            chooseCustomer(payload);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customer-modal')).hide();
            document.getElementById('new-customer-name').value = '';
            document.getElementById('new-customer-mobile').value = '';
        });
        const preset = repair.customers.find((customer) => customer.uuid === document.getElementById('customer-uuid').value);
        if (preset) chooseCustomer(preset);
        renderSlip();
    </script>
@endpush
