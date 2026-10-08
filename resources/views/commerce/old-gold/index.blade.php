@extends('layouts.app')

@section('title', 'Old gold')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Old gold</h1>
        @can('create', App\Models\OldGoldExchange::class)
            <a class="btn btn-primary" href="{{ route('old-gold.create') }}"><i class="bi bi-plus-lg"></i> New exchange</a>
        @endcan
    </div>
    @include('commerce.old-gold.tabs', ['active' => 'exchanges'])
    <form class="d-flex flex-wrap gap-2 mb-3" method="GET" action="{{ route('old-gold.index') }}">
        <input class="form-control" style="max-width: 22rem" name="search" value="{{ $search }}" placeholder="Number, customer name or mobile">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Find</button>
        @if ($search !== '')
            <a class="btn btn-link" href="{{ route('old-gold.index') }}">Clear</a>
        @endif
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Metal</th>
                        <th class="num">Gross</th>
                        <th class="num">Fine</th>
                        <th class="num">Value</th>
                        <th class="num">Paid</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exchanges as $exchange)
                        <tr>
                            <td class="text-nowrap">{{ $exchange->number }}</td>
                            <td class="text-nowrap">{{ $exchange->exchanged_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                            <td>{{ $exchange->customer?->name }}<div class="small text-secondary">{{ $exchange->customer?->mobile }}</div></td>
                            <td class="text-nowrap">{{ $exchange->metalType?->name }} {{ $exchange->purity?->name }}</td>
                            <td class="num">{{ $weight((string) $exchange->gross_weight) }}</td>
                            <td class="num">{{ $weight((string) $exchange->melted_weight) }}</td>
                            <td class="num">{{ $money((string) $exchange->exchange_value) }}</td>
                            <td class="num">{{ $money((string) ($exchange->payments_sum_amount ?? '0')) }}</td>
                            <td class="text-end"><a href="{{ route('old-gold.show', $exchange) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">{{ $search !== '' ? 'Nothing found.' : 'No old gold yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $exchanges->links() }}</div>
@endsection
