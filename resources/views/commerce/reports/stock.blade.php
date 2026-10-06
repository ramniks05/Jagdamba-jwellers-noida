@extends('layouts.app')

@section('title', 'Stock report')

@section('content')
    <h1 class="page-title h3 mb-4">Stock on hand</h1>
    <div class="row g-3 mb-4">
        @foreach ($byMetal as $label => $row)
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="stat-label">{{ $label }}</div><div class="fw-semibold">{{ $row['pieces'] }} pieces</div><div>{{ $row['net'] }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Place</th><th>Net</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($pieces as $item)
                        <tr>
                            <td><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->category?->name }}</td>
                            <td>{{ $item->location?->branch?->name }} / {{ $item->location?->name }}</td>
                            <td>{{ $weight((string) $item->net_weight) }}</td>
                            <td>{{ $item->status->label() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No pieces in stock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
