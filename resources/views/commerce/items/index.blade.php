@extends('layouts.app')

@section('title', 'Pieces')

@section('content')
    @php
        $pill = fn (string $value) => match ($value) {
            'available' => 'is-ready',
            'reserved' => 'is-booked',
            'repair' => 'is-repairing',
            'damaged', 'lost' => 'is-cancelled',
            default => 'is-delivered',
        };
        $filters = ['available' => 'In stock', 'reserved' => 'Reserved', 'repair' => 'At repair', 'sold' => 'Sold', '' => 'All'];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title h3 mb-0">Jewellery pieces</h1>
            <p class="text-secondary mb-0">Your stock list. To sell a piece, open it and use Sell this piece, or start a New bill.</p>
        </div>
        @can('create', App\Models\Item::class)
            <a class="btn btn-primary" href="{{ route('items.create') }}"><i class="bi bi-plus-lg"></i> Add piece</a>
        @endcan
    </div>
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach ($filters as $key => $label)
            @php($count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0))
            <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('items.index', ['status' => $key] + array_filter(['search' => $search])) }}">{{ $label }} <span class="opacity-75">{{ $count }}</span></a>
        @endforeach
        @if (! array_key_exists($status, $filters))
            <span class="btn btn-sm btn-primary disabled">{{ App\Enums\ItemStatus::tryFrom($status)?->label() }}</span>
        @endif
    </div>
    <form class="d-flex flex-wrap gap-2 mb-3" method="GET" action="{{ route('items.index') }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <input class="form-control" style="max-width: 22rem" name="search" value="{{ $search }}" placeholder="Code, name, barcode or HUID">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Find</button>
        @if ($search !== '')
            <a class="btn btn-link" href="{{ route('items.index', ['status' => $status]) }}">Clear</a>
        @endif
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Piece</th>
                        <th>Metal</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                        <th>Kept at</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}@if ($item->category)<div class="small text-secondary">{{ $item->category->name }}</div>@endif</td>
                            <td class="text-nowrap">{{ $item->metalType?->name }} {{ $item->purity?->name }}</td>
                            <td class="num">{{ $weight((string) $item->gross_weight) }}</td>
                            <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                            <td class="text-nowrap">{{ $item->location?->label() }}</td>
                            <td><span class="order-status {{ $pill($item->status->value) }}">{{ $item->status->label() }}</span></td>
                            <td class="text-end"><a href="{{ route('items.show', $item) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ $search !== '' ? 'Nothing found.' : 'No pieces here yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endsection
