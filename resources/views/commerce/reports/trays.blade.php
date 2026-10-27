@extends('layouts.app')

@section('title', 'Tray report')

@section('content')
    @include('commerce.reports.partials.head', ['title' => 'Tray report', 'print' => false])
    <form class="filter-bar no-print mb-3" method="GET" action="{{ route('reports.trays') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="filter-tray">Tray</label>
                <select class="form-select" id="filter-tray" name="tray" required>
                    <option value="">Choose a tray</option>
                    @foreach ($trays as $option)
                        <option value="{{ $option->uuid }}" @selected($trayUuid === $option->uuid)>{{ $option->name }} ({{ $option->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label" for="filter-from">From</label>
                <input class="form-control" id="filter-from" name="from" type="date" value="{{ $from }}">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label" for="filter-to">To</label>
                <input class="form-control" id="filter-to" name="to" type="date" value="{{ $to }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
            </div>
        </div>
    </form>

    @if ($tray)
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="stat-label">Tray</div>
                        <div class="fw-semibold">{{ $tray->name }}</div>
                        <div class="small text-secondary">{{ $tray->code }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="stat-label">Weight left</div>
                        <div class="fw-semibold">{{ $weight((string) $tray->grams) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="stat-label">Pieces left</div>
                        <div class="fw-semibold">{{ $tray->pieces }}</div>
                        @unless ($tray->is_active)
                            <span class="badge text-bg-secondary mt-1">Inactive</span>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">Sales from this tray</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bill</th>
                            <th>Customer</th>
                            <th class="num">Net weight</th>
                            <th class="num">Pieces</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            @php
                                $net = $sale->lines->reduce(fn ($sum, $line) => $sum + (float) $line->net_weight, 0);
                                $detail = $sale->lines->map(fn ($line) => trim($line->name.' · '.$line->metal_name.' '.$line->purity_name))->join('; ');
                            @endphp
                            <tr>
                                <td>{{ $sale->sold_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                                <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
                                <td>{{ $sale->customer?->name }}</td>
                                <td class="num">{{ $weight(number_format($net, 3, '.', '')) }}</td>
                                <td class="num">1</td>
                                <td class="small text-secondary">{{ $detail }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No posted bills from this tray for these dates.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($sales instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="mt-3">{{ $sales->links() }}</div>
        @endif
    @else
        <p class="text-secondary">Choose a tray to see its balance and sales.</p>
    @endif
@endsection
