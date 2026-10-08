@extends('layouts.app')

@section('title', 'Purchases')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Purchases</h1>
        @can('create', App\Models\Purchase::class)
            <a class="btn btn-primary" href="{{ route('purchases.create') }}"><i class="bi bi-plus-lg"></i> Receive purchase</a>
        @endcan
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['all' => 'All', 'due' => 'To pay', 'paid' => 'Paid'] as $key => $label)
            <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('purchases.index', array_filter(['show' => $key === 'all' ? null : $key, 'search' => $search])) }}">{{ $label }} <span class="opacity-75">{{ $counts[$key] }}</span></a>
        @endforeach
        @if ((float) $dueTotal > 0)
            <span class="align-self-center small text-secondary ms-1">{{ $money($dueTotal) }} still to pay on these bills</span>
        @endif
    </div>

    <form class="d-flex flex-wrap gap-2 mb-3" method="GET" action="{{ route('purchases.index') }}">
        @if ($show !== 'all')
            <input type="hidden" name="show" value="{{ $show }}">
        @endif
        <input class="form-control" style="max-width: 22rem" name="search" value="{{ $search }}" placeholder="Number, supplier, their bill no. or mobile">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Find</button>
        @if ($search !== '')
            <a class="btn btn-link" href="{{ route('purchases.index', $show !== 'all' ? ['show' => $show] : []) }}">Clear</a>
        @endif
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Purchase</th>
                        <th>Supplier</th>
                        <th class="num">Pieces</th>
                        <th class="num">Total</th>
                        <th class="num">Paid</th>
                        <th class="num">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        @php
                            $due = $purchase->dueAmount();
                            [$statusText, $statusClass] = (float) $due <= 0
                                ? ((float) $purchase->returnedAmount() >= (float) $purchase->total && (float) $purchase->total > 0 ? ['Sent back', 'is-cancelled'] : ['Paid', 'is-ready'])
                                : ((float) $purchase->paid_amount > 0 ? ['Part paid', 'is-received'] : ['To pay', 'is-received']);
                        @endphp
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->number }}</a>
                                <div class="small text-secondary">{{ $purchase->purchased_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</div>
                            </td>
                            <td>
                                {{ $purchase->supplier?->name }}
                                <div class="small text-secondary">{{ collect([$purchase->supplier_bill_number ? 'Bill '.$purchase->supplier_bill_number : null, $purchase->supplier?->mobile])->filter()->implode(' · ') }}</div>
                            </td>
                            <td class="num">{{ $purchase->lines_count }}<div class="small text-secondary">{{ $weight((string) ($purchase->lines_sum_gross_weight ?? '0')) }}</div></td>
                            <td class="num">{{ $money((string) $purchase->total) }}</td>
                            <td class="num">{{ $money((string) $purchase->paid_amount) }}</td>
                            <td class="num {{ (float) $due > 0 ? 'fw-semibold' : 'text-secondary' }}">{{ $money($due) }}</td>
                            <td><span class="order-status {{ $statusClass }}">{{ $statusText }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ $search !== '' || $show !== 'all' ? 'Nothing found.' : 'No purchases yet. Use Receive purchase when a supplier’s pieces come in.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $purchases->links() }}</div>
@endsection
