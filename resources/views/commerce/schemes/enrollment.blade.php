@extends('layouts.app')

@section('title', $enrollment->number)

@php
    $total = $enrollment->installments->count();
    $remaining = $total - $paidCount;
    $open = $enrollment->status === 'active';
    $fixed = $enrollment->scheme?->installment_mode === 'fixed';
@endphp

@section('content')
    @php
        $daysLeft = $open && $nextInstallment ? (int) now()->startOfDay()->diffInDays($nextInstallment->due_on->copy()->startOfDay(), false) : null;
        $dueNote = match (true) {
            ! $open => 'Matured on '.$enrollment->matured_at?->timezone(config('app.timezone'))->format('d-m-Y'),
            $nextInstallment === null => 'Every month paid · ready to mature',
            $daysLeft > 1 => 'Month '.($paidCount + 1).' due '.$nextInstallment->due_on->format('d-m-Y').' · in '.$daysLeft.' days',
            $daysLeft === 1 => 'Month '.($paidCount + 1).' due tomorrow',
            $daysLeft === 0 => 'Month '.($paidCount + 1).' due today',
            default => 'Month '.($paidCount + 1).' is '.abs($daysLeft).' '.(abs($daysLeft) === 1 ? 'day' : 'days').' late',
        };
        $dueTone = $daysLeft !== null ? ($daysLeft < 0 ? 'is-late' : ($daysLeft <= 1 ? 'is-soon' : '')) : '';
        $statusClass = ! $open ? 'is-matured' : ($nextInstallment ? 'is-active' : 'is-ready');
        $statusLabel = ! $open ? 'Matured' : ($nextInstallment ? 'Paying' : 'Ready to mature');
        $lastPaid = $enrollment->installments->whereNotNull('paid_at')->last()?->paid_at?->timezone(config('app.timezone'))->format('d-m-Y');
        $steps = [
            ['Joined', $enrollment->started_on?->format('d-m-Y'), 'done'],
            ['Paying '.$paidCount.' of '.$total, $lastPaid, $paidCount > 0 ? 'done' : 'next'],
            ['Every month paid', $nextInstallment === null ? $lastPaid : null, $nextInstallment === null ? 'done' : ($paidCount > 0 ? 'next' : '')],
            ['Matured', $enrollment->matured_at?->timezone(config('app.timezone'))->format('d-m-Y'), $open ? ($nextInstallment === null ? 'next' : '') : 'done'],
        ];
        $gets = $enrollment->status === 'matured' ? (string) $enrollment->maturity_amount : ($closing ?? $maturity);
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        @if ($enrollment->scheme)
            <a href="{{ route('schemes.show', $enrollment->scheme) }}"><i class="bi bi-arrow-left"></i> {{ $enrollment->scheme->name }}</a>
        @else
            <a href="{{ route('schemes.index') }}"><i class="bi bi-arrow-left"></i> All schemes</a>
        @endif
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print passbook</button>
        </div>
    </div>

    <div class="card order-panel mb-3 no-print">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Passbook {{ $enrollment->number }} · {{ $enrollment->scheme?->name }}</div>
                    <div class="order-panel-title">
                        {{ $enrollment->customer?->name }}
                        <span class="order-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="text-secondary small">{{ $enrollment->customer?->mobile ?: 'Mobile not recorded' }} · started {{ $enrollment->started_on?->format('d-m-Y') }} · {{ $total }} months @if ($fixed) · {{ $money((string) $enrollment->scheme->monthly_amount) }} each month @endif</div>
                </div>
                <div class="order-due {{ $dueTone }}"><i class="bi bi-calendar-event"></i> {{ $dueNote }}</div>
            </div>
            <ol class="order-steps">
                @foreach ($steps as [$label, $date, $state])
                    <li class="{{ $state ? 'is-'.$state : '' }}">
                        <span class="order-step-dot"></span>
                        <strong>{{ $label }}</strong>
                        <small>{{ $date ?? ($state === 'next' ? 'Next' : '—') }}</small>
                    </li>
                @endforeach
            </ol>
            <div class="customer-stats mb-0">
                <div class="customer-stat"><div class="stat-label">Collected · {{ $paidCount }} of {{ $total }}</div><div>{{ $money($collected) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Still to pay</div><div>{{ $remaining }} {{ $remaining === 1 ? 'month' : 'months' }}@if ($fixed && $remaining > 0) · {{ $money(number_format($remaining * (float) $enrollment->scheme->monthly_amount, 2, '.', '')) }}@endif</div></div>
                <div class="customer-stat is-due"><div class="stat-label">{{ $open ? 'Customer gets at the end' : 'Credited to customer' }}</div><div>{{ $gets !== null ? $money($gets) : 'After every month is paid' }}</div></div>
            </div>
        </div>
    </div>

    @if ($open && $nextInstallment)
        @can('create', App\Models\GoldScheme::class)
            <form class="card due-pay mb-3 no-print" method="POST" action="{{ route('enrollments.installments.store', $enrollment) }}">
                @csrf
                <div class="card-body">
                    <div class="due-pay-head">
                        <div>
                            <div class="stat-label">Collect month {{ $paidCount + 1 }} of {{ $total }}</div>
                            <div class="text-secondary small">Due {{ $nextInstallment->due_on->format('d-m-Y') }}@if ($fixed) · this scheme collects {{ $money((string) $enrollment->scheme->monthly_amount) }} each month @endif</div>
                        </div>
                        @if ($fixed)
                            <div class="due-pay-amount">{{ $money((string) $enrollment->scheme->monthly_amount) }}</div>
                        @endif
                    </div>
                    <div class="due-pay-row">
                        <div>
                            <label class="form-label" for="amount">Amount ₹</label>
                            <input class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $fixed ? $enrollment->scheme?->monthly_amount : null) }}" inputmode="decimal" placeholder="Amount" @readonly($fixed) required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="method">Paid by</label>
                            <select class="form-select" id="method" name="method">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="reference">Reference</label>
                            <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" placeholder="UPI ref, cheque no.">
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-cash-coin"></i> Save installment</button>
                    </div>
                </div>
            </form>
        @endcan
    @endif

    @if ($open && ! $nextInstallment)
        @can('update', $enrollment)
            <form class="card due-pay mb-3 no-print" method="POST" action="{{ route('enrollments.mature', $enrollment) }}">
                @csrf
                <div class="card-body">
                    <div class="due-pay-head mb-0">
                        <div>
                            <div class="stat-label">Every month is paid</div>
                            <div class="text-secondary small">Maturing credits this amount to the customer’s account. They can use it on a bill.</div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="due-pay-amount">{{ $money($closing ?? $collected) }}</div>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Mature scheme</button>
                        </div>
                    </div>
                    @error('scheme')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </form>
        @endcan
    @endif

    <article class="invoice-sheet">
        @include('commerce.partials.shop-document-head', ['kicker' => 'Scheme passbook'])
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Customer</th>
                    <th>Scheme</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $enrollment->customer?->name }}</strong>
                        <div>{{ $enrollment->customer?->mobile ?: 'Mobile not recorded' }}</div>
                    </td>
                    <td>
                        <div><span>Number</span><strong>{{ $enrollment->number }}</strong></div>
                        <div><span>Scheme</span><strong>{{ $enrollment->scheme?->name }}</strong></div>
                        <div><span>Started</span>{{ $enrollment->started_on?->format('d-m-Y') }}</div>
                        <div><span>Status</span>{{ $open ? 'Open' : 'Matured' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Due</th>
                    <th class="num">Amount</th>
                    <th>Paid on</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($enrollment->installments as $installment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $installment->due_on->format('d-m-Y') }}</td>
                        <td class="num">{{ $installment->paid_at || $fixed ? $money((string) $installment->amount) : '—' }}</td>
                        <td>{{ $installment->paid_at ? $installment->paid_at->timezone(config('app.timezone'))->format('d-m-Y') : 'Due' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="invoice-bottom">
            <div class="invoice-words">
                <div class="invoice-kicker">Customer gets</div>
                <p>{{ $getsWords ?? 'The closing amount is known after every month is paid.' }}</p>
                <div>{{ $enrollment->scheme?->duration_months }} months @if ($enrollment->scheme?->monthly_amount !== null) · {{ $money((string) $enrollment->scheme->monthly_amount) }} each month @endif</div>
            </div>
            <table class="invoice-totals">
                <tr><td>Customer pays</td><td>{{ $payable !== null ? $money($payable) : 'Each month' }}</td></tr>
                <tr><td>Paid</td><td>{{ $paidCount }} of {{ $total }}</td></tr>
                <tr><td>Collected</td><td>{{ $money($collected) }}</td></tr>
                <tr class="invoice-grand">
                    <td>Customer gets</td>
                    <td>{{ $gets !== null ? $money($gets) : 'At the end' }}</td>
                </tr>
            </table>
        </div>
        <footer class="invoice-foot">
            <div class="invoice-terms">
                <p>This passbook is for the customer. The maturity amount is credited only when every month is paid, and it can be used on a bill.</p>
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
@endsection
