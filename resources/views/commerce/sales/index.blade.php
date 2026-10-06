@extends('layouts.app')

@section('title', 'Sales')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Sales</h1>
        @can('create', App\Models\Sale::class)
            <a class="btn btn-primary" href="{{ route('sales.create') }}"><i class="bi bi-plus-lg"></i> New bill</a>
        @endcan
    </div>
    <form class="filter-bar" method="GET" action="{{ route('sales.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="bill-search">Invoice, customer, or mobile</label>
                <input class="form-control" id="bill-search" name="search" value="{{ $search }}" placeholder="INV-2026 or a mobile number">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="bill-from">From</label>
                <input class="form-control" id="bill-from" type="date" name="from" value="{{ $from }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="bill-to">To</label>
                <input class="form-control" id="bill-to" type="date" name="to" value="{{ $to }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="bill-status">Payment</label>
                <select class="form-select" id="bill-status" name="status">
                    <option value="all" @selected($status === 'all')>All bills</option>
                    <option value="paid" @selected($status === 'paid')>Paid</option>
                    <option value="due" @selected($status === 'due')>Balance due</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Filter</button>
                @if ($search !== '' || $from !== '' || $to !== '' || $status !== 'all')
                    <a class="btn btn-outline-secondary" href="{{ route('sales.index') }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label"><i class="bi bi-receipt"></i> Bills</div><div class="stat-value">{{ $billCount }}</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label"><i class="bi bi-cash"></i> Billed</div><div class="stat-value">{{ $billed }}</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label"><i class="bi bi-check2-circle"></i> Received</div><div class="stat-value">{{ $received }}</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label"><i class="bi bi-hourglass-split"></i> Balance due</div><div class="stat-value">{{ $due }}</div></div></div></div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>When</th>
                        <th class="num">Total</th>
                        <th class="num">Paid</th>
                        <th class="num">Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->number }}</td>
                            <td>
                                <div>{{ $sale->customer?->name }}</div>
                                <div class="small text-secondary">{{ $sale->customer?->mobile }}</div>
                            </td>
                            <td>{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td class="num">{{ $money((string) $sale->total) }}</td>
                            <td class="num">{{ $money((string) $sale->paid_amount) }}</td>
                            <td class="num">{{ $money($sale->balanceDue()) }}</td>
                            <td>
                                @if ((float) $sale->balanceDue() > 0)
                                    <span class="badge text-bg-warning">Due</span>
                                @else
                                    <span class="badge text-bg-success">Paid</span>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ route('sales.show', $sale) }}">Invoice</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No bills match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $sales->links() }}</div>
@endsection
