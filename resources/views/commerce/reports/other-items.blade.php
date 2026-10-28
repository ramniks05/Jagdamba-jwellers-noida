@extends('layouts.app')

@section('title', 'Other item sales')

@section('content')
    @include('commerce.reports.partials.head', ['title' => 'Other item sales', 'print' => true])
    <form class="filter-bar no-print mb-3" method="GET" action="{{ route('reports.other-items') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="filter-search">Search</label>
                <input class="form-control" id="filter-search" name="search" value="{{ $filters['search'] }}" placeholder="Item or bill no.">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label" for="filter-from">From</label>
                <input class="form-control" id="filter-from" name="from" type="date" value="{{ $filters['from'] }}">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label" for="filter-to">To</label>
                <input class="form-control" id="filter-to" name="to" type="date" value="{{ $filters['to'] }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="stat-label">Lines</div><div class="fw-semibold">{{ $lineCount }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="stat-label">Quantity</div><div class="fw-semibold">{{ $quantity }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="stat-label">Sales</div><div class="fw-semibold">{{ $amount }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="stat-label">GST on lines</div><div class="fw-semibold">{{ $tax }}</div></div></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bill</th>
                        <th>Customer</th>
                        <th>Item</th>
                        <th class="num">Qty</th>
                        <th class="num">Amount</th>
                        <th class="num">GST</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr>
                            <td>{{ $line->sale?->sold_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                            <td>
                                @can('view', $line->sale)
                                    <a href="{{ route('sales.show', $line->sale) }}">{{ $line->sale?->number }}</a>
                                @else
                                    {{ $line->sale?->number }}
                                @endcan
                            </td>
                            <td>{{ $line->sale?->customer?->name }}</td>
                            <td>{{ $line->name }}</td>
                            <td class="num">{{ $line->quantity }}</td>
                            <td class="num">{{ $money((string) $line->line_amount) }}</td>
                            <td class="num">{{ $money((string) $line->tax_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No other-item sales in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $lines->links() }}
    </div>
@endsection
