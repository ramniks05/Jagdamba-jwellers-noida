@extends('layouts.app')

@section('title', $purchase->number)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-title h3 mb-1">{{ $purchase->number }}</h1>
            <div class="text-secondary">{{ $purchase->supplier?->name }} · {{ $purchase->purchased_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</div>
        </div>
        @can('create', App\Models\Purchase::class)
            <form method="POST" action="{{ route('purchases.return', $purchase) }}">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">Send back to supplier</button>
            </form>
        @endcan
    </div>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Piece</th><th>Net</th><th>Rate / g</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($purchase->lines as $line)
                        <tr>
                            <td>{{ $line->item_code }}<div class="small text-secondary">{{ $line->name }}</div></td>
                            <td>{{ $weight((string) $line->net_weight) }}</td>
                            <td>{{ $money((string) $line->rate_per_gram) }}</td>
                            <td>{{ $money((string) $line->line_amount) }}</td>
                            <td>{{ $line->item?->status?->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <ul class="list-group">
        <li class="list-group-item d-flex justify-content-between"><span>GST {{ $purchase->tax_percent }}%</span><span>{{ $money((string) $purchase->tax_amount) }}</span></li>
        <li class="list-group-item d-flex justify-content-between fw-semibold"><span>Total</span><span>{{ $money((string) $purchase->total) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Paid</span><span>{{ $money((string) $purchase->paid_amount) }}</span></li>
    </ul>
@endsection
