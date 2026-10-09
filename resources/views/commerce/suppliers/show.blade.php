@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
    @php
        $owes = (float) $payable > 0;
        $advance = (float) $payable < 0;
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a class="btn btn-outline-secondary" href="{{ route('suppliers.index') }}"><i class="bi bi-arrow-left"></i> Suppliers</a>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $supplier)
                <a class="btn btn-outline-secondary" href="{{ route('suppliers.edit', $supplier) }}"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @if ($supplier->is_active)
                @can('create', App\Models\Purchase::class)
                    <a class="btn btn-primary" href="{{ route('purchases.create', ['supplier' => $supplier->uuid]) }}"><i class="bi bi-plus-lg"></i> New purchase</a>
                @endcan
            @endif
        </div>
    </div>

    <div class="card order-panel mb-3">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">Supplier {{ $supplier->code }}</div>
                    <h1 class="order-panel-title h3 mb-1">{{ $supplier->name }}</h1>
                    <div class="text-secondary">{{ collect([$supplier->contact_name, $supplier->mobile, $supplier->email])->filter()->join(' · ') ?: 'No contact saved' }}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @include('masters.partials.status', ['active' => $supplier->is_active])
                    <span class="order-status {{ $supplier->kyc_status === App\Enums\KycStatus::Verified ? 'is-ready' : ($supplier->kyc_status === App\Enums\KycStatus::Rejected ? 'is-cancelled' : 'is-closed') }}">KYC {{ $supplier->kyc_status->label() }}</span>
                </div>
            </div>
            <div class="customer-stats mt-3">
                <div class="customer-stat {{ $owes ? 'is-due' : '' }}">
                    <div class="stat-label">{{ $advance ? 'Advance with them' : 'We owe' }}</div>
                    {{ $money(ltrim($payable, '-')) }}
                </div>
                <div class="customer-stat">
                    <div class="stat-label">Purchases</div>
                    {{ $purchaseCount }}
                </div>
                <div class="customer-stat">
                    <div class="stat-label">Bought in all</div>
                    {{ $money($boughtTotal) }}
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>Purchases</span>
                    @if ($purchaseCount > $purchases->count())
                        <a class="small" href="{{ route('purchases.index', ['search' => $supplier->name]) }}">See all {{ $purchaseCount }}</a>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Purchase</th>
                                <th class="num">Pieces</th>
                                <th class="num">Total</th>
                                <th class="num">Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                @php
                                    $due = $purchase->dueAmount();
                                    [$statusText, $statusClass] = (float) $due <= 0
                                        ? ((float) $purchase->returnedAmount() >= (float) $purchase->total && (float) $purchase->total > 0 ? ['Sent back', 'is-cancelled'] : ['Paid', 'is-ready'])
                                        : ((float) $purchase->paid_amount > 0 ? ['Part paid', 'is-received'] : ['To pay', 'is-received']);
                                @endphp
                                <tr>
                                    <td>
                                        <a class="fw-semibold" href="{{ route('purchases.show', $purchase) }}">{{ $purchase->number }}</a>
                                        <div class="small text-secondary">{{ $purchase->purchased_at?->timezone(config('app.timezone'))->format('d-m-Y') }}{{ $purchase->supplier_bill_number ? ' · Bill '.$purchase->supplier_bill_number : '' }}</div>
                                    </td>
                                    <td class="num">{{ $purchase->lines_count }}</td>
                                    <td class="num">{{ $money((string) $purchase->total) }}</td>
                                    <td class="num {{ (float) $due > 0 ? 'text-danger fw-semibold' : 'text-secondary' }}">{{ (float) $due > 0 ? $money($due) : '—' }}</td>
                                    <td><span class="order-status {{ $statusClass }}">{{ $statusText }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No purchases from this supplier yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">Account</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Details</th>
                                <th class="num">Bill / return</th>
                                <th class="num">Paid</th>
                                <th class="num">We owe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                @php($isCredit = $entry->direction === App\Enums\LedgerDirection::Credit)
                                <tr>
                                    <td class="text-nowrap">{{ $entry->occurred_at->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                                    <td>{{ $entry->narration }}</td>
                                    <td class="num">{{ $isCredit ? $money((string) $entry->amount) : '' }}</td>
                                    <td class="num">{{ $isCredit ? '' : $money((string) $entry->amount) }}</td>
                                    <td class="num fw-semibold">{{ $money($balances[$entry->id]) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">Nothing in this account yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4 bill-side">
            @can('create', App\Models\Payment::class)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-cash-coin"></i> Pay supplier</div>
                        <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" for="pay-amount">Amount</label>
                                    <input class="form-control @error('amount') is-invalid @enderror" id="pay-amount" name="amount" value="{{ old('amount', $owes ? number_format((float) $payable, 2, '.', '') : '') }}" inputmode="decimal" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="pay-method">Paid by</label>
                                    <select class="form-select" id="pay-method" name="method">
                                        @foreach ($methods as $method)
                                            <option value="{{ $method->value }}" @selected(old('method', 'cash') === $method->value)>{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="pay-reference">Reference</label>
                                    <input class="form-control" id="pay-reference" name="reference" value="{{ old('reference') }}" placeholder="Cheque / UTR number">
                                </div>
                            </div>
                            <div class="form-text">Goes against the oldest unpaid bills first.</div>
                            <button class="btn btn-primary w-100 mt-2" type="submit"><i class="bi bi-check2"></i> Save payment</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-body">
                    <div class="weigh-section-title"><i class="bi bi-person-vcard"></i> Details</div>
                    <div class="bill-sums">
                        <div class="bill-row"><span>Address</span><span class="text-end">{{ collect([$supplier->address_line1, $supplier->address_line2, $supplier->city, $supplier->state, $supplier->postal_code])->filter()->join(', ') ?: '—' }}</span></div>
                        <div class="bill-row"><span>GSTIN</span><span>{{ $supplier->gstin ?: '—' }}</span></div>
                        <div class="bill-row"><span>PAN</span><span>{{ $supplier->pan ?: '—' }}</span></div>
                        <div class="bill-row"><span>Bank</span><span class="text-end">{{ $supplier->bank_name ?: '—' }}@if ($supplier->account_number)<br><span class="small">{{ $supplier->account_number }} {{ $supplier->ifsc }}</span>@endif</span></div>
                    </div>
                    @if ($supplier->notes)
                        <p class="small text-secondary mt-2 mb-0">{{ $supplier->notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
