@extends('layouts.app')

@section('title', $pledge->number)

@php
    $percentLabel = rtrim(rtrim(number_format((float) $pledge->interest_percent, 2, '.', ''), '0'), '.');
    $loanLabel = $pledge->loan_percent !== null
        ? rtrim(rtrim(number_format((float) $pledge->loan_percent, 2, '.', ''), '0'), '.').'% of the gold value'
        : 'One loan amount';
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
        <div>
            <h1 class="page-title h3 mb-1">{{ $pledge->number }}</h1>
            <div class="text-secondary">{{ $pledge->customer?->name }} · {{ $pledge->status === 'open' ? 'Open' : 'Released' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print receipt</button>
        </div>
    </div>

    @if ($pledge->status === 'open')
        @can('update', $pledge)
            <form class="card mb-3 no-print" id="settle-form" method="POST" action="{{ route('girvi.settle', $pledge) }}" data-principal="{{ $pledge->principal }}" data-percent="{{ $pledge->interest_percent }}">
                @csrf
                <div class="card-header bg-white">Interest and release</div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="months">Months to charge</label>
                        <input class="form-control" id="months" name="months" value="{{ old('months', $months) }}" required>
                        <div class="form-text">Counted from {{ $pledge->interest_from->format('d M Y') }}. A part month is one month.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="settle-payment">Amount received</label>
                        <input class="form-control" id="settle-payment" name="payment" value="{{ old('payment') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="method">Method</label>
                        <select class="form-select" id="method" name="method">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="reference">Reference</label>
                        <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}">
                    </div>
                    <div class="col-12">
                        <p class="mb-1">Interest now <strong id="interest-figure"></strong></p>
                        <p class="mb-3">To release the gold <strong id="release-figure"></strong></p>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-primary" name="action" value="interest" type="submit" id="interest-button">Take interest only</button>
                            <button class="btn btn-primary" name="action" value="release" type="submit" id="release-button">Release the gold</button>
                        </div>
                    </div>
                </div>
            </form>
        @endcan
    @endif

    <article class="invoice-sheet">
        @include('commerce.partials.shop-document-head', ['kicker' => 'Girvi receipt'])
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Customer</th>
                    <th>Girvi</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $pledge->customer?->name }}</strong>
                        <div>{{ $pledge->customer?->mobile ?: 'Mobile not recorded' }}</div>
                        @if ($pledge->customer?->address_line1)
                            <div>{{ collect([$pledge->customer->address_line1, $pledge->customer->city])->filter()->implode(', ') }}</div>
                        @endif
                    </td>
                    <td>
                        <div><span>Number</span><strong>{{ $pledge->number }}</strong></div>
                        <div><span>Date</span><strong>{{ $pledge->pledged_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</strong></div>
                        <div><span>Status</span>{{ $pledge->status === 'open' ? 'Gold is in the shop' : 'Released '.($pledge->released_at?->timezone(config('app.timezone'))->format('d-m-Y') ?? '') }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>Piece</th>
                    <th>Metal</th>
                    <th class="num">Gross</th>
                    <th class="num">Net</th>
                    <th class="num">Rate / g</th>
                    <th class="num">Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pledge->items as $item)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->description }}</strong></td>
                        <td>{{ $item->metalType?->name }} {{ $item->purity?->name }}</td>
                        <td class="num">{{ $weight((string) $item->gross_weight) }}</td>
                        <td class="num">{{ $weight((string) $item->net_weight) }}</td>
                        <td class="num">{{ $money((string) $item->rate_per_gram) }}</td>
                        <td class="num"><strong>{{ $money((string) $item->gold_value) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td class="num">1</td>
                        <td><strong>{{ $pledge->description }}</strong></td>
                        <td>{{ $pledge->metalType?->name }} {{ $pledge->purity?->name }}</td>
                        <td class="num">{{ $weight((string) $pledge->gross_weight) }}</td>
                        <td class="num">{{ $weight((string) $pledge->net_weight) }}</td>
                        <td class="num">{{ $money((string) $pledge->rate_per_gram) }}</td>
                        <td class="num"><strong>{{ $money((string) $pledge->gold_value) }}</strong></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="invoice-bottom">
            <div class="invoice-words">
                <div class="invoice-kicker">Loan in words</div>
                <p>{{ $loanWords }}</p>
                <div class="invoice-kicker">Cash given to the customer</div>
                <div>{{ $money((string) $pledge->principal) }} · {{ $loanLabel }}</div>
            </div>
            <table class="invoice-totals">
                <tr><td>Total value</td><td>{{ $money((string) $pledge->gold_value) }}</td></tr>
                <tr class="invoice-grand"><td>Loan given</td><td>{{ $money((string) $pledge->principal) }}</td></tr>
                <tr><td>Interest</td><td>{{ $percentLabel }}% / month</td></tr>
                @if ((float) $pledge->interest_charged > 0)
                    <tr><td>Interest collected</td><td>{{ $money((string) $pledge->interest_charged) }}</td></tr>
                @endif
                @if ($pledge->status === 'open')
                    <tr><td>Interest due now</td><td>{{ $money($interest) }} · {{ $months }} {{ $months === 1 ? 'month' : 'months' }}</td></tr>
                    <tr class="invoice-grand"><td>To release today</td><td>{{ $money($release) }}</td></tr>
                @endif
            </table>
        </div>
        <footer class="invoice-foot">
            <div class="invoice-terms">
                <p>Each piece above is kept on its own metal, karat, weight, and rate. The loan is on the total value. The pieces stay with the shop until the loan and the interest are paid. A part of a month is charged as one full month. Please keep this receipt and bring it to release them.</p>
                @if ($pledge->notes)
                    <p>{{ $pledge->notes }}</p>
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
                @if ($company->signature_path)
                    <img src="{{ '/storage/'.$company->signature_path }}" alt="Authorised signature">
                @else
                    <div class="invoice-sign-space"></div>
                @endif
                <span>Authorised signatory</span>
            </div>
        </footer>
    </article>
@endsection

@push('scripts')
    <script>
        const form = document.getElementById('settle-form');
        if (form) {
            const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
            const principal = Number(form.dataset.principal);
            const percent = Number(form.dataset.percent);
            const months = document.getElementById('months');
            const payment = document.getElementById('settle-payment');

            function round2(value) {
                return Math.round((value + Number.EPSILON) * 100) / 100;
            }

            function figures() {
                const count = Math.max(0, parseInt(months.value || '0', 10));
                const interest = round2(principal * percent / 100 * count);
                return { interest, release: round2(principal + interest) };
            }

            function renderSettle() {
                const amounts = figures();
                document.getElementById('interest-figure').textContent = money.format(amounts.interest);
                document.getElementById('release-figure').textContent = money.format(amounts.release);
            }

            document.getElementById('interest-button').addEventListener('click', () => {
                payment.value = figures().interest.toFixed(2);
            });
            document.getElementById('release-button').addEventListener('click', () => {
                payment.value = figures().release.toFixed(2);
            });
            form.addEventListener('input', renderSettle);
            renderSettle();
        }
    </script>
@endpush
