@extends('layouts.app')

@section('title', 'Outstanding')

@section('content')
    @php($advance = $filters['show'] === 'advance')
    @include('commerce.reports.partials.head', ['title' => 'Outstanding report'])
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.outstanding') }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <input type="hidden" name="show" value="{{ $filters['show'] }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label" for="due-search">Search</label>
                <input class="form-control" id="due-search" name="search" value="{{ $filters['search'] }}" placeholder="Customer, mobile or code">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="due-sort">Sort</label>
                <select class="form-select" id="due-sort" name="sort">
                    <option value="balance">{{ $advance ? 'Largest advance' : 'Largest balance' }}</option>
                    <option value="last_bill" @selected($filters['sort'] === 'last_bill')>Oldest last bill</option>
                    <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($filters['search'] !== '' || $filters['sort'] !== 'balance')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.outstanding', ['show' => $filters['show']]) }}">Clear</a>
                @endif
            </div>
        </div>
        <div class="report-ranges">
            @foreach (['due' => 'Customers who owe', 'advance' => 'Advance with shop'] as $key => $label)
                <a class="btn btn-sm {{ $filters['show'] === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reports.outstanding', ['show' => $key, 'per_page' => $perPage]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">{{ $advance ? 'Customer advance' : 'Customer outstanding' }}</div>
                <h2>{{ $company->displayName() }}</h2>
                <p>{{ now()->timezone(config('app.timezone'))->format('d M Y') }}</p>
            </div>
            <div class="text-end">
                <div>{{ $customerCount }} {{ $customerCount === 1 ? 'customer' : 'customers' }}</div>
                <div>{{ $advance ? 'Advance held' : 'Total due' }} {{ $total }}</div>
            </div>
        </header>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Code</th>
                        <th>Mobile</th>
                        <th>Last bill</th>
                        <th class="num">{{ $advance ? 'Advance' : 'Balance' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $customer)
                        <tr>
                            <td><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                            <td class="text-nowrap">{{ $customer->code }}</td>
                            <td class="text-nowrap">{{ $customer->mobile ?: '—' }}</td>
                            <td class="text-nowrap">{{ $customer->last_bill_at ? \Illuminate\Support\Carbon::parse($customer->last_bill_at)->timezone(config('app.timezone'))->format('d M Y') : '—' }}</td>
                            <td class="num">{{ $money(number_format(abs((float) $customer->balance), 2, '.', '')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ $advance ? 'No customer has an advance with the shop.' : 'Nobody has an outstanding balance.' }}</td></tr>
                    @endforelse
                </tbody>
                @if ($customerCount > 0)
                    <tfoot>
                        <tr>
                            <td colspan="4">Total · {{ $customerCount }} {{ $customerCount === 1 ? 'customer' : 'customers' }}</td>
                            <td class="num">{{ $total }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </article>
    @include('commerce.reports.partials.pager', ['rows' => $rows, 'noun' => 'customers'])
@endsection
