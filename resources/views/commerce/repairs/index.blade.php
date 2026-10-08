@extends('layouts.app')

@section('title', 'Repairs')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Repairs</h1>
        @can('create', App\Models\RepairOrder::class)
            <a class="btn btn-primary" href="{{ route('repairs.create') }}"><i class="bi bi-plus-lg"></i> Take repair</a>
        @endcan
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['open' => 'In shop', 'ready' => 'Ready to deliver', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
            <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('repairs.index', ['show' => $key]) }}">{{ $label }}</a>
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
                        <th class="num">Charge</th>
                        <th>Received</th>
                        <th>Ready by</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($repairs as $repair)
                        @php($late = in_array($repair->status, ['received', 'inspection', 'repairing'], true) && $repair->expected_on && $repair->expected_on->lt(today()))
                        <tr>
                            <td class="text-nowrap">{{ $repair->number }}</td>
                            <td>{{ $repair->customer?->name }}<div class="small text-secondary">{{ $repair->customer?->mobile }}</div></td>
                            <td>{{ $repair->description }}<div class="small text-secondary">{{ $repair->problem }}</div></td>
                            <td class="num">{{ $weight((string) $repair->gross_weight) }}</td>
                            <td class="num">{{ $money((string) ($repair->final_charge ?? $repair->estimated_cost)) }}</td>
                            <td class="text-nowrap">{{ $repair->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                            <td class="text-nowrap {{ $late ? 'is-late' : '' }}">{{ $repair->expected_on?->format('d-m-Y') ?? '—' }}</td>
                            <td><span class="order-status is-{{ $repair->status }}">{{ $repair->statusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ route('repairs.show', $repair) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No repairs here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $repairs->links() }}</div>
@endsection
