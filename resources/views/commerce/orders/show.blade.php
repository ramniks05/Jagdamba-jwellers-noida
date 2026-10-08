@extends('layouts.app')

@section('title', $order->number)

@php
    $metal = trim(($order->metalType?->name).' '.($order->purity?->name));
    $booked = $order->booked_at?->timezone(config('app.timezone'))->format('d-m-Y');
@endphp

@section('content')
    @php
        $held = $order->advanceHeld();
        $balance = (float) $estimate['balance'] > 0 ? $estimate['balance'] : '0';
        $daysLeft = $order->due_on ? (int) now()->startOfDay()->diffInDays($order->due_on->copy()->startOfDay(), false) : null;
        $dueNote = match (true) {
            $order->due_on === null => 'Delivery date not set',
            ! $order->isOpen() => 'Delivery by '.$order->due_on->format('d-m-Y'),
            $daysLeft > 1 => 'Delivery by '.$order->due_on->format('d-m-Y').' · in '.$daysLeft.' days',
            $daysLeft === 1 => 'Delivery tomorrow · '.$order->due_on->format('d-m-Y'),
            $daysLeft === 0 => 'Delivery today',
            default => abs($daysLeft).' '.(abs($daysLeft) === 1 ? 'day' : 'days').' late · was due '.$order->due_on->format('d-m-Y'),
        };
        $dueTone = $order->isOpen() && $daysLeft !== null ? ($daysLeft < 0 ? 'is-late' : ($daysLeft <= 1 ? 'is-soon' : '')) : '';
        $stepDate = fn ($date) => $date?->timezone(config('app.timezone'))->format('d-m-Y');
        $steps = $order->status === 'cancelled'
            ? [['Booked', $stepDate($order->booked_at), 'done'], ['Cancelled', $stepDate($order->cancelled_at), 'cancelled']]
            : [
                ['Booked', $stepDate($order->booked_at), 'done'],
                ['Ready', $stepDate($order->ready_at), $order->ready_at ? 'done' : ($order->status === 'booked' ? 'next' : '')],
                ['Delivered', $stepDate($order->delivered_at), $order->delivered_at ? 'done' : ($order->status === 'ready' ? 'next' : '')],
            ];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('orders.index') }}"><i class="bi bi-arrow-left"></i> All orders</a>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print slip</button>
            @if ($order->isOpen())
                @can('create', App\Models\Sale::class)
                    <a class="btn btn-primary" href="{{ route('sales.create', ['order' => $order->uuid]) }}"><i class="bi bi-receipt"></i> Make the bill</a>
                @endcan
            @elseif ($order->sale)
                <a class="btn btn-primary" href="{{ route('sales.show', $order->sale) }}"><i class="bi bi-receipt"></i> Bill {{ $order->sale->number }}</a>
            @endif
        </div>
    </div>

    <div class="card order-panel mb-3 no-print">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Order {{ $order->number }} · {{ $order->description }}</div>
                    <div class="order-panel-title">
                        {{ $order->customer?->name }}
                        <span class="order-status is-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                    </div>
                    <div class="text-secondary small">{{ $metal }} · about {{ $weight((string) $order->expected_weight) }} · rate locked {{ $money((string) $order->rate_per_gram) }} / g</div>
                </div>
                <div class="order-due {{ $dueTone }}"><i class="bi bi-calendar-event"></i> {{ $dueNote }}</div>
            </div>
            <ol class="order-steps">
                @foreach ($steps as [$label, $date, $state])
                    <li class="{{ $state ? 'is-'.$state : '' }}">
                        <span class="order-step-dot"></span>
                        <strong>{{ $label }}</strong>
                        <small>{{ $date ?? ($state === 'next' ? 'Next' : '—') }}</small>
                    </li>
                @endforeach
            </ol>
            <div class="customer-stats mb-0">
                <div class="customer-stat"><div class="stat-label">Estimated bill</div><div>{{ $money($estimate['total']) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Advance held</div><div>{{ $money($held) }}</div></div>
                <div class="customer-stat {{ $order->isOpen() ? 'is-due' : '' }}"><div class="stat-label">Balance at delivery (about)</div><div>{{ $order->isOpen() ? $money($balance) : '—' }}</div></div>
            </div>
        </div>
    </div>

    @if ($order->isOpen())
        @can('update', $order)
            <div class="row g-3 mb-3 no-print">
                <div class="col-lg-7">
                    <form class="card due-pay h-100" method="POST" action="{{ route('orders.advance', $order) }}">
                        @csrf
                        <div class="card-body">
                            <div class="due-pay-head">
                                <div>
                                    <div class="stat-label">Take more advance</div>
                                    <div class="text-secondary small">Saved on the order and taken off the final bill.</div>
                                </div>
                            </div>
                            <div class="due-pay-row">
                                <div>
                                    <label class="form-label" for="method">Paid by</label>
                                    <select class="form-select" id="method" name="method">
                                        @foreach ($methods as $method)
                                            <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="amount">Amount ₹</label>
                                    <input class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="Amount" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="reference">Reference</label>
                                    <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" placeholder="UPI ref, cheque no.">
                                </div>
                                <button class="btn btn-primary" type="submit"><i class="bi bi-cash-coin"></i> Save advance</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header bg-white">Next step</div>
                        <div class="card-body d-flex flex-column">
                            @if ($order->status === 'booked')
                                <form method="POST" action="{{ route('orders.ready', $order) }}">
                                    @csrf
                                    <p class="small text-secondary mb-2">When the piece comes back from the karigar, mark it ready and call the customer.</p>
                                    <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-check2-circle"></i> Mark ready</button>
                                </form>
                            @else
                                <p class="small text-secondary mb-2">The piece is ready. Add it to stock with its actual weight, then make the bill.</p>
                                @can('create', App\Models\Sale::class)
                                    <a class="btn btn-outline-primary w-100" href="{{ route('sales.create', ['order' => $order->uuid]) }}"><i class="bi bi-receipt"></i> Make the bill</a>
                                @endcan
                            @endif
                            <details class="order-cancel mt-auto">
                                <summary><i class="bi bi-x-circle"></i> Cancel this order</summary>
                                <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-2" onsubmit="return confirm('Cancel order {{ $order->number }}?')">
                                    @csrf
                                    <div class="row g-2 align-items-end">
                                        <div class="col-6">
                                            <label class="form-label" for="refund">Refund now ₹</label>
                                            <input class="form-control" id="refund" name="refund" value="{{ old('refund', '0') }}" inputmode="decimal">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" for="refund-method">Paid by</label>
                                            <select class="form-select" id="refund-method" name="method">
                                                @foreach ($methods as $method)
                                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-text">Advance held {{ $money($held) }}. Whatever you do not refund stays on the customer’s account for the next bill.</div>
                                    <button class="btn btn-outline-danger w-100 mt-2" type="submit">Cancel order</button>
                                </form>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    @endif

    <article class="invoice-sheet">
        @include('commerce.partials.shop-document-head', ['kicker' => 'Order booking slip'])
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Customer</th>
                    <th>Order</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $order->customer?->name }}</strong>
                        <div>{{ $order->customer?->mobile ?: 'Mobile not recorded' }}</div>
                        @if ($order->customer?->address_line1)
                            <div>{{ collect([$order->customer->address_line1, $order->customer->city])->filter()->implode(', ') }}</div>
                        @endif
                    </td>
                    <td>
                        <div><span>Number</span><strong>{{ $order->number }}</strong></div>
                        <div><span>Booked</span><strong>{{ $booked }}</strong></div>
                        <div><span>Delivery by</span>{{ $order->due_on?->format('d-m-Y') ?? 'To be told' }}</div>
                        <div><span>Status</span>{{ $order->statusLabel() }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Piece</th>
                    <th>Metal</th>
                    <th class="num">Expected weight</th>
                    <th class="num">Rate locked / g</th>
                    <th class="num">Metal value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $order->description }}</strong>
                        @if ($order->design_notes)
                            <div class="muted">{{ $order->design_notes }}</div>
                        @endif
                    </td>
                    <td>{{ $metal }}</td>
                    <td class="num">{{ $weight((string) $order->expected_weight) }}</td>
                    <td class="num">{{ $money((string) $order->rate_per_gram) }}</td>
                    <td class="num"><strong>{{ $money($estimate['gold']) }}</strong></td>
                </tr>
            </tbody>
        </table>
        <div class="invoice-bottom">
            <div class="invoice-words">
                <div class="invoice-kicker">Advance in words</div>
                <p>{{ $advanceWords }}</p>
                @php($received = $order->payments->where('direction', 'in'))
                @if ($received->isNotEmpty())
                    <div class="invoice-kicker">Advance received</div>
                    @foreach ($received as $payment)
                        <div>{{ $payment->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $payment->number }} · {{ $payment->method->label() }} {{ $money((string) $payment->amount) }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
                    @endforeach
                @endif
                @foreach ($order->payments->where('direction', 'out') as $payment)
                    <div class="invoice-kicker mt-2">Refunded</div>
                    <div>{{ $payment->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $payment->number }} · {{ $payment->method->label() }} {{ $money((string) $payment->amount) }}</div>
                @endforeach
            </div>
            <table class="invoice-totals">
                <tr><td>Metal value</td><td>{{ $money($estimate['gold']) }}</td></tr>
                @if ((float) $estimate['making'] > 0)
                    <tr><td>{{ ['inside' => 'Making (in jewellery GST)', 'separate' => 'Making (own GST)', 'processing' => 'Processing charge (no GST)'][$estimate['mode']] ?? 'Making' }}</td><td>{{ $money($estimate['making']) }}</td></tr>
                @endif
                @if ((float) $estimate['tax'] > 0)
                    <tr><td>GST {{ (float) $gstPercent }}% on metal{{ $estimate['mode'] === 'inside' && (float) $estimate['making'] > 0 ? ' + making' : '' }}</td><td>{{ $money($estimate['tax']) }}</td></tr>
                @endif
                @if ((float) $estimate['making_tax'] > 0)
                    <tr><td>GST {{ (float) $makingGstPercent }}% on making</td><td>{{ $money($estimate['making_tax']) }}</td></tr>
                @endif
                @if ((float) $estimate['round_off'] !== 0.0)
                    <tr><td>Round off</td><td>{{ $money($estimate['round_off']) }}</td></tr>
                @endif
                <tr class="invoice-grand"><td>Estimated bill</td><td>{{ $money($estimate['total']) }}</td></tr>
                <tr><td>Advance paid</td><td>{{ $money($held) }}</td></tr>
                @if ($order->isOpen())
                    <tr><td><strong>Balance at delivery (about)</strong></td><td><strong>{{ $money($balance) }}</strong></td></tr>
                @endif
            </table>
        </div>
        <footer class="invoice-foot">
            <div class="invoice-terms">
                <p>The {{ $metal }} rate is locked at {{ $money((string) $order->rate_per_gram) }} per gram from {{ $booked }}. The final bill uses the actual weight of the finished piece at this rate, with {{ $estimate['mode'] === 'processing' ? 'the processing charge' : 'making' }} and GST. The advance is taken off the final bill. Please bring this slip at delivery.</p>
                @if ($order->status === 'cancelled')
                    <p>This order was cancelled on {{ $order->cancelled_at?->timezone(config('app.timezone'))->format('d-m-Y') }}.@if ((float) $held > 0) {{ $money($held) }} stays on the customer’s account.@endif</p>
                @endif
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
