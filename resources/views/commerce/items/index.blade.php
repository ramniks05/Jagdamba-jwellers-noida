@extends('layouts.app')

@section('title', 'Pieces')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title h3 mb-0">Jewellery pieces</h1>
            <p class="text-secondary mb-0">This is the stock list. To sell a piece, open Sales and start a new bill.</p>
        </div>
        @can('create', App\Models\Item::class)
            <a class="btn btn-primary" href="{{ route('items.create') }}">Add piece</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('items.index') }}">
        <div class="col-md-4">
            <input class="form-control" name="search" value="{{ $search }}" placeholder="Code, name, barcode, or HUID">
        </div>
        <div class="col-md-3">
            <select class="form-select" name="status">
                <option value="">Every status</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Metal</th><th>Net g</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->item_code }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->metalType?->name }} {{ $item->purity?->name }}</td>
                            <td>{{ $item->net_weight }}</td>
                            <td>{{ $item->status->label() }}</td>
                            <td class="text-end"><a href="{{ route('items.show', $item) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No pieces yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endsection
