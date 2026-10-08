@extends('layouts.app')

@section('title', $repair->number)

@section('content')
    @php
        $received = $repair->received_at?->timezone(config('app.timezone'))->format('d-m-Y');
        $walkIn = (bool) $repair->customer?->is_system;
        $daysLeft = $repair->expected_on ? (int) now()->startOfDay()->diffInDays($repair->expected_on->copy()->startOfDay(), false) : null;
        $dueNote = match (true) {
            $repair->status === 'delivered' => 'Delivered on '.$repair->delivered_at?->timezone(config('app.timezone'))->format('d-m-Y'),
            $repair->status === 'cancelled' => 'Cancelled',
            $repair->status === 'ready' => 'Ready · call the customer to collect',
            $repair->expected_on === null => 'Ready date not set',
            $daysLeft > 1 => 'Ready by '.$repair->expected_on->format('d-m-Y').' · in '.$daysLeft.' days',
            $daysLeft === 1 => 'Ready by tomorrow · '.$repair->expected_on->format('d-m-Y'),
            $daysLeft === 0 => 'Ready by today',
            default => abs($daysLeft).' '.(abs($daysLeft) === 1 ? 'day' : 'days').' late · was due '.$repair->expected_on->format('d-m-Y'),
        };
        $dueTone = in_array($repair->status, ['received', 'inspection', 'repairing'], true) && $daysLeft !== null
            ? ($daysLeft < 0 ? 'is-late' : ($daysLeft <= 1 ? 'is-soon' : ''))
            : '';
        $flow = ['received' => 'Received', 'inspection' => 'Inspection', 'repairing' => 'Repairing', 'ready' => 'Ready', 'delivered' => 'Delivered'];
        $position = array_search($repair->status, array_keys($flow), true);
        $steps = [];
        if ($repair->status === 'cancelled') {
            $steps = [['Received', $received, 'done'], ['Cancelled', $repair->updated_at?->timezone(config('app.timezone'))->format('d-m-Y'), 'cancelled']];
        } else {
            foreach (array_values($flow) as $index => $label) {
                $date = match ($index) {
                    0 => $received,
                    4 => $repair->delivered_at?->timezone(config('app.timezone'))->format('d-m-Y'),
                    default => null,
                };
                $state = $index <= $position ? 'done' : ($index === $position + 1 ? 'next' : '');
                $steps[] = [$label, $date ?? ($state === 'done' ? 'Done' : null), $state];
            }
        }
        $nextStep = [
            'received' => ['inspection', 'Start inspection', 'bi-search', 'Check the piece with the customer. Note stones, marks and weight.'],
            'inspection' => ['repairing', 'Start repair', 'bi-tools', 'Send the piece to the karigar once the work and estimate are agreed.'],
            'repairing' => ['ready', 'Mark ready', 'bi-check2-circle', 'When the piece comes back from the karigar, weigh it, mark it ready and call the customer.'],
        ][$repair->status] ?? null;
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('repairs.index') }}"><i class="bi bi-arrow-left"></i> All repairs</a>
        <div class="d-flex flex-wrap gap-2">
            @if (! $walkIn)
                <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send to customer</a>
            @endif
            <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print receipt</button>
        </div>
    </div>

    <div class="card order-panel mb-3 no-print">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Repair {{ $repair->number }} · {{ $repair->description }}</div>
                    <div class="order-panel-title">
                        {{ $repair->customer?->name }}
                        <span class="order-status is-{{ $repair->status }}">{{ $repair->statusLabel() }}</span>
                    </div>
                    <div class="text-secondary small">{{ $repair->problem }} · {{ $weight((string) $repair->gross_weight) }} received @if ($repair->technician) · Karigar {{ $repair->technician }} @endif</div>
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
                <div class="customer-stat"><div class="stat-label">Estimate</div><div>{{ $money((string) $repair->estimated_cost) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Repair charge</div><div>{{ $repair->final_charge !== null ? $money((string) $repair->final_charge) : 'On delivery' }}</div></div>
                <div class="customer-stat {{ (float) $due > 0 ? 'is-due' : '' }}"><div class="stat-label">{{ $repair->final_charge !== null ? 'Balance on account' : 'Paid' }}</div><div>{{ $repair->final_charge !== null ? $money($due) : $money($paid) }}</div></div>
            </div>
        </div>
    </div>

    @can('update', $repair)
        @if ($nextStep)
            <div class="card mb-3 no-print">
                <div class="card-header bg-white">Next step</div>
                <div class="card-body">
                    <p class="small text-secondary mb-2">{{ $nextStep[3] }}</p>
                    <form class="d-flex flex-wrap gap-2" method="POST" action="{{ route('repairs.status', $repair) }}">
                        @csrf
                        <button class="btn btn-primary" name="status" value="{{ $nextStep[0] }}" type="submit"><i class="bi {{ $nextStep[2] }}"></i> {{ $nextStep[1] }}</button>
                        @if ($nextStep[0] !== 'ready')
                            <button class="btn btn-outline-primary" name="status" value="ready" type="submit"><i class="bi bi-check2-circle"></i> Already done · mark ready</button>
                        @endif
                    </form>
                    <details class="order-cancel mt-3">
                        <summary><i class="bi bi-x-circle"></i> Cancel this repair</summary>
                        <form method="POST" action="{{ route('repairs.status', $repair) }}" class="mt-2" onsubmit="return confirm('Cancel repair {{ $repair->number }}? Give the piece back to the customer.')">
                            @csrf
                            <p class="form-text mt-0">Use this when the customer takes the piece back without repair. Nothing is charged.@if ($repair->item) Our stock piece {{ $repair->item->item_code }} goes back to stock.@endif</p>
                            <button class="btn btn-outline-danger" name="status" value="cancelled" type="submit">Cancel repair</button>
                        </form>
                    </details>
                </div>
            </div>
        @endif
        @if ($repair->status === 'ready')
            <form class="card due-pay mb-3 no-print" id="deliver-form" method="POST" action="{{ route('repairs.deliver', $repair) }}" data-walkin="{{ $walkIn ? '1' : '0' }}">
                @csrf
                <div class="card-body">
                    <div class="due-pay-head">
                        <div>
                            <div class="stat-label">Deliver and collect</div>
                            <div class="text-secondary small">Estimate {{ $money((string) $repair->estimated_cost) }}. Enter the actual repair charge and what the customer pays now.</div>
                        </div>
                        <div class="due-pay-amount" id="deliver-total">{{ $money((string) $repair->estimated_cost) }}</div>
                    </div>
                    <div class="due-pay-row repair-deliver-row">
                        <div>
                            <label class="form-label" for="final-charge">Repair charge ₹</label>
                            <input class="form-control @error('final_charge') is-invalid @enderror" id="final-charge" name="final_charge" value="{{ old('final_charge', $repair->estimated_cost) }}" inputmode="decimal" required>
                            @error('final_charge')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="repair-payment">Paying now ₹</label>
                            <input class="form-control @error('payment') is-invalid @enderror" id="repair-payment" name="payment" value="{{ old('payment', $repair->estimated_cost) }}" inputmode="decimal">
                            @error('payment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="pay-method">Paid by</label>
                            <select class="form-select" id="pay-method" name="method">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="pay-reference">Reference</label>
                            <input class="form-control" id="pay-reference" name="reference" value="{{ old('reference') }}" maxlength="80" placeholder="UPI ref, cheque no.">
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-right"></i> Deliver</button>
                    </div>
                    <div class="small fw-semibold mt-2" id="deliver-note"></div>
                </div>
            </form>
        @endif
    @endcan

    <article class="invoice-sheet">
        @include('commerce.partials.shop-document-head', ['kicker' => 'Repair receipt'])
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Customer</th>
                    <th>Repair</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $repair->customer?->name }}</strong>
                        <div>{{ $repair->customer?->mobile ?: 'Mobile not recorded' }}</div>
                        @if ($repair->customer?->address_line1)
                            <div>{{ collect([$repair->customer->address_line1, $repair->customer->city])->filter()->implode(', ') }}</div>
                        @endif
                    </td>
                    <td>
                        <div><span>Number</span><strong>{{ $repair->number }}</strong></div>
                        <div><span>Received</span><strong>{{ $received }}</strong></div>
                        @if ($repair->delivered_at)
                            <div><span>Delivered</span><strong>{{ $repair->delivered_at->timezone(config('app.timezone'))->format('d-m-Y') }}</strong></div>
                        @else
                            <div><span>Ready by</span>{{ $repair->expected_on?->format('d-m-Y') ?? 'To be told' }}</div>
                        @endif
                        <div><span>Status</span>{{ $repair->statusLabel() }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Piece</th>
                    <th>Repair</th>
                    <th class="num">Weight received</th>
                    <th class="num">Estimate</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $repair->description }}</strong>
                        @if ($repair->item)
                            <div class="muted">Our stock {{ $repair->item->item_code }}</div>
                        @endif
                        @if ($repair->notes)
                            <div class="muted">{{ $repair->notes }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $repair->problem }}
                        @if ($repair->technician)
                            <div class="muted">Karigar {{ $repair->technician }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $weight((string) $repair->gross_weight) }}</td>
                    <td class="num"><strong>{{ $money((string) $repair->estimated_cost) }}</strong></td>
                </tr>
            </tbody>
        </table>
        <div class="invoice-bottom">
            <div class="invoice-words">
                @if ($payments->isNotEmpty())
                    <div class="invoice-kicker">Received</div>
                    @foreach ($payments as $payment)
                        <div>{{ $payment->received_at?->timezone(config('app.timezone'))->format('d-m-Y') }} · {{ $payment->number }} · {{ $payment->method->label() }} {{ $money((string) $payment->amount) }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
                    @endforeach
                @else
                    <div class="invoice-kicker">Charge</div>
                    <p>{{ $repair->final_charge !== null ? 'Nothing received yet.' : 'The repair charge is collected on delivery.' }}</p>
                @endif
            </div>
            <table class="invoice-totals">
                <tr><td>Estimate</td><td>{{ $money((string) $repair->estimated_cost) }}</td></tr>
                @if ($repair->final_charge !== null)
                    <tr class="invoice-grand"><td>Repair charge</td><td>{{ $money((string) $repair->final_charge) }}</td></tr>
                    <tr><td>Paid</td><td>{{ $money($paid) }}</td></tr>
                    <tr><td><strong>Balance</strong></td><td><strong>{{ $money($due) }}</strong></td></tr>
                @endif
            </table>
        </div>
        <footer class="invoice-foot">
            <div class="invoice-terms">
                @if ($repair->status === 'delivered')
                    <p>The piece received weighing {{ $weight((string) $repair->gross_weight) }} was returned to the customer after repair.</p>
                @else
                    <p>The piece was received weighing {{ $weight((string) $repair->gross_weight) }}. The estimate may change after inspection; the final charge is collected on delivery. Please bring this receipt when collecting the jewellery.</p>
                @endif
                @if ($repair->status === 'cancelled')
                    <p>This repair was cancelled and the piece returned without charge.</p>
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
        const form = document.getElementById('deliver-form');
        if (form) {
            const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
            const walkIn = form.dataset.walkin === '1';
            const charge = document.getElementById('final-charge');
            const payment = document.getElementById('repair-payment');
            const note = document.getElementById('deliver-note');
            const total = document.getElementById('deliver-total');
            let paymentTouched = Number(payment.value || 0) !== Number(charge.value || 0);

            function round2(value) {
                return Math.round((value + Number.EPSILON) * 100) / 100;
            }

            function renderDue() {
                const amount = Math.max(0, Number(charge.value || 0));
                total.textContent = money.format(amount);
                if (walkIn || !paymentTouched) payment.value = amount.toFixed(2);
                payment.readOnly = walkIn;
                const paying = Math.max(0, Number(payment.value || 0));
                const due = round2(amount - paying);
                note.classList.toggle('text-danger', paying > amount);
                if (paying > amount) {
                    note.textContent = 'The payment cannot be more than the repair charge.';
                    return;
                }
                note.textContent = walkIn
                    ? 'A walk-in repair is paid in full. Collect ' + money.format(amount) + '.'
                    : (due > 0 ? money.format(due) + ' stays on the customer’s account.' : 'Fully paid at delivery.');
            }

            charge.addEventListener('input', renderDue);
            payment.addEventListener('input', () => {
                paymentTouched = true;
                renderDue();
            });
            renderDue();
        }
    </script>
@endpush
