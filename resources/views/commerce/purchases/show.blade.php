@extends('layouts.app')

@section('title', $purchase->number)

@section('content')
    @php
        $date = $purchase->purchased_at?->timezone(config('app.timezone'))->format('d-m-Y');
        $returned = $purchase->returnedAmount();
        $due = $purchase->dueAmount();
        $openLines = $purchase->lines->filter(fn ($line) => ! $line->returnLine);
        $canSendBack = $openLines->contains(fn ($line) => $line->item?->status === App\Enums\ItemStatus::Available);
        if ($openLines->isEmpty()) {
            [$statusText, $statusClass] = ['Sent back', 'is-cancelled'];
        } elseif ((float) $due <= 0) {
            [$statusText, $statusClass] = ['Paid', 'is-ready'];
        } elseif ((float) $purchase->paid_amount > 0) {
            [$statusText, $statusClass] = ['Part paid', 'is-received'];
        } else {
            [$statusText, $statusClass] = ['To pay', 'is-received'];
        }
        $gross = $purchase->lines->sum(fn ($line) => (float) $line->gross_weight);
        $net = $purchase->lines->sum(fn ($line) => (float) $line->net_weight);
        $percent = rtrim(rtrim(number_format((float) $purchase->tax_percent, 2, '.', ''), '0'), '.');
        $trim = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('purchases.index') }}"><i class="bi bi-arrow-left"></i> All purchases</a>
        <div class="d-flex flex-wrap gap-2">
            @can('view', $purchase->supplier)
                <a class="btn btn-outline-secondary" href="{{ route('suppliers.show', $purchase->supplier) }}"><i class="bi bi-person-vcard"></i> Supplier account</a>
            @endcan
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            @can('create', App\Models\Purchase::class)
                <a class="btn btn-primary" href="{{ route('purchases.create', ['supplier' => $purchase->supplier?->uuid]) }}"><i class="bi bi-plus-lg"></i> New purchase from {{ $purchase->supplier?->name }}</a>
            @endcan
        </div>
    </div>

    <div class="card order-panel mb-3">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Purchase {{ $purchase->number }} · {{ $date }}@if ($purchase->supplier_bill_number) · their bill {{ $purchase->supplier_bill_number }}@endif</div>
                    <div class="order-panel-title">{{ $purchase->supplier?->name }}</div>
                    <div class="text-secondary small">
                        {{ $purchase->lines->count() }} {{ $purchase->lines->count() === 1 ? 'piece' : 'pieces' }} · {{ $weight((string) $gross) }} gross · {{ $weight((string) $net) }} net
                        @if ($purchase->supplier?->mobile) · {{ $purchase->supplier->mobile }} @endif
                    </div>
                </div>
                <span class="order-status {{ $statusClass }}">{{ $statusText }}</span>
            </div>
            <div class="customer-stats mb-0 mt-3">
                <div class="customer-stat"><div class="stat-label">Bill total</div><div>{{ $money((string) $purchase->total) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Paid</div><div>{{ $money((string) $purchase->paid_amount) }}</div></div>
                <div class="customer-stat {{ (float) $due > 0 ? 'is-due' : '' }}"><div class="stat-label">Due on this bill</div><div>{{ $money($due) }}</div></div>
            </div>
            <p class="small text-secondary mb-0 mt-2">
                @if ((float) $returned > 0)
                    {{ $money($returned) }} came off for pieces sent back. ·
                @endif
                @if ((float) $payable > 0)
                    In all you owe {{ $purchase->supplier?->name }} {{ $money($payable) }}.
                @elseif ((float) $payable < 0)
                    {{ $purchase->supplier?->name }} owes you {{ $money((string) abs((float) $payable)) }}.
                @else
                    Nothing is due to {{ $purchase->supplier?->name }} now.
                @endif
            </p>
        </div>
    </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Pieces</span>
                    @if ($canSendBack)
                        <span class="small text-secondary no-print">Tick a piece to send it back</span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                @if ($canSendBack)<th class="purchase-pick no-print"></th>@endif
                                <th>Piece</th>
                                <th class="num">Weight</th>
                                <th>Supplier rate</th>
                                <th class="num">Cost</th>
                                <th>Now</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchase->lines as $line)
                                @php
                                    $item = $line->item;
                                    $status = $item?->status;
                                    $pill = match ($status) {
                                        App\Enums\ItemStatus::Available => ['In stock', 'is-ready'],
                                        App\Enums\ItemStatus::Sold => ['Sold', 'is-delivered'],
                                        App\Enums\ItemStatus::SentBack => ['Sent back', 'is-cancelled'],
                                        App\Enums\ItemStatus::Repair => ['At repair', 'is-repairing'],
                                        default => [$status?->label() ?? '—', 'is-received'],
                                    };
                                @endphp
                                <tr>
                                    @if ($canSendBack)
                                        <td class="no-print">
                                            @if (! $line->returnLine && $status === App\Enums\ItemStatus::Available)
                                                <input class="form-check-input" type="checkbox" name="lines[]" value="{{ $line->uuid }}" form="send-back-form" aria-label="Send {{ $line->item_code }} back">
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        @if ($item)
                                            <a href="{{ route('items.show', $item) }}">{{ $line->item_code }}</a>
                                        @else
                                            {{ $line->item_code }}
                                        @endif
                                        <div class="small text-secondary">{{ $line->name }} · {{ $item?->metalType?->name }} {{ $item?->purity?->name }}@if ($item?->huid) · HUID {{ $item->huid }}@endif</div>
                                    </td>
                                    <td class="num">
                                        {{ $weight((string) ((float) $line->gross_weight > 0 ? $line->gross_weight : $item?->gross_weight)) }}
                                        <div class="small text-secondary fw-normal">net {{ $weight((string) $line->net_weight) }}</div>
                                    </td>
                                    <td>
                                        <span class="text-nowrap">{{ $money((string) $line->rate_per_gram) }} / g</span>
                                        @if ((float) $line->wastage_percent > 0 || (float) $line->labour_per_gram > 0 || (float) $line->stone_amount > 0)
                                            <div class="small text-secondary">
                                                {{ collect([
                                                    (float) $line->wastage_percent > 0 ? 'wastage '.$trim($line->wastage_percent).'%' : null,
                                                    (float) $line->labour_per_gram > 0 ? 'labour '.$money((string) $line->labour_per_gram).'/g' : null,
                                                    (float) $line->stone_amount > 0 ? 'stones '.$money((string) $line->stone_amount) : null,
                                                ])->filter()->implode(' · ') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="num">
                                        {{ $money((string) ((float) $line->cost_amount > 0 ? $line->cost_amount : $line->line_amount)) }}
                                        <div class="small text-secondary fw-normal">bill {{ $money((string) $line->line_amount) }}</div>
                                    </td>
                                    <td>
                                        <span class="order-status {{ $pill[1] }}">{{ $pill[0] }}</span>
                                        @if ($line->returnLine?->purchaseReturn)
                                            <div class="small text-secondary">{{ $line->returnLine->purchaseReturn->number }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body small text-secondary">
                    “Bill” is the piece’s amount on the supplier bill. “Cost” is its share of the bill total after discount and GST, and is saved as the piece’s cost price.
                </div>
                @if ($canSendBack)
                    @can('create', App\Models\Purchase::class)
                        <div class="card-body border-top no-print">
                            <form method="POST" action="{{ route('purchases.return', $purchase) }}" id="send-back-form" class="d-flex flex-wrap align-items-center gap-2">
                                @csrf
                                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-box-arrow-left"></i> Send ticked pieces back</button>
                                <span class="small text-secondary">They go out of stock and their cost comes off what you owe the supplier.</span>
                            </form>
                        </div>
                    @endcan
                @endif
            </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header bg-white">Bill</div>
                <div class="card-body bill-sums">
                    <div class="bill-block">
                        <div class="bill-row"><span>Pieces value</span><span>{{ $money((string) $purchase->lines_amount) }}</span></div>
                        @if ((float) $purchase->discount_amount > 0)
                            <div class="bill-row"><span>Discount</span><span>− {{ $money((string) $purchase->discount_amount) }}</span></div>
                        @endif
                        <div class="bill-row"><span>GST {{ $percent }}%</span><span>{{ $money((string) $purchase->tax_amount) }}</span></div>
                        @if ((float) $purchase->round_off !== 0.0)
                            <div class="bill-row bill-row-muted"><span>Round off</span><span>{{ $money((string) $purchase->round_off) }}</span></div>
                        @endif
                    </div>
                    <div class="bill-grand"><span>Bill total</span><strong>{{ $money((string) $purchase->total) }}</strong></div>
                    <div class="bill-block mt-2">
                        <div class="bill-row"><span>Paid</span><span>{{ $money((string) $purchase->paid_amount) }}</span></div>
                        @if ((float) $returned > 0)
                            <div class="bill-row"><span>Sent back</span><span>− {{ $money($returned) }}</span></div>
                        @endif
                        <div class="bill-row bill-row-sub"><span>Due</span><span>{{ $money($due) }}</span></div>
                    </div>
                </div>
            </div>
            @if ($purchase->notes)
                <div class="card mb-3">
                    <div class="card-body"><div class="stat-label">Notes</div>{{ $purchase->notes }}</div>
                </div>
            @endif
        </div>

        <div class="col-lg-6">

            @if ((float) $due > 0)
                @can('create', App\Models\Payment::class)
                    <div class="card mb-3 no-print">
                        <div class="card-header bg-white">Pay this bill</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('purchases.payments.store', $purchase) }}">
                                @csrf
                                <div class="row g-2">
                                    <div class="col-4">
                                        <select class="form-select" name="method" aria-label="Payment method">
                                            @foreach ($methods as $method)
                                                <option value="{{ $method->value }}" @selected(old('method', 'cash') === $method->value)>{{ $method->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4"><input class="form-control @error('amount') is-invalid @enderror" name="amount" value="{{ old('amount', number_format((float) $due, 2, '.', '')) }}" inputmode="decimal" aria-label="Amount" required></div>
                                    <div class="col-4"><input class="form-control" name="reference" value="{{ old('reference') }}" placeholder="Cheque / UTR" aria-label="Reference"></div>
                                </div>
                                <button class="btn btn-primary w-100 mt-2" type="submit"><i class="bi bi-cash-coin"></i> Save payment</button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

            <div class="card mb-3">
                <div class="card-header bg-white">Payments</div>
                <div class="card-body">
                    @forelse ($purchase->payments->sortBy('received_at') as $payment)
                        <div class="bill-row">
                            <span>{{ $payment->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $payment->method->label() }}@if ($payment->reference) · {{ $payment->reference }}@endif</span>
                            <span>{{ $money((string) $payment->amount) }}</span>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">No payment against this bill yet.</p>
                    @endforelse
                    @if ((float) $purchase->paid_amount > (float) $purchase->payments->sum('amount'))
                        <p class="text-secondary small mb-0 mt-2">{{ $money((string) ((float) $purchase->paid_amount - (float) $purchase->payments->sum('amount'))) }} was paid from the supplier account.</p>
                    @endif
                </div>
            </div>

            @if ($purchase->returns->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header bg-white">Sent back</div>
                    <div class="card-body">
                        @foreach ($purchase->returns as $return)
                            <div class="bill-row">
                                <span>{{ $return->returned_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $return->number }}</span>
                                <span>{{ $money((string) $return->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('send-back-form')?.addEventListener('submit', (event) => {
            const ticked = document.querySelectorAll('input[form="send-back-form"]:checked').length;
            if (ticked === 0) {
                event.preventDefault();
                alert('Tick the pieces that go back to the supplier.');
                return;
            }
            if (!confirm('Send ' + ticked + (ticked === 1 ? ' piece' : ' pieces') + ' back to the supplier? They go out of stock and cannot be sold.')) {
                event.preventDefault();
            }
        });
    </script>
@endpush
