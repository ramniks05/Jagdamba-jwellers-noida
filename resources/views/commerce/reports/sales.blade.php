@extends('layouts.app')

@section('title', 'Sales report')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="page-title h3 mb-0">Sales report</h1>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print report</button>
    </div>
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.sales') }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="report-search">Invoice, customer, or mobile</label>
                <input class="form-control" id="report-search" name="search" value="{{ $search }}" placeholder="Name or invoice number">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="report-from">From</label>
                <input class="form-control" id="report-from" type="date" name="from" value="{{ $from }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="report-to">To</label>
                <input class="form-control" id="report-to" type="date" name="to" value="{{ $to }}">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.sales', ['from' => now()->toDateString(), 'to' => now()->toDateString()]) }}">Today</a>
                <a class="btn btn-outline-secondary" href="{{ route('reports.sales') }}">This month</a>
            </div>
        </div>
    </form>
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Sales report</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ $company->formattedAddress() }}</p>
            </div>
            <div>
                <div>{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</div>
                <div>{{ $billCount }} bills</div>
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
                            <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
                            <td>{{ $sale->customer?->name }}</td>
                            <td>{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td class="num">{{ $money((string) $sale->tax_amount) }}</td>
                            <td class="num">{{ $money((string) $sale->total) }}</td>
                            <td class="num">{{ $money((string) $sale->paid_amount) }}</td>
                            <td class="num">{{ $money($sale->balanceDue()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No bills in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($sales->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3">Total</td>
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
@endsection
