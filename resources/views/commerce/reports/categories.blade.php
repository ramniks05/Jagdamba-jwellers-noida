@extends('layouts.app')

@section('title', 'Pieces by category report')

@section('content')
    @include('commerce.reports.partials.head', ['title' => 'Pieces by category report'])
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.categories') }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-4 col-6">
                <label class="form-label" for="category-metal">Metal</label>
                <select class="form-select" id="category-metal" name="metal">
                    <option value="">All metals</option>
                    @foreach ($metals as $option)
                        <option value="{{ $option->uuid }}" @selected($filters['metal'] === $option->uuid)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-4 col-6">
                <label class="form-label" for="category-location">Kept at</label>
                <select class="form-select" id="category-location" name="location">
                    <option value="">All places</option>
                    @foreach ($locations as $option)
                        <option value="{{ $option->uuid }}" @selected($filters['location'] === $option->uuid)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($filters['metal'] !== '' || $filters['location'] !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.categories') }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Pieces by category</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ now()->timezone(config('app.timezone'))->format('d M Y') }}</p>
            </div>
            <div class="text-end">
                <div>{{ $totals['in_stock'] }} pcs in stock</div>
                <div>Net {{ $weight($totals['net']) }}</div>
            </div>
        </header>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="num">In stock</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                        <th class="num">Reserved</th>
                        <th class="num">At repair</th>
                        <th class="num">Sold</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td class="num fw-semibold">{{ $row['in_stock'] }}</td>
                            <td class="num">{{ $weight($row['gross']) }}</td>
                            <td class="num">{{ $weight($row['net']) }}</td>
                            <td class="num">{{ $row['reserved'] ?: '—' }}</td>
                            <td class="num">{{ $row['repair'] ?: '—' }}</td>
                            <td class="num">{{ $row['sold'] ?: '—' }}</td>
                            <td class="text-end text-nowrap no-print">
                                @if ($row['in_stock'] > 0)
                                    <a href="{{ route('items.index', ['status' => 'available', 'category' => $row['uuid'] ?? 'none']) }}">See pieces</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No pieces yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total · {{ $rows->count() }} {{ $rows->count() === 1 ? 'category' : 'categories' }}</td>
                            <td class="num">{{ $totals['in_stock'] }}</td>
                            <td class="num">{{ $weight($totals['gross']) }}</td>
                            <td class="num">{{ $weight($totals['net']) }}</td>
                            <td class="num">{{ $totals['reserved'] }}</td>
                            <td class="num">{{ $totals['repair'] }}</td>
                            <td class="num">{{ $totals['sold'] }}</td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </article>
@endsection
