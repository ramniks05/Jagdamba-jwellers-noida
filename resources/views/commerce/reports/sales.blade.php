@extends('layouts.app')

@section('title', 'Sales report')

@section('content')
    @include('commerce.reports.partials.head', ['title' => 'Sales report'])
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.sales') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label" for="report-search">Search</label>
                <input class="form-control" id="report-search" name="search" value="{{ $filters['search'] }}" placeholder="Invoice, customer, mobile or code">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="report-from">From</label>
                <input class="form-control" id="report-from" type="date" name="from" value="{{ $filters['from'] }}">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="report-to">To</label>
                <input class="form-control" id="report-to" type="date" name="to" value="{{ $filters['to'] }}">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="report-payment">Payment</label>
                <select class="form-select" id="report-payment" name="payment">
                    <option value="all">All bills</option>
                    <option value="due" @selected($filters['payment'] === 'due')>Balance due</option>
                    <option value="paid" @selected($filters['payment'] === 'paid')>Fully paid</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($filters['search'] !== '' || $filters['payment'] !== 'all')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.sales', ['from' => $filters['from'], 'to' => $filters['to']]) }}">Clear</a>
                @endif
            </div>
        </div>
        <div class="report-ranges">
            @foreach ($ranges as $label => [$rangeFrom, $rangeTo])
                <a class="btn btn-sm {{ $filters['from'] === $rangeFrom && $filters['to'] === $rangeTo ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reports.sales', ['from' => $rangeFrom, 'to' => $rangeTo, 'search' => $filters['search'] ?: null, 'payment' => $filters['payment'] !== 'all' ? $filters['payment'] : null, 'per_page' => $perPage]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>
    <div class="row g-3 mb-3 no-print">
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Bills</div><div class="fw-semibold fs-5">{{ $billCount }}</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Billed</div><div class="fw-semibold fs-5">{{ $total }}</div><div class="small text-secondary">GST {{ $tax }}</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Received</div><div class="fw-semibold fs-5">{{ $paid }}</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Balance due</div><div class="fw-semibold fs-5">{{ $due }}</div></div></div>
        </div>
    </div>
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Sales report</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ $company->formattedAddress() }}</p>
            </div>
            <div class="text-end">
                <div>{{ \Illuminate\Support\Carbon::parse($filters['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('d M Y') }}</div>
                <div>{{ $billCount }} {{ $billCount === 1 ? 'bill' : 'bills' }}@if ($filters['payment'] === 'due') · balance due @elseif ($filters['payment'] === 'paid') · fully paid @endif</div>
            </div>
        </header>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>When</th>
                        <th class="num">GST</th>
                        <th class="num">Total</th>
                        <th class="num">Paid</th>
                        <th class="num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
                            <td>{{ $sale->customer?->name }}@if ($sale->customer?->mobile)<div class="small text-secondary">{{ $sale->customer->mobile }}</div>@endif</td>
                            <td class="text-nowrap">{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td class="num">{{ $money(number_format((float) $sale->tax_amount + (float) $sale->making_tax_amount, 2, '.', '')) }}</td>
                            <td class="num">{{ $money((string) $sale->total) }}</td>
                            <td class="num">{{ $money((string) $sale->paid_amount) }}</td>
                            <td class="num {{ (float) $sale->balanceDue() > 0 ? 'is-late' : '' }}">{{ $money($sale->balanceDue()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No bills for this filter.</td></tr>
                    @endforelse
                </tbody>
                @if ($billCount > 0)
                    <tfoot>
                        <tr>
                            <td colspan="3">Total · {{ $billCount }} {{ $billCount === 1 ? 'bill' : 'bills' }}</td>
                            <td class="num">{{ $tax }}</td>
                            <td class="num">{{ $total }}</td>
                            <td class="num">{{ $paid }}</td>
                            <td class="num">{{ $due }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </article>
    @include('commerce.reports.partials.pager', ['rows' => $sales, 'noun' => 'bills'])
@endsection
