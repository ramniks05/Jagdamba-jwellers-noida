@extends('layouts.app')

@section('title', 'Stock report')

@section('content')
    @include('commerce.reports.partials.head', ['title' => 'Stock report'])
    @if ($metalStock->isNotEmpty())
        <div class="table-responsive mb-3">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Metal</th>
                        <th>Purity</th>
                        <th class="num">Opening</th>
                        <th class="num">Purchased</th>
                        <th class="num">Sold</th>
                        <th class="num">In hand</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($metalStock as $row)
                        <tr>
                            <td>{{ $row['metal'] }}</td>
                            <td>{{ $row['purity'] }}</td>
                            <td class="num">{{ $weight($row['opening']) }}</td>
                            <td class="num">{{ $weight($row['purchased']) }}</td>
                            <td class="num">{{ $weight($row['sold']) }}</td>
                            <td class="num {{ $row['short'] ? 'text-danger' : '' }}"><strong>{{ $weight($row['balance']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @can('inventory.create')
        <div class="card mb-3 no-print">
            <div class="card-header bg-white">Add grams</div>
            <div class="card-body">
                <p class="text-secondary small">Stock is grams of a purity. Opening sets what is already in the shop. A purchase adds more grams. A bill takes the net grams off. Pieces are not added here.</p>
                <form method="POST" action="{{ route('reports.stock.store') }}" class="row g-2 align-items-end" id="metal-stock-form">
                    @csrf
                    @if ($branches->count() > 1)
                        <div class="col-md-2 col-6">
                            <label class="form-label" for="stock-branch">Branch</label>
                            <select class="form-select" id="stock-branch" name="branch_uuid" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->uuid }}" @selected(old('branch_uuid', $branches->first()?->uuid) === $branch->uuid)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_uuid" value="{{ $branches->first()?->uuid }}">
                    @endif
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-kind">Add as</label>
                        <select class="form-select" id="stock-kind" name="kind">
                            <option value="opening" @selected(old('kind', 'opening') === 'opening')>Opening stock</option>
                            <option value="purchase" @selected(old('kind') === 'purchase')>Purchase</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-metal">Metal</label>
                        <select class="form-select" id="stock-metal" name="metal_uuid" required>
                            @foreach ($metals as $metal)
                                <option value="{{ $metal->uuid }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-purity">Purity</label>
                        <select class="form-select" id="stock-purity" name="purity_uuid" required>
                            @foreach ($metals as $metal)
                                @foreach ($metal->purities as $purity)
                                    <option value="{{ $purity->uuid }}" data-metal="{{ $metal->uuid }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="stock-grams">Grams</label>
                        <input class="form-control" id="stock-grams" name="grams" value="{{ old('grams') }}" inputmode="decimal" placeholder="0.000" required>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary" type="submit">Add to stock</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.stock') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="stock-search">Search</label>
                <input class="form-control" id="stock-search" name="search" value="{{ $filters['search'] }}" placeholder="Name, code, HUID or barcode">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="filter-metal">Metal</label>
                <select class="form-select" id="filter-metal" name="metal">
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

@push('scripts')
    <script>
        const stockMetal = document.getElementById('stock-metal');
        const stockPurity = document.getElementById('stock-purity');
        if (stockMetal && stockPurity) {
            const syncStockPurity = () => {
                const metal = stockMetal.value;
                let first = null;
                Array.from(stockPurity.options).forEach((option) => {
                    const show = option.dataset.metal === metal;
                    option.hidden = !show;
                    option.disabled = !show;
                    if (show && !first) first = option;
                });
                if (!stockPurity.selectedOptions[0] || stockPurity.selectedOptions[0].disabled) {
                    if (first) first.selected = true;
                }
            };
            stockMetal.addEventListener('change', syncStockPurity);
            syncStockPurity();
        }
    </script>
@endpush
