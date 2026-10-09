@extends('layouts.app')

@section('title', $scheme->name)

@section('content')
    @php
        $paying = $scheme->enrollments->where('status', 'active')->count();
        $collectedAll = $scheme->enrollments->reduce(fn ($sum, $row) => $sum + (float) $row->collected, 0.0);
        $canEnroll = $scheme->is_active && auth()->user()?->can('create', App\Models\GoldScheme::class);
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('schemes.index') }}"><i class="bi bi-arrow-left"></i> All schemes</a>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print scheme</button>
            @if ($canEnroll)
                <a class="btn btn-primary" href="#add-member"><i class="bi bi-person-plus"></i> Add member</a>
            @endif
        </div>
    </div>

    <div class="card order-panel mb-3 no-print">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Scheme {{ $scheme->code }} · {{ $scheme->duration_months }} months</div>
                    <div class="order-panel-title">
                        {{ $scheme->name }}
                        <span class="order-status {{ $scheme->is_active ? 'is-active' : 'is-closed' }}">{{ $scheme->is_active ? 'Open to join' : 'Closed' }}</span>
                    </div>
                    <div class="text-secondary small">{{ $scheme->installment_mode === 'fixed' ? 'Fixed monthly amount' : 'Customer chooses the amount' }} · {{ $bonuses[$scheme->bonus_type] ?? $scheme->bonus_type }}</div>
                </div>
                <div class="order-due"><i class="bi bi-people"></i> {{ $scheme->enrollments->count() }} {{ $scheme->enrollments->count() === 1 ? 'member' : 'members' }} · {{ $paying }} paying</div>
            </div>
            <div class="customer-stats mb-0 mt-3">
                <div class="customer-stat"><div class="stat-label">Each month</div><div>{{ $scheme->monthly_amount !== null ? $money((string) $scheme->monthly_amount) : 'Any amount' }}</div></div>
                <div class="customer-stat"><div class="stat-label">Customer pays</div><div>{{ $payable !== null ? $money($payable) : 'Depends on each month' }}</div></div>
                <div class="customer-stat is-due"><div class="stat-label">Customer gets at the end</div><div>{{ $maturity !== null ? $money($maturity) : 'After every month is paid' }}</div></div>
            </div>
        </div>
    </div>

    @if ($canEnroll)
        <form class="row g-3 mb-3 no-print" method="POST" action="{{ route('schemes.enroll', $scheme) }}" id="enroll-form" autocomplete="off">
            @csrf
            <input type="hidden" name="customer_uuid" id="customer-uuid" value="{{ old('customer_uuid') }}">
            <div class="col-lg-7" id="add-member">
                @include('commerce.partials.customer-picker', ['title' => 'Add a member', 'addLabel' => 'Choose', 'errorText' => 'Choose a customer. A walk-in cannot join a scheme.'])
                @error('customer_uuid')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
                @error('scheme')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-lg-5">
                <div class="card due-pay h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="stat-label">Joining today</div>
                        <div class="bill-sums">
                            <div class="bill-row"><span>Month 1 due</span><span>{{ now()->format('d-m-Y') }}</span></div>
                            <div class="bill-row"><span>Last month due</span><span>{{ now()->addMonths(max(0, $scheme->duration_months - 1))->format('d-m-Y') }}</span></div>
                            <div class="bill-row"><span>Each month</span><span>{{ $scheme->monthly_amount !== null ? $money((string) $scheme->monthly_amount) : 'Any amount' }}</span></div>
                        </div>
                        <p class="small text-secondary mb-3">The passbook opens after enrolling. Collect month 1 there.</p>
                        <button class="btn btn-primary w-100 mt-auto" type="submit"><i class="bi bi-person-check"></i> Enroll member</button>
                    </div>
                </div>
            </div>
        </form>
    @elseif (! $scheme->is_active)
        <div class="alert alert-secondary no-print">This scheme is closed to new members. Existing members can still pay and mature.</div>
    @endif

    <div class="card mb-3 no-print">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>Members</span>
            @if ($collectedAll > 0)
                <span class="small text-secondary">Collected {{ $money(number_format($collectedAll, 2, '.', '')) }}</span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Passbook</th>
                        <th>Customer</th>
                        <th>Started</th>
                        <th class="num">Paid</th>
                        <th class="num">Collected</th>
                        <th>Next due</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($scheme->enrollments as $enrollment)
                        @php
                            $nextDue = $enrollment->status === 'active' && $enrollment->next_due ? \Illuminate\Support\Carbon::parse($enrollment->next_due) : null;
                            $late = $nextDue && $nextDue->lt(today());
                            $allPaid = $enrollment->status === 'active' && $enrollment->paid_count >= $enrollment->installments_count;
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $enrollment->number }}</td>
                            <td>{{ $enrollment->customer?->name }}<div class="small text-secondary">{{ $enrollment->customer?->mobile }}</div></td>
                            <td class="text-nowrap">{{ $enrollment->started_on?->format('d-m-Y') }}</td>
                            <td class="num">{{ $enrollment->paid_count }} of {{ $enrollment->installments_count }}</td>
                            <td class="num">{{ $money(number_format((float) $enrollment->collected, 2, '.', '')) }}</td>
                            <td class="text-nowrap {{ $late ? 'is-late' : '' }}">{{ $nextDue?->format('d-m-Y') ?? '—' }}</td>
                            <td>
                                @if ($enrollment->status === 'matured')
                                    <span class="order-status is-matured">Matured</span>
                                @elseif ($allPaid)
                                    <span class="order-status is-ready">Ready to mature</span>
                                @else
                                    <span class="order-status is-active">Paying</span>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ route('enrollments.show', $enrollment) }}">Passbook</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No members yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
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

    @if ($canEnroll)
        @include('commerce.partials.customer-modals', ['document' => 'scheme', 'note' => 'The customer code is assigned when you save. A walk-in cannot join a scheme.'])
        <script type="application/json" id="enroll-config">{!! json_encode([
            'csrf' => csrf_token(),
            'customerUrl' => route('customers.store'),
            'customerShowUrl' => auth()->user()?->can('viewAny', App\Models\Customer::class) ? route('customers.show', '__customer__') : null,
            'customers' => $customers->map(fn ($customer) => [
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'code' => $customer->code,
                'mobile' => $customer->mobile,
            ])->values(),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    @endif
@endsection

@push('scripts')
    @if ($scheme->is_active && auth()->user()?->can('create', App\Models\GoldScheme::class))
        <script src="{{ asset('js/customer-picker.js') }}?v={{ filemtime(public_path('js/customer-picker.js')) }}"></script>
        <script>
            const enroll = JSON.parse(document.getElementById('enroll-config').textContent);
            const picker = customerPicker({
                customers: enroll.customers,
                createUrl: enroll.customerUrl,
                showUrl: enroll.customerShowUrl,
                csrf: enroll.csrf,
                addLabel: 'Choose',
                chipLabel: 'New member',
            });
            document.getElementById('enroll-form').addEventListener('submit', (event) => {
                if (!picker.ensure()) event.preventDefault();
            });
        </script>
    @endif
@endpush
