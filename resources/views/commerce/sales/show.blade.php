@extends('layouts.app')

@section('title', $sale->number)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="{{ route('sales.create') }}">New bill</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Print invoice</button>
    </div>
    @php
        $customerAddress = collect([
            $sale->customer?->address_line1,
            $sale->customer?->address_line2,
            $sale->customer?->city,
            $sale->customer?->state,
            $sale->customer?->postal_code,
        ])->filter()->implode(', ');
        $phones = collect([$company->phone, $company->mobile])->filter()->implode(' · ');
    @endphp
    <article class="invoice-sheet">
        <header class="invoice-head">
            <div class="invoice-brand">
                @if ($showLogo && $company->logo_path)
                    <img class="invoice-logo" src="{{ '/storage/'.$company->logo_path }}" alt="{{ $company->displayName() }}">
                @endif
                <div>
                    <div class="invoice-kicker">Tax invoice</div>
                    <h1>{{ $company->displayName() }}</h1>
                    <p>{{ $company->formattedAddress() }}</p>
                    @if ($phones !== '')
                        <p>Phone {{ $phones }}@if ($company->email) · {{ $company->email }}@endif</p>
                    @endif
                </div>
            </div>
            <div class="invoice-gstin">
                <span>GSTIN {{ $company->gstin ?: '—' }}</span>
                <span>PAN {{ $company->pan ?: '—' }}</span>
                <span>State {{ $company->state ?: '—' }}</span>
            </div>
        </header>

        <table class="invoice-parties">
            <tr>
                <th>Billed to</th>
                <th>Invoice</th>
            </tr>
            <tr>
                <td>
                    <strong>{{ $sale->customer?->name }}</strong>
                    <div>{{ $sale->customer?->mobile ?: 'Mobile not recorded' }}</div>
                    @if ($customerAddress !== '')
                        <div>{{ $customerAddress }}</div>
                    @endif
                    @if ($sale->customer?->gstin)
                        <div>GSTIN {{ $sale->customer->gstin }}</div>
                    @endif
                </td>
                <td>
                    <div><span>Number</span><strong>{{ $sale->number }}</strong></div>
                    <div><span>Date</span><strong>{{ $sale->sold_at->timezone(config('app.timezone'))->format('d-m-Y') }}</strong></div>
                    <div><span>Time</span>{{ $sale->sold_at->timezone(config('app.timezone'))->format('h:i A') }}</div>
                    <div><span>Branch</span>{{ $sale->branch?->name }}</div>
                </td>
            </tr>
        </table>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>Particulars</th>
                    <th>HUID</th>
                    <th class="num">Gross</th>
                    <th class="num">Net</th>
                    <th class="num">Rate / g</th>
                    <th class="num">Making</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->lines as $line)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td>
                            <strong>{{ $line->name }}</strong>
                            <div class="muted">{{ $line->item_code }} · {{ $line->metal_name }} {{ $line->purity_name }}</div>
                            @if ((float) $line->wastage_amount > 0 || (float) $line->stone_amount > 0)
                                <div class="muted">
                                    @if ((float) $line->wastage_amount > 0) Wastage {{ $money((string) $line->wastage_amount) }} @endif
                                    @if ((float) $line->stone_amount > 0) Stone {{ $money((string) $line->stone_amount) }} @endif
                                </div>
                            @endif
                        </td>
                        <td>{{ $line->item?->huid ?: '—' }}</td>
                        <td class="num">{{ $weight((string) $line->gross_weight) }}</td>
                        <td class="num">{{ $weight((string) $line->net_weight) }}</td>
                        <td class="num">{{ $money((string) $line->rate_per_gram) }}</td>
                        <td class="num">{{ $money((string) $line->making_amount) }}</td>
                        <td class="num"><strong>{{ $money((string) $line->line_amount) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="invoice-bottom">
            <div class="invoice-words">
                <div class="invoice-kicker">Amount in words</div>
                <p>{{ $amountWords }}</p>
                @if ($sale->payments->isNotEmpty())
                    <div class="invoice-kicker">Received</div>
                    @foreach ($sale->payments as $payment)
                        <div>{{ $payment->method->label() }} {{ $money((string) $payment->amount) }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
                    @endforeach
                @endif
            </div>
            <table class="invoice-totals">
                <tr>
                    <td>Taxable value</td>
                    <td>{{ $money((string) $sale->lines_amount) }}</td>
                </tr>
                @if ((float) $sale->discount_amount !== 0.0)
                    <tr>
                        <td>Discount</td>
                        <td>{{ $money((string) $sale->discount_amount) }}</td>
                    </tr>
                @endif
                @foreach ($taxes as $tax)
                    <tr>
                        <td>{{ $tax['label'] }}</td>
                        <td>{{ $money($tax['amount']) }}</td>
                    </tr>
                @endforeach
                @if ((float) $sale->round_off !== 0.0)
                    <tr>
                        <td>Round off</td>
                        <td>{{ $money((string) $sale->round_off) }}</td>
                    </tr>
                @endif
                <tr class="invoice-grand">
                    <td>Total</td>
                    <td>{{ $money((string) $sale->total) }}</td>
                </tr>
                <tr>
                    <td>Paid</td>
                    <td>{{ $money((string) $sale->paid_amount) }}</td>
                </tr>
                <tr>
                    <td>Balance due</td>
                    <td>{{ $money($sale->balanceDue()) }}</td>
                </tr>
            </table>
        </div>

        <footer class="invoice-foot">
            <div class="invoice-terms">
                @if ($terms !== '')
                    <p>{{ $terms }}</p>
                @endif
                @if ($footer !== '')
                    <p class="thanks">{{ $footer }}</p>
                @endif
            </div>
            <div class="invoice-sign">
                <div>For {{ $company->displayName() }}</div>
                @if ($company->signature_path)
                    <img src="{{ '/storage/'.$company->signature_path }}" alt="Authorised signature">
                @else
                    <div class="invoice-sign-space"></div>
                @endif
                <span>Authorised signatory</span>
            </div>
        </footer>
    </article>
    @can('create', App\Models\SaleReturn::class)
        @if ($sale->lines->contains(fn ($line) => ! $returned->contains($line->id)))
            <details class="card mt-4 no-print">
                <summary class="card-header bg-white">Return a piece later</summary>
                <form method="POST" action="{{ route('sales.returns.store', $sale) }}">
                    @csrf
                    <div class="card-body">
                        <p class="text-secondary">Use this only when the customer brings a piece back. It is not printed on the invoice.</p>
                        @foreach ($sale->lines as $line)
                            @if (! $returned->contains($line->id))
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="lines[]" value="{{ $line->uuid }}" id="line-{{ $line->uuid }}">
                                    <label class="form-check-label" for="line-{{ $line->uuid }}">{{ $line->item_code }} · {{ $line->name }} · {{ $money((string) $line->line_amount) }}</label>
                                </div>
                            @endif
                        @endforeach
                        <div class="mt-3" style="max-width: 16rem">
                            <label class="form-label">Cash refund</label>
                            <input class="form-control" name="refund" value="0">
                        </div>
                        <button class="btn btn-outline-primary mt-3" type="submit">Save credit note</button>
                    </div>
                </form>
            </details>
        @endif
    @endcan
@endsection
