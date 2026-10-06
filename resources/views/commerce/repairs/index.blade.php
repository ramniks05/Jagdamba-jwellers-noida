@extends('layouts.app')

@section('title', 'Repairs')

@php
    $statusLabel = [
        'received' => 'Received',
        'inspection' => 'Inspection',
        'repairing' => 'Repairing',
        'ready' => 'Ready',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Repairs</h1>
        @can('create', App\Models\RepairOrder::class)
            <a class="btn btn-primary" href="{{ route('repairs.create') }}"><i class="bi bi-plus-lg"></i> Take repair</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Piece</th>
                        <th>Expected</th>
                        <th class="num">Estimate</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($repairs as $repair)
                        <tr>
                            <td>{{ $repair->number }}</td>
                            <td>{{ $repair->customer?->name }}</td>
                            <td>{{ $repair->description }}</td>
                            <td>{{ $repair->expected_on?->format('d M Y') ?: '—' }}</td>
                            <td class="num">{{ $money((string) $repair->estimated_cost) }}</td>
                            <td>{{ $statusLabel[$repair->status] ?? ucfirst($repair->status) }}</td>
                            <td class="text-end"><a href="{{ route('repairs.show', $repair) }}">Receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No repairs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $repairs->links() }}</div>
@endsection
