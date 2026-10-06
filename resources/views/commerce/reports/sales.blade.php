@extends('layouts.app')

@section('title', 'Sales report')

@section('content')
    <h1 class="page-title h3 mb-4">Sales</h1>
    <form class="row g-2 mb-3" method="GET" action="{{ route('reports.sales') }}">
        <div class="col-auto"><input class="form-control" type="date" name="from" value="{{ $from }}"></div>
        <div class="col-auto"><input class="form-control" type="date" name="to" value="{{ $to }}"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Show</button></div>
    </form>
    <p class="fw-semibold">Total {{ $total }}</p>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Invoice</th><th>Customer</th><th>When</th><th>Total</th><th>Paid</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
                            <td>{{ $sale->customer?->name }}</td>
                            <td>{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $money((string) $sale->total) }}</td>
                            <td>{{ $money((string) $sale->paid_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No bills in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
