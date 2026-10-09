@extends('layouts.app')

@section('title', 'Girvi')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Girvi</h1>
        @can('create', App\Models\GirviPledge::class)
            <a class="btn btn-primary" href="{{ route('girvi.create') }}"><i class="bi bi-plus-lg"></i> New girvi</a>
        @endcan
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['open' => 'Gold in shop', 'released' => 'Released', 'all' => 'All'] as $key => $label)
            <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('girvi.index', ['show' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Pieces</th>
                        <th class="num">Net weight</th>
                        <th class="num">Loan</th>
                        <th class="num">Interest</th>
                        <th>Given on</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pledges as $pledge)
                        @php
                            $metals = $pledge->items->isNotEmpty()
                                ? $pledge->items->map(fn ($item) => trim($item->metalType?->name.' '.$item->purity?->name))->unique()->implode(', ')
                                : trim($pledge->metalType?->name.' '.$pledge->purity?->name);
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $pledge->number }}</td>
                            <td>{{ $pledge->customer?->name }}<div class="small text-secondary">{{ $pledge->customer?->mobile }}</div></td>
                            <td>{{ $pledge->description }}<div class="small text-secondary">{{ $metals }}</div></td>
                            <td class="num">{{ $weight((string) $pledge->net_weight) }}</td>
                            <td class="num">{{ $money((string) $pledge->principal) }}</td>
                            <td class="num text-nowrap">{{ rtrim(rtrim(number_format((float) $pledge->interest_percent, 2, '.', ''), '0'), '.') }}% / month</td>
                            <td class="text-nowrap">{{ $pledge->pledged_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                            <td><span class="order-status is-{{ $pledge->status }}">{{ $pledge->status === 'open' ? 'Gold in shop' : 'Released' }}</span></td>
                            <td class="text-end"><a href="{{ route('girvi.show', $pledge) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No girvi here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $pledges->links() }}</div>
@endsection
