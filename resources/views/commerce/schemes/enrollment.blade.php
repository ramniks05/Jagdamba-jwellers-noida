@extends('layouts.app')

@section('title', $enrollment->number)

@php
    $remaining = $enrollment->installments->count() - $paidCount;
    $open = $enrollment->status === 'active';
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
        <div>
            <h1 class="page-title h3 mb-1">{{ $enrollment->number }}</h1>
            <div class="text-secondary">{{ $enrollment->customer?->name }} · {{ $enrollment->scheme?->name }} · {{ $open ? 'Open' : 'Matured' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print passbook</button>
        </div>
    </div>

    @if ($open && $nextInstallment)
        @can('create', App\Models\GoldScheme::class)
            <form class="card mb-3 no-print" method="POST" action="{{ route('enrollments.installments.store', $enrollment) }}">
                @csrf
                <div class="card-header bg-white">Collect installment {{ $paidCount + 1 }} of {{ $enrollment->installments->count() }}</div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control" id="amount" name="amount" value="{{ old('amount', $enrollment->scheme?->installment_mode === 'fixed' ? $enrollment->scheme?->monthly_amount : $nextInstallment->amount) }}" required>
                        <div class="form-text">Due {{ $nextInstallment->due_on->format('d M Y') }}@if ($enrollment->scheme?->installment_mode === 'fixed') · this scheme collects {{ $money((string) $enrollment->scheme->monthly_amount) }} each month @endif</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="method">Method</label>
                        <select class="form-select" id="method" name="method">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="reference">Reference</label>
                        <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cash"></i> Save installment</button>
                    </div>
                    <div class="col-12 text-secondary">Collected {{ $money($collected) }}. {{ $remaining }} {{ $remaining === 1 ? 'month' : 'months' }} still to pay.@if ($maturity) On maturity the customer gets {{ $money($maturity) }}.@endif</div>
                </div>
            </form>
        @endcan
    @endif

    @if ($open && ! $nextInstallment)
        @can('update', $enrollment)
            <form class="card mb-3 no-print" method="POST" action="{{ route('enrollments.mature', $enrollment) }}">
                @csrf
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="fw-semibold">Every installment is paid.</div>
                        <div class="text-secondary">Maturing credits {{ $closing ? $money($closing) : $money($collected) }} to the customer. They can use it on a bill.</div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Mature scheme</button>
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
                        <td class="num">{{ $money((string) $installment->amount) }}</td>
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
                <tr><td>Paid</td><td>{{ $paidCount }} of {{ $enrollment->installments->count() }}</td></tr>
                <tr><td>Collected</td><td>{{ $money($collected) }}</td></tr>
                <tr class="invoice-grand">
                    <td>Customer gets</td>
                    <td>{{ $enrollment->status === 'matured' ? $money((string) $enrollment->maturity_amount) : ($closing ? $money($closing) : ($maturity ? $money($maturity) : 'At the end')) }}</td>
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
                @if ($company->signature_path)
                    <img src="{{ '/storage/'.$company->signature_path }}" alt="Authorised signature">
                @else
                    <div class="invoice-sign-space"></div>
                @endif
                <span>Authorised signatory</span>
            </div>
        </footer>
    </article>
@endsection
