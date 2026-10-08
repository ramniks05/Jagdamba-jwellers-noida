@extends('layouts.app')

@section('title', $exchange->number)

@section('content')
    @php
        $metal = trim(($exchange->metalType?->name).' '.($exchange->purity?->name));
        $date = $exchange->exchanged_at?->timezone(config('app.timezone'))->format('d-m-Y');
        $lossWeight = number_format((float) $exchange->net_weight - (float) $exchange->melted_weight, 3, '.', '');
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('old-gold.index') }}"><i class="bi bi-arrow-left"></i> All old gold</a>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\OldGoldExchange::class)
                <a class="btn btn-outline-secondary" href="{{ route('old-gold.create') }}"><i class="bi bi-plus-lg"></i> New exchange</a>
            @endcan
            @if ((float) $pieceGrossLeft > 0)
                @can('create', App\Models\Item::class)
                    <a class="btn btn-outline-secondary" href="{{ route('items.create', ['from_old_gold' => $exchange->uuid]) }}"><i class="bi bi-gem"></i> Make a stock piece</a>
                @endcan
            @endif
            <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print voucher</button>
            @if ((float) $kept > 0 && ! $exchange->customer?->is_system)
                @can('create', App\Models\Sale::class)
                    <a class="btn btn-primary" href="{{ route('sales.create', ['customer' => $exchange->customer?->uuid]) }}"><i class="bi bi-receipt"></i> New bill for {{ $exchange->customer?->name }}</a>
                @endcan
            @endif
        </div>
    </div>

    <div class="card order-panel mb-3 no-print">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Old gold {{ $exchange->number }} · {{ $date }}</div>
                    <div class="order-panel-title">{{ $exchange->customer?->name }}</div>
                    <div class="text-secondary small">{{ $metal }} · {{ $weight((string) $exchange->gross_weight) }} gross · {{ $weight((string) $exchange->melted_weight) }} fine at {{ $money((string) $exchange->rate_per_gram) }} / g</div>
                </div>
                @if ($exchange->testing_result)
                    <div class="order-due"><i class="bi bi-patch-check"></i> {{ $exchange->testing_result }}</div>
                @endif
            </div>
            <div class="customer-stats mb-0 mt-3">
                <div class="customer-stat"><div class="stat-label">Old gold value</div><div>{{ $money((string) $exchange->exchange_value) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Paid to customer</div><div>{{ $money($refund) }}</div></div>
                <div class="customer-stat {{ (float) $kept > 0 ? 'is-due' : '' }}"><div class="stat-label">Kept on account</div><div>{{ $money($kept) }}</div></div>
            </div>
            @if ((float) $kept > 0)
                <p class="small text-secondary mb-0 mt-2">{{ $money($kept) }} stays to {{ $exchange->customer?->name }}’s credit. On their next bill it is taken off as “Old gold / credit”.</p>
            @endif
            <p class="small text-secondary mb-0 mt-2">
                <i class="bi bi-box-seam"></i>
                @if ($exchange->pieceMovements->isEmpty())
                    This old gold is in <a href="{{ route('old-gold.stock') }}">old gold stock</a>. If a piece is good to sell again, use “Make a stock piece”.
                @else
                    Made into stock:
                    @foreach ($exchange->pieceMovements as $movement)
                        @if ($movement->item)
                            <a href="{{ route('items.show', $movement->item) }}">{{ $movement->item->item_code }}</a>
                        @else
                            {{ $movement->party }}
                        @endif
                        {{ '('.$weight((string) $movement->gross_weight).')'.($loop->last ? '' : ',') }}
                    @endforeach
                    · the other {{ $weight($pieceGrossLeft) }} stays as <a href="{{ route('old-gold.stock') }}">old gold stock</a>.
                @endif
            </p>
        </div>
    </div>

    <article class="invoice-sheet">
        @include('commerce.partials.shop-document-head', ['kicker' => 'Old gold purchase voucher'])
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Received from</th>
                    <th>Voucher</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $exchange->customer?->name }}</strong>
                        <div>{{ $exchange->customer?->mobile ?: 'Mobile not recorded' }}</div>
                        @if ($exchange->customer?->address_line1)
                            <div>{{ collect([$exchange->customer->address_line1, $exchange->customer->city])->filter()->implode(', ') }}</div>
                        @endif
                    </td>
                    <td>
                        <div><span>Number</span><strong>{{ $exchange->number }}</strong></div>
                        <div><span>Date</span><strong>{{ $date }}</strong></div>
                        <div><span>Metal</span>{{ $metal }}</div>
                        <div><span>Testing</span>{{ $exchange->testing_result ?: '—' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="num">Gross</th>
                    <th class="num">Stone</th>
                    <th class="num">Net</th>
                    <th class="num">Loss</th>
                    <th class="num">Fine</th>
                    <th class="num">Rate / g</th>
                    <th class="num">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Old {{ strtolower($exchange->metalType?->name ?? 'gold') }} {{ $exchange->purity?->name }}</strong>
                        @if ($exchange->notes)
                            <div class="muted">{{ $exchange->notes }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $weight((string) $exchange->gross_weight) }}</td>
                    <td class="num">{{ $weight((string) $exchange->stone_weight) }}</td>
                    <td class="num">{{ $weight((string) $exchange->net_weight) }}</td>
                    <td class="num">{{ (float) $exchange->melting_loss_percent }}%<div class="muted">{{ $weight($lossWeight) }}</div></td>
                    <td class="num"><strong>{{ $weight((string) $exchange->melted_weight) }}</strong></td>
                    <td class="num">{{ $money((string) $exchange->rate_per_gram) }}</td>
                    <td class="num"><strong>{{ $money($metalValue) }}</strong></td>
                </tr>
            </tbody>
        </table>
        <div class="invoice-bottom">
            <div class="invoice-words">
                <div class="invoice-kicker">Value in words</div>
                <p>{{ $valueWords }}</p>
                @if ($exchange->payments->isNotEmpty())
                    <div class="invoice-kicker">Paid to customer</div>
                    @foreach ($exchange->payments as $payment)
                        <div>{{ $payment->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $payment->number }} · {{ $payment->method->label() }} {{ $money((string) $payment->amount) }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
                    @endforeach
                @endif
            </div>
            <table class="invoice-totals">
                <tr><td>Metal value</td><td>{{ $money($metalValue) }}</td></tr>
                @if ((float) $exchange->deduction_amount > 0)
                    <tr><td>Less deduction</td><td>− {{ $money((string) $exchange->deduction_amount) }}</td></tr>
                @endif
                <tr class="invoice-grand"><td>Old gold value</td><td>{{ $money((string) $exchange->exchange_value) }}</td></tr>
                <tr><td>Paid now</td><td>{{ $money($refund) }}</td></tr>
                <tr><td><strong>Kept on account</strong></td><td><strong>{{ $money($kept) }}</strong></td></tr>
            </table>
        </div>
        <footer class="invoice-foot">
            <div class="invoice-terms">
                <p>I declare that the above old {{ strtolower($exchange->metalType?->name ?? 'gold') }} belongs to me and I have sold it to {{ $company->displayName() }} of my own free will after seeing it weighed and tested. I accept the value shown.</p>
                @if ($footer !== '')
                    <p class="thanks">{{ $footer }}</p>
                @endif
                <div class="invoice-sign">
                    <div class="invoice-sign-space"></div>
                    <span>Customer signature</span>
                </div>
            </div>
            <div class="invoice-sign">
                <div>For {{ $company->displayName() }}</div>
                <img src="{{ $company->brandSignatureUrl() }}" alt="Authorised signature">
                <span>Authorised signatory</span>
            </div>
        </footer>
    </article>
@endsection
