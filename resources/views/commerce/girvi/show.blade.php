@extends('layouts.app')

@section('title', $pledge->number)

@php
    $percentLabel = rtrim(rtrim(number_format((float) $pledge->interest_percent, 2, '.', ''), '0'), '.');
    $loanLabel = $pledge->loan_percent !== null
        ? rtrim(rtrim(number_format((float) $pledge->loan_percent, 2, '.', ''), '0'), '.').'% of the gold value'
        : 'One loan amount';
    $open = $pledge->status === 'open';
    $given = $pledge->pledged_at?->timezone(config('app.timezone'))->format('d-m-Y');
    $released = $pledge->released_at?->timezone(config('app.timezone'))->format('d-m-Y');
@endphp

@section('content')
    @php
        $dueNote = $open
            ? 'Interest from '.$pledge->interest_from->format('d-m-Y').' · '.$months.' '.($months === 1 ? 'month' : 'months')
            : 'Released on '.$released;
        $steps = [
            ['Loan given', $given, 'done'],
            ['Interest paid', $receipts->where('narration', 'Girvi interest '.$pledge->number)->last()?->received_at?->timezone(config('app.timezone'))->format('d-m-Y'), (float) $pledge->interest_charged > 0 ? 'done' : ($open ? 'next' : '')],
            ['Released', $released, $open ? '' : 'done'],
        ];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('girvi.index') }}"><i class="bi bi-arrow-left"></i> All girvi</a>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print receipt</button>
        </div>
    </div>

    <div class="card order-panel mb-3 no-print">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Girvi {{ $pledge->number }} · {{ $pledge->description }}</div>
                    <div class="order-panel-title">
                        {{ $pledge->customer?->name }}
                        <span class="order-status is-{{ $pledge->status }}">{{ $open ? 'Gold in shop' : 'Released' }}</span>
                    </div>
                    <div class="text-secondary small">
                        {{ $metalWeights->map(fn ($net, $name) => $name.' '.$net)->implode(' · ') }} · value {{ $money((string) $pledge->gold_value) }} · {{ $percentLabel }}% a month
                    </div>
                </div>
                <div class="order-due"><i class="bi bi-calendar-event"></i> {{ $dueNote }}</div>
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
                <div class="customer-stat"><div class="stat-label">Loan given</div><div>{{ $money((string) $pledge->principal) }}</div></div>
                @if ($open)
                    <div class="customer-stat"><div class="stat-label">Interest due now</div><div>{{ $money($interest) }}</div></div>
                    <div class="customer-stat is-due"><div class="stat-label">To release today</div><div>{{ $money($release) }}</div></div>
                @else
                    <div class="customer-stat"><div class="stat-label">Interest collected</div><div>{{ $money((string) $pledge->interest_charged) }}</div></div>
                    <div class="customer-stat"><div class="stat-label">Released on</div><div>{{ $released }}</div></div>
                @endif
            </div>
        </div>
    </div>

    @if ($open)
        @can('update', $pledge)
            <form class="card due-pay mb-3 no-print" id="settle-form" method="POST" action="{{ route('girvi.settle', $pledge) }}" data-principal="{{ $pledge->principal }}" data-percent="{{ $pledge->interest_percent }}">
                @csrf
                <div class="card-body">
                    <div class="due-pay-head">
                        <div>
                            <div class="stat-label">Collect interest or release</div>
                            <div class="text-secondary small">Interest counted from {{ $pledge->interest_from->format('d-m-Y') }}. A part of a month is one full month.</div>
                        </div>
                        <div class="text-end">
                            <div class="small text-secondary">Interest <strong id="interest-figure"></strong> · to release</div>
                            <div class="due-pay-amount" id="release-figure"></div>
                        </div>
                    </div>
                    <div class="due-pay-row is-four">
                        <div>
                            <label class="form-label" for="months">Months</label>
                            <input class="form-control @error('months') is-invalid @enderror" id="months" name="months" value="{{ old('months', $months) }}" inputmode="numeric" required>
                            @error('months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="settle-payment">Amount ₹</label>
                            <input class="form-control @error('payment') is-invalid @enderror" id="settle-payment" name="payment" value="{{ old('payment') }}" inputmode="decimal" placeholder="Filled by the button">
                            @error('payment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="method">Paid by</label>
                            <select class="form-select" id="method" name="method">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="reference">Reference</label>
                            <input class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" placeholder="UPI ref, cheque no.">
                        </div>
                    </div>
                    <div class="due-pay-actions">
                        <button class="btn btn-outline-primary" name="action" value="interest" type="submit" id="interest-button"><i class="bi bi-cash-coin"></i> Take interest only</button>
                        <button class="btn btn-primary" name="action" value="release" type="submit" id="release-button"><i class="bi bi-unlock"></i> Release the gold</button>
                        <span class="small text-secondary">Interest only keeps the gold in girvi and starts the month count again from today.</span>
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
                        <div><span>Date</span><strong>{{ $given }}</strong></div>
                        <div><span>Status</span>{{ $open ? 'Gold is in the shop' : 'Released '.$released }}</div>
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
                @if ($receipts->isNotEmpty())
                    <div class="invoice-kicker mt-2">Received</div>
                    @foreach ($receipts as $payment)
                        <div>{{ $payment->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $payment->number }} · {{ $payment->method->label() }} {{ $money((string) $payment->amount) }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
                    @endforeach
                @endif
            </div>
            <table class="invoice-totals">
                <tr><td>Total value</td><td>{{ $money((string) $pledge->gold_value) }}</td></tr>
                <tr class="invoice-grand"><td>Loan given</td><td>{{ $money((string) $pledge->principal) }}</td></tr>
                <tr><td>Interest</td><td>{{ $percentLabel }}% / month</td></tr>
                @if ((float) $pledge->interest_charged > 0)
                    <tr><td>Interest collected</td><td>{{ $money((string) $pledge->interest_charged) }}</td></tr>
                @endif
                @if ($open)
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
                <img src="{{ $company->brandSignatureUrl() }}" alt="Authorised signature">
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
