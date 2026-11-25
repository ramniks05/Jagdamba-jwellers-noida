@extends('layouts.app')

@section('title', 'Supplier passbook report')

@section('content')
    @php
        $amount = fn ($value) => number_format((float) $value, 2, '.', '');
        $owed = fn ($value) => (float) $value < -0.004 ? $money(ltrim($amount($value), '-')).' advance' : $money($amount($value));
        $date = fn (string $day) => \Illuminate\Support\Carbon::parse($day)->format('d-m-Y');
        $query = fn (array $extra = []) => array_filter(array_merge(['supplier' => $filters['supplier'] ?: null, 'from' => $filters['from'], 'to' => $filters['to'], 'per_page' => $perPage], $extra), fn ($value) => $value !== null);
    @endphp
    @include('commerce.reports.partials.head', ['title' => 'Supplier passbook report', 'pdfUrl' => route('reports.suppliers', $query(['pdf' => 1, 'per_page' => null]))])
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.suppliers') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label" for="report-supplier">Supplier</label>
                <select class="form-select" id="report-supplier" name="supplier">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $option)
                        <option value="{{ $option->uuid }}" @selected($filters['supplier'] === $option->uuid)>{{ $option->name }} ({{ $option->code }}){{ $option->is_active ? '' : ' · hidden' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="report-from">From</label>
                <input class="form-control" id="report-from" type="date" name="from" value="{{ $filters['from'] }}">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="report-to">To</label>
                <input class="form-control" id="report-to" type="date" name="to" value="{{ $filters['to'] }}">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($filters['supplier'] !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.suppliers', ['from' => $filters['from'], 'to' => $filters['to']]) }}">All suppliers</a>
                @endif
            </div>
        </div>
        <div class="report-ranges">
            @foreach ($ranges as $label => [$rangeFrom, $rangeTo])
                <a class="btn btn-sm {{ $filters['from'] === $rangeFrom && $filters['to'] === $rangeTo ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reports.suppliers', $query(['from' => $rangeFrom, 'to' => $rangeTo])) }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    <div class="row g-3 mb-3 no-print">
        <div class="col-md col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">We owed on {{ $date($filters['from']) }}</div><div class="fw-semibold fs-5">{{ $owed($sums['opening']) }}</div></div></div>
        </div>
        <div class="col-md col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Purchased</div><div class="fw-semibold fs-5">{{ $money($amount($sums['purchased'])) }}</div></div></div>
        </div>
        <div class="col-md col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Paid</div><div class="fw-semibold fs-5">{{ $money($amount($sums['paid'])) }}</div></div></div>
        </div>
        <div class="col-md col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Sent back</div><div class="fw-semibold fs-5">{{ $money($amount($sums['returned'])) }}</div></div></div>
        </div>
        <div class="col-md col-6">
            <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">We owe on {{ $date($filters['to']) }}</div><div class="fw-semibold fs-5">{{ $owed($sums['closing']) }}</div></div></div>
        </div>
    </div>

    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Supplier passbook</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ $supplier ? $supplier->name.' ('.$supplier->code.')' : 'All suppliers' }} · {{ $date($filters['from']) }} to {{ $date($filters['to']) }}</p>
            </div>
            <div class="text-end">
                @if ($supplier)
                    <div>{{ count($passbook) }} {{ count($passbook) === 1 ? 'entry' : 'entries' }}</div>
                @else
                    <div>{{ $supplierCount }} {{ $supplierCount === 1 ? 'supplier' : 'suppliers' }}</div>
                @endif
                <div>We owe {{ $owed($sums['closing']) }}</div>
            </div>
        </header>
        <div class="table-responsive">
            @if ($supplier)
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Details</th>
                            <th class="num">Purchased</th>
                            <th class="num">Sent back</th>
                            <th class="num">Paid</th>
                            <th class="num">We owe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="text-secondary">
                            <td class="text-nowrap">{{ $date($filters['from']) }}</td>
                            <td>Balance brought forward</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="num fw-semibold">{{ $owed($sums['opening']) }}</td>
                        </tr>
                        @forelse ($passbook as $row)
                            <tr>
                                <td class="text-nowrap">{{ $date($row['date']) }}</td>
                                <td>
                                    @if ($row['link'])
                                        <a class="fw-semibold" href="{{ $row['link'] }}">{{ $row['title'] }}</a>
                                    @else
                                        {{ $row['title'] }}
                                    @endif
                                    @if ($row['detail'])
                                        <div class="small text-secondary">{{ $row['detail'] }}</div>
                                    @endif
                                </td>
                                <td class="num">{{ $row['purchased'] !== null ? $money($amount($row['purchased'])) : '' }}</td>
                                <td class="num">{{ $row['returned'] !== null ? $money($amount($row['returned'])) : '' }}</td>
                                <td class="num">{{ $row['paid'] !== null ? $money($amount($row['paid'])) : '' }}</td>
                                <td class="num fw-semibold">{{ $owed($row['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Nothing bought or paid between these dates.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">Total for these dates</td>
                            <td class="num">{{ $money($amount($sums['purchased'])) }}</td>
                            <td class="num">{{ $money($amount($sums['returned'])) }}</td>
                            <td class="num">{{ $money($amount($sums['paid'])) }}</td>
                            <td class="num">{{ $owed($sums['closing']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th class="num">We owed on {{ $date($filters['from']) }}</th>
                            <th class="num">Purchased</th>
                            <th class="num">Sent back</th>
                            <th class="num">Paid</th>
                            <th class="num">We owe on {{ $date($filters['to']) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('reports.suppliers', $query(['supplier' => $row->uuid])) }}">{{ $row->name }}</a>
                                    <div class="small text-secondary">{{ $row->code }}</div>
                                </td>
                                <td class="num">{{ $owed($row->opening) }}</td>
                                <td class="num">{{ $money($amount($row->purchased)) }}</td>
                                <td class="num">{{ $money($amount($row->returned)) }}</td>
                                <td class="num">{{ $money($amount($row->paid)) }}</td>
                                <td class="num fw-semibold">{{ $owed($row->closing) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No supplier has anything bought, paid or owed in these dates.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($supplierCount > 0)
                        <tfoot>
                            <tr>
                                <td>Total · {{ $supplierCount }} {{ $supplierCount === 1 ? 'supplier' : 'suppliers' }}</td>
                                <td class="num">{{ $owed($sums['opening']) }}</td>
                                <td class="num">{{ $money($amount($sums['purchased'])) }}</td>
                                <td class="num">{{ $money($amount($sums['returned'])) }}</td>
                                <td class="num">{{ $money($amount($sums['paid'])) }}</td>
                                <td class="num">{{ $owed($sums['closing']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>
    </article>
    @unless ($supplier)
        @include('commerce.reports.partials.pager', ['rows' => $rows, 'noun' => 'suppliers'])
    @endunless
@endsection
