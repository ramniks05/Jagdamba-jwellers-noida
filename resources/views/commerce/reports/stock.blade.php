@extends('layouts.app')

@section('title', 'Stock report')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="page-title h3 mb-0">Stock report</h1>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print report</button>
    </div>
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.stock') }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="stock-search">Name, code, or HUID</label>
                <input class="form-control" id="stock-search" name="search" value="{{ $search }}" placeholder="Ring or piece code">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="stock-metal">Metal</label>
                <select class="form-select" id="stock-metal" name="metal">
                    <option value="">All metals</option>
                    @foreach ($metals as $row)
                        <option value="{{ $row->uuid }}" @selected($metal === $row->uuid)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($search !== '' || $metal !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.stock') }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    <div class="row g-3 mb-3 no-print">
        @foreach ($byMetal as $label => $row)
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="stat-label">{{ $label }}</div>
                        <div class="fw-semibold">{{ $row['pieces'] }} pieces</div>
                        <div>{{ $row['net'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Stock on hand</div>
                <h2>{{ $company->displayName() }}</h2>
            </div>
            <div>{{ $pieceCount }} pieces · {{ $netTotal }}</div>
        </header>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Metal</th>
                        <th>Category</th>
                        <th>Place</th>
                        <th class="num">Net</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pieces as $item)
                        <tr>
                            <td><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}</td>
                            <td>{{ trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? '')) }}</td>
                            <td>{{ $item->category?->name ?: '—' }}</td>
                            <td>{{ $item->location?->branch?->name }} / {{ $item->location?->name }}</td>
                            <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                            <td>{{ $item->status->label() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No pieces in stock for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
@endsection
