@extends('layouts.app')

@section('title', 'Stock report')

@section('content')
    @include('commerce.reports.partials.head', ['title' => 'Stock report'])
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.stock') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="stock-search">Search</label>
                <input class="form-control" id="stock-search" name="search" value="{{ $filters['search'] }}" placeholder="Name, code, HUID or barcode">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="stock-metal">Metal</label>
                <select class="form-select" id="stock-metal" name="metal">
                    <option value="">All metals</option>
                    @foreach ($metals as $row)
                        <option value="{{ $row->uuid }}" @selected($filters['metal'] === $row->uuid)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="stock-category">Category</label>
                <select class="form-select" id="stock-category" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $row)
                        <option value="{{ $row->id }}" @selected($filters['category'] === (string) $row->id)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($locations->count() > 1)
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label" for="stock-location">Place</label>
                    <select class="form-select" id="stock-location" name="location">
                        <option value="">All places</option>
                        @foreach ($locations as $row)
                            <option value="{{ $row->uuid }}" @selected($filters['location'] === $row->uuid)>{{ $row->branch?->name }} / {{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="stock-status">Status</label>
                <select class="form-select" id="stock-status" name="status">
                    <option value="all">In stock</option>
                    <option value="available" @selected($filters['status'] === 'available')>Available</option>
                    <option value="reserved" @selected($filters['status'] === 'reserved')>Reserved</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="stock-source">Source</label>
                <select class="form-select" id="stock-source" name="source">
                    <option value="">All</option>
                    @foreach ($sources as $option)
                        <option value="{{ $option->value }}" @selected($filters['source'] === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="stock-sort">Sort</label>
                <select class="form-select" id="stock-sort" name="sort">
                    <option value="code">Piece code</option>
                    <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                    <option value="heaviest" @selected($filters['sort'] === 'heaviest')>Heaviest first</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($filtered || $filters['sort'] !== 'code')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.stock') }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    @if ($byMetal->isNotEmpty())
        <div class="row g-3 mb-3 no-print">
            @foreach ($byMetal as $row)
                <div class="col-md-3 col-6">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="stat-label">{{ $row['label'] }}</div>
                            <div class="fw-semibold">{{ $row['pieces'] }} {{ $row['pieces'] === 1 ? 'piece' : 'pieces' }}</div>
                            <div>{{ $row['net'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Stock on hand</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ now()->timezone(config('app.timezone'))->format('d M Y') }}@if ($filtered) · filtered @endif</p>
            </div>
            <div class="text-end">
                <div>{{ $pieceCount }} {{ $pieceCount === 1 ? 'piece' : 'pieces' }}</div>
                <div>Net {{ $netTotal }}</div>
            </div>
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
                        <th>Source</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pieces as $item)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('items.show', $item) }}">{{ $item->item_code }}</a></td>
                            <td>{{ $item->name }}@if ($item->huid)<div class="small text-secondary">HUID {{ $item->huid }}</div>@endif</td>
                            <td>{{ trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? '')) }}</td>
                            <td>{{ $item->category?->name ?: '—' }}</td>
                            <td>{{ collect([$item->location?->branch?->name, $item->location?->name])->filter()->join(' / ') ?: '—' }}</td>
                            <td>{{ $item->source->label() }}</td>
                            <td class="num">{{ $weight((string) $item->gross_weight) }}</td>
                            <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                            <td><span class="order-status is-{{ $item->status->value }}">{{ $item->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No pieces in stock for this filter.</td></tr>
                    @endforelse
                </tbody>
                @if ($pieceCount > 0)
                    <tfoot>
                        <tr>
                            <td colspan="6">Total · {{ $pieceCount }} {{ $pieceCount === 1 ? 'piece' : 'pieces' }}</td>
                            <td class="num">{{ $grossTotal }}</td>
                            <td class="num">{{ $netTotal }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </article>
    @include('commerce.reports.partials.pager', ['rows' => $pieces, 'noun' => 'pieces'])
@endsection
