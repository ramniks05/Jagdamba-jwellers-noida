@extends('layouts.app')

@section('title', 'Own gold')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title h3 mb-0">Own gold</h1>
            <p class="text-secondary mb-0">Jewellery you own. These pieces stay off the stock page and are sold by their item code.</p>
        </div>
        @can('create', App\Models\Item::class)
            <a class="btn btn-primary" href="{{ route('items.create', ['own' => 1]) }}"><i class="bi bi-plus-lg"></i> Add own jewellery</a>
        @endcan
    </div>

    <form class="filter-bar" method="GET" action="{{ route('purchases.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="own-search">Find</label>
                <input class="form-control" id="own-search" name="search" value="{{ $search }}" placeholder="Item code, name or HUID">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Find</button>
                @if ($search !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('purchases.index') }}">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Item code</th>
                        <th>Name</th>
                        <th>Metal</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}@if ($item->category)<div class="small text-secondary">{{ $item->category->name }}</div>@endif</td>
                            <td>{{ $item->metalType?->name }} {{ $item->purity?->name }}</td>
                            <td class="num">{{ $weight((string) $item->gross_weight) }}</td>
                            <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ $search !== '' ? 'Nothing found.' : 'No own jewellery yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endsection
