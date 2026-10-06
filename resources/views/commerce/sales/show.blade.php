@extends('layouts.app')

@section('title', $sale->number)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4 no-print">
        <div>
            <h1 class="page-title h3 mb-1">{{ $sale->number }}</h1>
            <div class="text-secondary">{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</div>
        </div>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="fw-semibold">{{ $company->displayName() }}</div>
                    <div>{{ $company->formattedAddress() }}</div>
                    <div>GSTIN {{ $company->gstin ?: '—' }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">{{ $sale->customer?->name }}</div>
                    <div>{{ $sale->customer?->mobile }}</div>
                    <div>{{ $sale->branch?->name }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Piece</th><th>Net g</th><th>Rate / g</th><th>Metal</th><th>Wastage</th><th>Making</th><th>Stone</th><th>Amount</th></tr></thead>
                <tbody>
                    @foreach ($sale->lines as $line)
                        <tr>
                            <td>{{ $line->item_code }}<div class="small text-secondary">{{ $line->name }} · {{ $line->metal_name }} {{ $line->purity_name }}</div></td>
                            <td>{{ $weight((string) $line->net_weight) }}</td>
                            <td>{{ $money((string) $line->rate_per_gram) }}</td>
                            <td>{{ $money((string) $line->metal_amount) }}</td>
                            <td>{{ $money((string) $line->wastage_amount) }}</td>
                            <td>{{ $money((string) $line->making_amount) }}</td>
                            <td>{{ $money((string) $line->stone_amount) }}</td>
                            <td>{{ $money((string) $line->line_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white">Payments</div>
                <ul class="list-group list-group-flush">
                    @forelse ($sale->payments as $payment)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $payment->method->label() }} · {{ $payment->number }} {{ $payment->reference }}</span><span>{{ $money((string) $payment->amount) }}</span></li>
                    @empty
                        <li class="list-group-item">No payment on this bill.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>Items</span><span>{{ $money((string) $sale->lines_amount) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Discount</span><span>{{ $money((string) $sale->discount_amount) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>GST {{ $sale->tax_percent }}%</span><span>{{ $money((string) $sale->tax_amount) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Round off</span><span>{{ $money((string) $sale->round_off) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between fw-semibold"><span>Total</span><span>{{ $money((string) $sale->total) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Balance due</span><span>{{ $money($sale->balanceDue()) }}</span></li>
                </ul>
            </div>
        </div>
    </div>
    @can('create', App\Models\SaleReturn::class)
        @if ($sale->lines->contains(fn ($line) => ! $returned->contains($line->id)))
            <form class="card mt-4 no-print" method="POST" action="{{ route('sales.returns.store', $sale) }}">
                @csrf
                <div class="card-header bg-white">Return pieces</div>
                <div class="card-body">
                    @foreach ($sale->lines as $line)
                        @if (! $returned->contains($line->id))
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="lines[]" value="{{ $line->uuid }}" id="line-{{ $line->uuid }}">
                                <label class="form-check-label" for="line-{{ $line->uuid }}">{{ $line->item_code }} · {{ $money((string) $line->line_amount) }}</label>
                            </div>
                        @endif
                    @endforeach
                    <div class="mt-3" style="max-width: 16rem">
                        <label class="form-label">Refund</label>
                        <input class="form-control" name="refund" value="0">
                    </div>
                    <button class="btn btn-outline-primary mt-3" type="submit">Save credit note</button>
                </div>
            </form>
        @endif
    @endcan
@endsection
