@extends('layouts.app')

@section('title', 'Sales')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Sales</h1>
        @can('create', App\Models\Sale::class)
            <a class="btn btn-primary" href="{{ route('sales.create') }}">New bill</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Invoice</th><th>Customer</th><th>When</th><th>Total</th><th>Paid</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->number }}</td>
                            <td>{{ $sale->customer?->name }}</td>
                            <td>{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $sale->total }}</td>
                            <td>{{ $sale->paid_amount }}</td>
                            <td class="text-end"><a href="{{ route('sales.show', $sale) }}">Invoice</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $sales->links() }}</div>
@endsection
