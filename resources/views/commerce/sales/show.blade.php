@extends('layouts.app')

@section('title', $sale->number)

@section('content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('sales.create') }}">New bill</a>
        <div class="d-flex flex-wrap gap-2">
            @if ($whatsappReady)
                <form method="POST" action="{{ route('sales.whatsapp.store', $sale) }}" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button class="btn btn-outline-success" type="submit" @disabled($whatsappNumber === null) @if ($whatsappNumber === null) title="Add the customer's mobile number first" @endif>
                        <i class="bi bi-whatsapp"></i> {{ $whatsappMessages->isEmpty() ? 'Send on WhatsApp' : 'Send again on WhatsApp' }}
                    </button>
                </form>
                <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener" title="Open WhatsApp on this device instead"><i class="bi bi-box-arrow-up-right"></i></a>
            @else
                <a class="btn btn-outline-success" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send on WhatsApp</a>
            @endif
            <button class="btn btn-primary" type="button" onclick="window.print()">Print invoice</button>
        </div>
    </div>
    @if ($whatsappMessages->isNotEmpty())
        <div class="card mb-3 no-print wa-log">
            <div class="card-body py-2">
                <div class="stat-label mb-1"><i class="bi bi-whatsapp"></i> WhatsApp</div>
                @foreach ($whatsappMessages as $message)
                    <div class="wa-log-row">
                        <span class="badge wa-status-{{ $message->status }}">{{ $message->statusLabel() }}</span>
                        <span>+{{ $message->recipient }}</span>
                        <span class="text-secondary">{{ ($message->status_at ?? $message->created_at)->format('d-m-Y h:i A') }}{{ $message->user ? ' · '.$message->user->name : '' }}</span>
                        @if ($message->status === 'failed' && $message->error)
                            <span class="text-danger small">{{ $message->error }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @php($due = $sale->balanceDue())
    @if ((float) $due > 0)
        @can('create', App\Models\Payment::class)
            <form class="card due-pay mb-3 no-print" method="POST" action="{{ route('sales.payments.store', $sale) }}">
                @csrf
                <div class="card-body">
                    <div class="due-pay-head">
                        <div>
                            <div class="stat-label">Balance due on {{ $sale->number }}</div>
                            <div class="due-pay-amount">{{ $money($due) }}</div>
                        </div>
                        <div class="text-secondary small">Total {{ $money((string) $sale->total) }} · Paid {{ $money((string) $sale->paid_amount) }}</div>
                    </div>
                    <div class="due-pay-row">
                        <div>
                            <label class="form-label" for="due-method">Paid by</label>
                            <select class="form-select" id="due-method" name="method" required>
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="due-amount">Amount ₹</label>
                            <input class="form-control @error('amount') is-invalid @enderror" id="due-amount" name="amount" inputmode="decimal" value="{{ old('amount', $due) }}" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="due-reference">Reference</label>
                            <input class="form-control" id="due-reference" name="reference" value="{{ old('reference') }}" placeholder="UPI ref, cheque no.">
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-cash-coin"></i> Receive payment</button>
                    </div>
                    <div class="form-text">Change the amount if the customer pays only part of the due.</div>
                </div>
            </form>
        @endcan
    @endif
    @include('commerce.sales.partials.invoice')
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
