@extends('layouts.app')

@section('title', $scheme->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
        <div>
            <h1 class="page-title h3 mb-1">{{ $scheme->name }}</h1>
            <p class="text-secondary mb-0">{{ $scheme->code }} · {{ $scheme->duration_months }} months · {{ $bonuses[$scheme->bonus_type] ?? $scheme->bonus_type }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print scheme</button>
        </div>
    </div>
    <article class="invoice-sheet">
        @include('commerce.partials.shop-document-head', ['kicker' => 'Scheme details'])
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Scheme</th>
                    <th>What the customer gets</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $scheme->name }}</strong>
                        <div><span>Months</span><span>{{ $scheme->duration_months }}</span></div>
                        <div><span>Each month</span><span>{{ $scheme->monthly_amount !== null ? $money((string) $scheme->monthly_amount) : 'Any amount' }}</span></div>
                        <div><span>Bonus</span><span>{{ $bonuses[$scheme->bonus_type] ?? $scheme->bonus_type }}</span></div>
                    </td>
                    <td>
                        <div><span>Customer pays</span><strong>{{ $payable !== null ? $money($payable) : 'Depends on each month' }}</strong></div>
                        <div><span>Customer gets</span><strong>{{ $maturity !== null ? $money($maturity) : 'After every month is paid' }}</strong></div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="invoice-bottom">
            <div class="invoice-words">
                <div class="invoice-kicker">Customer gets</div>
                <p>{{ $maturityWords ?? 'The closing amount is known after every month is paid.' }}</p>
            </div>
            <table class="invoice-totals">
                <tr><td>Months</td><td>{{ $scheme->duration_months }}</td></tr>
                <tr><td>Customer pays</td><td>{{ $payable !== null ? $money($payable) : 'Each month' }}</td></tr>
                <tr class="invoice-grand"><td>Customer gets</td><td>{{ $maturity !== null ? $money($maturity) : 'At the end' }}</td></tr>
            </table>
        </div>
        <footer class="invoice-foot">
            <div class="invoice-terms">
                <p>The customer pays every month. The maturity amount is credited only when every month is paid, and it can be used on a bill. This sheet can be given to the customer before they join.</p>
                @if ($footer !== '')
                    <p class="thanks">{{ $footer }}</p>
                @endif
            </div>
            <div class="invoice-sign">
                <div>For {{ $company->displayName() }}</div>
                <img src="{{ $company->brandSignatureUrl() }}" alt="Authorised signature">
                <span>Authorised signatory</span>
            </div>
        </footer>
    </article>
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Monthly</div>
                <div class="h4 mb-0">{{ $scheme->monthly_amount !== null ? $money((string) $scheme->monthly_amount) : 'Any amount' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Customer pays</div>
                <div class="h4 mb-0">{{ $payable !== null ? $money($payable) : 'Depends on each month' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Customer gets at the end</div>
                <div class="h4 mb-0">{{ $maturity !== null ? $money($maturity) : 'After every month is paid' }}</div>
            </div></div>
        </div>
    </div>
    @can('create', App\Models\GoldScheme::class)
        @if ($scheme->is_active)
            <form class="card mb-4 no-print" method="POST" action="{{ route('schemes.enroll', $scheme) }}" id="enroll-form">
                @csrf
                <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Add a member</span>
                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#customer-modal"><i class="bi bi-person-plus"></i> New customer</button>
                </div>
                <div class="card-body">
                    <label class="form-label" for="customer-search">Search by mobile number or name</label>
                    <input class="form-control" id="customer-search" placeholder="Mobile, name, or code" autocomplete="off">
                    <div class="bill-results mt-2 d-none" id="customer-results"></div>
                    <div class="alert alert-warning mt-3 mb-3 d-none" id="customer-chosen"></div>
                    <div class="text-danger small mb-3 d-none" id="customer-error">Choose a customer. A walk-in cannot join a scheme.</div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-person-check"></i> Enroll</button>
                </div>
            </form>
        @endif
    @endcan
    <div class="card no-print">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Started</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($scheme->enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->number }}</td>
                            <td>{{ $enrollment->customer?->name }}</td>
                            <td>{{ $enrollment->customer?->mobile ?: '—' }}</td>
                            <td>{{ $enrollment->started_on?->format('d M Y') }}</td>
                            <td>{{ $enrollment->status === 'matured' ? 'Matured' : 'Open' }}</td>
                            <td class="text-end"><a href="{{ route('enrollments.show', $enrollment) }}">Passbook</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No members yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="customer-modal" tabindex="-1" aria-labelledby="customer-modal-title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="customer-modal-title">New customer</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">The customer code is assigned when you save. A walk-in cannot join a scheme.</p>
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
                    <button class="btn btn-primary" id="save-customer" type="button">Save and enroll</button>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="enroll-config">{!! json_encode([
        'csrf' => csrf_token(),
        'customerUrl' => route('customers.store'),
        'customers' => $customers->map(fn ($customer) => [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'code' => $customer->code,
            'mobile' => $customer->mobile,
        ])->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
    <script>
        const enroll = JSON.parse(document.getElementById('enroll-config').textContent);
        const form = document.getElementById('enroll-form');
        if (form && enroll) {
            function chooseCustomer(customer) {
                document.getElementById('customer-uuid').value = customer.uuid;
                document.getElementById('customer-error').classList.add('d-none');
                document.getElementById('customer-results').classList.add('d-none');
                const box = document.getElementById('customer-chosen');
                box.classList.remove('d-none');
                box.textContent = customer.name + (customer.mobile ? ' · ' + customer.mobile : '') + (customer.code ? ' · ' + customer.code : '');
            }
            document.getElementById('customer-search').addEventListener('input', () => {
                const query = document.getElementById('customer-search').value.trim().toLowerCase();
                const box = document.getElementById('customer-results');
                if (query.length < 1) {
                    box.classList.add('d-none');
                    box.innerHTML = '';
                    return;
                }
                const rows = enroll.customers.filter((customer) => [customer.name, customer.mobile, customer.code].join(' ').toLowerCase().includes(query)).slice(0, 8);
                box.classList.remove('d-none');
                box.innerHTML = rows.length
                    ? rows.map((customer) => '<button type="button" data-uuid="' + customer.uuid + '">' + customer.name + (customer.mobile ? ' · ' + customer.mobile : '') + '</button>').join('')
                    : '<div class="p-2 text-secondary">No customer found. Add one with New customer.</div>';
            });
            document.getElementById('customer-results').addEventListener('click', (event) => {
                const button = event.target.closest('button');
                if (!button) return;
                const customer = enroll.customers.find((row) => row.uuid === button.dataset.uuid);
                if (customer) chooseCustomer(customer);
            });
            form.addEventListener('submit', (event) => {
                if (!document.getElementById('customer-uuid').value) {
                    event.preventDefault();
                    document.getElementById('customer-error').classList.remove('d-none');
                    document.getElementById('customer-search').focus();
                }
            });
            document.getElementById('save-customer').addEventListener('click', async () => {
                const error = document.getElementById('customer-modal-error');
                error.classList.add('d-none');
                const response = await fetch(enroll.customerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': enroll.csrf,
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
                enroll.customers.push(payload);
                chooseCustomer(payload);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('customer-modal')).hide();
                document.getElementById('new-customer-name').value = '';
                document.getElementById('new-customer-mobile').value = '';
            });
            const preset = enroll.customers.find((customer) => customer.uuid === document.getElementById('customer-uuid').value);
            if (preset) chooseCustomer(preset);
        }
    </script>
@endpush
