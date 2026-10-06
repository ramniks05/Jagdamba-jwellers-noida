@extends('layouts.app')

@section('title', $item->item_code)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-title h3 mb-1">{{ $item->name }}</h1>
            <div class="text-secondary">{{ $item->item_code }} · {{ $item->status->label() }}</div>
        </div>
        <div class="d-flex gap-2">
            @can('update', $item)
                <a class="btn btn-outline-secondary" href="{{ route('items.edit', $item) }}">Edit</a>
            @endcan
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Metal</div>
                <div>{{ $item->metalType?->name }} {{ $item->purity?->name }}</div>
                <div class="small text-secondary mt-2">Gross {{ $item->gross_weight }} g · Net {{ $item->net_weight }} g</div>
                <div class="small text-secondary">Stone {{ $item->stone_weight }} g · Other {{ $item->other_weight }} g</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Place</div>
                <div>{{ $item->location?->branch?->name }}</div>
                <div>{{ $item->location?->label() }}</div>
                <div class="small text-secondary mt-2">{{ $item->category?->name }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Marks</div>
                <div>HUID {{ $item->huid ?: '—' }}</div>
                <div>Hallmark {{ $item->hallmark ?: '—' }}</div>
                <div>Certificate {{ $item->certificate_number ?: '—' }}</div>
            </div></div>
        </div>
    </div>
    @can('inventory.adjust')
        <div class="d-flex gap-2 mb-4">
            @if ($item->status->value === 'available')
                <form method="POST" action="{{ route('items.reserve', $item) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Reserve</button></form>
                <form method="POST" action="{{ route('items.damage', $item) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Mark damaged</button></form>
                <form method="POST" action="{{ route('items.lost', $item) }}">@csrf<button class="btn btn-outline-danger btn-sm" type="submit">Mark lost</button></form>
            @endif
            @if ($item->status->value === 'reserved')
                <form method="POST" action="{{ route('items.release', $item) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Release</button></form>
            @endif
        </div>
    @endcan
    <div class="card">
        <div class="card-header bg-white">Stock history</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>When</th><th>Movement</th><th>Quantity</th><th>Note</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr>
                            <td>{{ $movement->occurred_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $movement->type->label() }}</td>
                            <td>{{ $movement->quantity }}</td>
                            <td>{{ $movement->notes }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
