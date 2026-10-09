@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Suppliers',
        'intro' => 'Karigars and wholesalers you buy from. You owe '.$money($owedTotal).' in all.',
        'actions' => array_values(array_filter([
            auth()->user()->can('viewAny', App\Models\Purchase::class) ? ['url' => route('purchases.index'), 'label' => 'Purchases', 'icon' => 'bag'] : null,
            auth()->user()->can('create', App\Models\Supplier::class) ? ['url' => route('suppliers.create'), 'label' => 'Add supplier', 'icon' => 'plus-lg', 'primary' => true] : null,
        ])),
    ])
    <form class="d-flex flex-wrap align-items-center gap-2 mb-3" method="GET" action="{{ route('suppliers.index') }}">
        @if ($show !== 'all')
            <input type="hidden" name="show" value="{{ $show }}">
        @endif
        <input class="form-control master-search" name="search" value="{{ $search }}" placeholder="Name, code, mobile or GSTIN" aria-label="Search">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Search</button>
        @if ($search !== '')
            <a class="btn btn-outline-secondary" href="{{ route('suppliers.index', array_filter(['show' => $show === 'all' ? null : $show])) }}">Clear</a>
        @endif
        <div class="d-flex flex-wrap gap-2 ms-md-auto">
            @foreach (['all' => 'All', 'owed' => 'We owe', 'hidden' => 'Hidden'] as $key => $label)
                <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('suppliers.index', array_filter(['search' => $search, 'show' => $key === 'all' ? null : $key])) }}">{{ $label }} <span class="opacity-75">{{ $counts[$key] }}</span></a>
            @endforeach
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Contact</th>
                        <th>GSTIN</th>
                        <th class="num">Purchases</th>
                        <th class="num">We owe</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        @php($owe = $payable[$supplier->id] ?? '0.00')
                        <tr>
                            <td>
                                <a class="fw-semibold" href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                                <div class="small text-secondary">{{ $supplier->code }}{{ $supplier->city ? ' · '.$supplier->city : '' }}</div>
                            </td>
                            <td>
                                {{ $supplier->mobile ?: '—' }}
                                @if ($supplier->contact_name)
                                    <div class="small text-secondary">{{ $supplier->contact_name }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $supplier->gstin ?: '—' }}</td>
                            <td class="num">{{ $supplier->purchases_count }}</td>
                            <td class="num {{ (float) $owe > 0 ? 'text-danger fw-semibold' : 'text-secondary' }}">{{ (float) $owe > 0 ? $money($owe) : ((float) $owe < 0 ? 'Advance '.$money(ltrim($owe, '-')) : '—') }}</td>
                            <td>@include('masters.partials.status', ['active' => $supplier->is_active])</td>
                            <td class="text-end text-nowrap">
                                @if ($supplier->is_active)
                                    @can('create', App\Models\Purchase::class)
                                        <a href="{{ route('purchases.create', ['supplier' => $supplier->uuid]) }}">New purchase</a>
                                    @endcan
                                @endif
                                <a class="ms-2" href="{{ route('suppliers.show', $supplier) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ $search !== '' || $show !== 'all' ? 'No supplier matches this search.' : 'No suppliers yet. Add one, or add them while receiving a purchase.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $suppliers])
@endsection
