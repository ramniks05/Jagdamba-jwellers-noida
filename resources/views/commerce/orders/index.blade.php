@extends('layouts.app')

@section('title', 'Orders')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Orders</h1>
        @can('create', App\Models\AdvanceOrder::class)
            <a class="btn btn-primary" href="{{ route('orders.create') }}"><i class="bi bi-plus-lg"></i> New order</a>
        @endcan
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['open' => 'To deliver', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
            <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('orders.index', ['show' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Piece</th>
                        <th class="num">Weight</th>
                        <th class="num">Locked rate</th>
                        <th class="num">Advance</th>
                        <th>Delivery</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($late = $order->isOpen() && $order->due_on && $order->due_on->lt(today()))
                        <tr>
                            <td>{{ $order->number }}</td>
                            <td>{{ $order->customer?->name }}</td>
                            <td>{{ $order->description }}<div class="small text-secondary">{{ $order->metalType?->name }} {{ $order->purity?->name }}</div></td>
                            <td class="num">{{ $weight((string) $order->expected_weight) }}</td>
                            <td class="num">{{ $money((string) $order->rate_per_gram) }}</td>
                            <td class="num">{{ $money($order->advanceHeld()) }}</td>
                            <td class="{{ $late ? 'is-late' : '' }}">{{ $order->due_on?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $order->statusLabel() }}</td>
                            <td class="text-end"><a href="{{ route('orders.show', $order) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No orders here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
