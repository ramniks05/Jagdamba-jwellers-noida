@extends('layouts.app')

@section('title', 'Purchases')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Purchases</h1>
        @can('create', App\Models\Purchase::class)
            <a class="btn btn-primary" href="{{ route('purchases.create') }}">Receive purchase</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Number</th><th>Supplier</th><th>When</th><th>Total</th><th>Paid</th><th></th></tr></thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td>{{ $purchase->number }}</td>
                            <td>{{ $purchase->supplier?->name }}</td>
                            <td>{{ $purchase->purchased_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $purchase->total }}</td>
                            <td>{{ $purchase->paid_amount }}</td>
                            <td class="text-end"><a href="{{ route('purchases.show', $purchase) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No purchases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $purchases->links() }}</div>
@endsection
