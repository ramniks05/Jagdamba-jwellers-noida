@extends('layouts.app')

@section('title', $repair->number)

@php
    $statusLabel = [
        'received' => 'Received',
        'inspection' => 'Inspection',
        'repairing' => 'Repairing',
        'ready' => 'Ready to deliver',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ][$repair->status] ?? ucfirst($repair->status);
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
        <div>
            <h1 class="page-title h3 mb-1">{{ $repair->number }}</h1>
            <div class="text-secondary">{{ $statusLabel }}</div>
        </div>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print receipt</button>
    </div>

    @can('update', $repair)
        @if (in_array($repair->status, ['received', 'inspection', 'repairing'], true))
            <form class="d-flex flex-wrap gap-2 mb-3 no-print" method="POST" action="{{ route('repairs.status', $repair) }}">
                @csrf
                @if ($repair->status === 'received')
                    <button class="btn btn-primary" name="status" value="inspection" type="submit"><i class="bi bi-search"></i> Start inspection</button>
                @elseif ($repair->status === 'inspection')
                    <button class="btn btn-primary" name="status" value="repairing" type="submit"><i class="bi bi-tools"></i> Start repair</button>
                @else
                    <button class="btn btn-primary" name="status" value="ready" type="submit"><i class="bi bi-check2-circle"></i> Mark ready</button>
                @endif
                <button class="btn btn-outline-secondary" name="status" value="cancelled" type="submit">Cancel</button>
            </form>
        @endif
        @if ($repair->status === 'ready')
            <form class="card mb-3 no-print" id="deliver-form" method="POST" action="{{ route('repairs.deliver', $repair) }}" data-walkin="{{ $repair->customer?->is_system ? '1' : '0' }}">
                @csrf
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Deliver and collect</span>
                    <button class="btn btn-outline-secondary btn-sm" id="use-charge" type="button">Collect the full charge</button>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="final-charge">Final charge</label>
                        <input class="form-control" id="final-charge" name="final_charge" value="{{ old('final_charge', $repair->estimated_cost) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="repair-payment">Payment now</label>
                        <input class="form-control" id="repair-payment" name="payment" value="{{ old('payment', $repair->customer?->is_system ? $repair->estimated_cost : '0') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="pay-method">Method</label>
                        <select class="form-select" id="pay-method" name="method">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="pay-reference">Reference</label>
                        <input class="form-control" id="pay-reference" name="reference" value="{{ old('reference') }}">
                    </div>
                    <div class="col-12">
                        <p class="fw-semibold mb-2" id="deliver-note"></p>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-right"></i> Deliver</button>
                    </div>
                </div>
            </form>
        @endif
    @endcan

    <article class="invoice-sheet">
        <header class="invoice-head">
            <div class="invoice-kicker">Repair receipt</div>
            <h1>{{ $company->displayName() }}</h1>
            <p>{{ $company->formattedAddress() }}</p>
            @if ($company->phone || $company->mobile)
                <p>{{ $company->phone ?: $company->mobile }}</p>
            @endif
        </header>
        <table class="invoice-parties">
            <tbody>
                <tr>
                    <th>Customer</th>
                    <th>Repair</th>
                </tr>
                <tr>
                    <td>
                        <strong>{{ $repair->customer?->name }}</strong>
                        @if ($repair->customer?->mobile)
                            <div><span>Mobile</span><span>{{ $repair->customer->mobile }}</span></div>
                        @endif
                        @if ($repair->customer?->code)
                            <div><span>Code</span><span>{{ $repair->customer->code }}</span></div>
                        @endif
                    </td>
                    <td>
                        <div><span>Number</span><span>{{ $repair->number }}</span></div>
                        <div><span>Received</span><span>{{ $repair->received_at?->timezone(config('app.timezone'))->format('d M Y') }}</span></div>
                        <div><span>Status</span><span>{{ $statusLabel }}</span></div>
                        <div><span>Expected</span><span>{{ $repair->expected_on?->format('d M Y') ?: '—' }}</span></div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Piece</th>
                    <th>Repair</th>
                    <th>Weight</th>
                    <th class="num">Estimate</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ $repair->description }}
                        @if ($repair->item)
                            <div>{{ $repair->item->item_code }}</div>
                        @endif
                    </td>
                    <td>{{ $repair->problem }}</td>
                    <td>{{ $repair->gross_weight !== null ? $weight((string) $repair->gross_weight) : '—' }}</td>
                    <td class="num">{{ $money((string) $repair->estimated_cost) }}</td>
                </tr>
            </tbody>
        </table>
        <table class="invoice-totals">
            <tbody>
                @if ($repair->technician)
                    <tr><td>Karigar</td><td>{{ $repair->technician }}</td></tr>
                @endif
                @if ($repair->notes)
                    <tr><td>Notes</td><td>{{ $repair->notes }}</td></tr>
                @endif
                @if ($repair->final_charge !== null)
                    <tr><td>Final charge</td><td class="num">{{ $money((string) $repair->final_charge) }}</td></tr>
                    <tr><td>Paid</td><td class="num">{{ $money($paid) }}</td></tr>
                    <tr><td>Balance</td><td class="num">{{ $money($due) }}</td></tr>
                @endif
            </tbody>
        </table>
        <p class="mt-3 mb-4">Please bring this receipt when collecting the jewellery. The repair charge is collected on delivery.</p>
        <div class="d-flex justify-content-between">
            <div>Customer</div>
            <div class="text-end">For {{ $company->displayName() }}</div>
        </div>
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

            function round2(value) {
                return Math.round((value + Number.EPSILON) * 100) / 100;
            }

            function renderDue() {
                const total = Math.max(0, Number(charge.value || 0));
                if (walkIn) {
                    payment.value = total.toFixed(2);
                    payment.readOnly = true;
                }
                const paying = Math.max(0, Number(payment.value || 0));
                const due = round2(total - paying);
                if (paying > total) {
                    note.textContent = 'The payment cannot be more than the repair charge.';
                    return;
                }
                note.textContent = walkIn
                    ? 'A walk-in repair is paid in full. Collect ' + money.format(total) + '.'
                    : (due > 0 ? 'Balance left on the account: ' + money.format(due) + '.' : 'This delivery is fully paid.');
            }

            form.addEventListener('input', renderDue);
            document.getElementById('use-charge').addEventListener('click', () => {
                if (!payment.readOnly) payment.value = Math.max(0, Number(charge.value || 0)).toFixed(2);
                renderDue();
            });
            renderDue();
        }
    </script>
@endpush
