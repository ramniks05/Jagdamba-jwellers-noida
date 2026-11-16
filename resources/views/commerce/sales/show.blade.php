@extends('layouts.app')

@section('title', $sale->number)

@section('content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('sales.create') }}">New bill</a>
        <div class="d-flex flex-wrap gap-2">
            @if ($whatsappReady)
                <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#whatsapp-send-modal" @disabled($whatsappNumber === null) @if ($whatsappNumber === null) title="Add the customer's mobile number first" @endif>
                    <i class="bi bi-whatsapp"></i> Send on WhatsApp
                </button>
                <a class="btn btn-outline-secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener" title="Open WhatsApp on this device instead"><i class="bi bi-box-arrow-up-right"></i></a>
            @else
                <a class="btn btn-outline-success" href="{{ $shareUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Send on WhatsApp</a>
            @endif
            @can('update', $sale)
                @if ($sale->isPosted() && $returned->isEmpty())
                    <a class="btn btn-outline-primary" href="{{ route('sales.edit', $sale) }}">Edit invoice</a>
                @endif
            @endcan
            @can('delete', $sale)
                @if ($sale->isPosted() && $returned->isEmpty())
                    <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#cancel-invoice-modal"><i class="bi bi-x-octagon"></i> Cancel invoice</button>
                @endif
            @endcan
            <button class="btn btn-primary" type="button" onclick="window.print()">Print invoice</button>
        </div>
    </div>
    @if ($sale->isCancelled())
        <div class="alert alert-danger no-print" role="status">
            <strong>This invoice was cancelled.</strong>
            It is kept for audit only and no longer counts in sales or customer balance.
            @if ($sale->cancelled_at)
                <span class="d-block small mt-1">{{ $sale->cancelled_at->timezone(config('app.timezone'))->format('d M Y, h:i A') }}{{ $sale->cancelledBy ? ' · '.$sale->cancelledBy->name : '' }}</span>
            @endif
            @if ($sale->cancel_reason)
                <span class="d-block small mt-1"><strong>Reason:</strong> {{ $sale->cancel_reason }}</span>
            @endif
        </div>
    @endif
    @php($due = $sale->balanceDue())
    @if ($sale->isPosted() && (float) $due > 0)
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
        @if ($sale->isPosted() && $sale->lines->contains(fn ($line) => ! $returned->contains($line->id)))
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
                            <input class="form-control counter-num" name="refund" value="" placeholder="0">
                        </div>
                        <button class="btn btn-outline-primary mt-3" type="submit">Save credit note</button>
                    </div>
                </form>
            </details>
        @endif
    @endcan

    @can('delete', $sale)
        @if ($sale->isPosted() && $returned->isEmpty() && \Illuminate\Support\Facades\Route::has('sales.destroy'))
            <div class="modal fade" id="cancel-invoice-modal" tabindex="-1" aria-labelledby="cancel-invoice-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('sales.destroy', $sale) }}" id="cancel-invoice-form">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header border-danger-subtle">
                                <h2 class="modal-title h5 text-danger" id="cancel-invoice-title">Cancel invoice {{ $sale->number }}?</h2>
                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">This does <strong>not</strong> erase the invoice number. The bill stays in the system as <strong>cancelled</strong> for your records.</p>
                                <ul class="small text-secondary mb-3">
                                    <li>Stock and customer account are put back as if this sale had not happened.</li>
                                    <li>If this bill delivered an order, that order opens again so you can bill correctly.</li>
                                    <li>Cancelled bills are left out of sales totals and reports.</li>
                                </ul>
                                <label class="form-label" for="cancel-reason">Why is this invoice being cancelled?</label>
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="cancel-reason" name="reason" rows="3" required minlength="10" maxlength="500" placeholder="For example: wrong customer, duplicate bill, weight entered twice">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if (! $errors->has('reason'))
                                    <div class="form-text">At least 10 characters — this reason is saved on the invoice.</div>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Keep invoice</button>
                                <button class="btn btn-danger" type="submit" id="cancel-invoice-confirm">Yes, cancel this invoice</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @if ($errors->has('reason'))
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('cancel-invoice-modal')).show();
                    });
                </script>
            @endif
        @endif
    @endcan

    @if ($whatsappReady && $whatsappNumber)
        <div class="modal fade" id="whatsapp-send-modal" tabindex="-1" aria-labelledby="whatsapp-send-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('sales.whatsapp.store', $sale) }}" id="whatsapp-send-form">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="whatsapp-send-title"><i class="bi bi-whatsapp text-success"></i> Send bill on WhatsApp</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Send <strong>{{ $sale->number }}</strong> to:</p>
                            <p class="fs-5 mb-3"><strong>+{{ $whatsappNumber }}</strong></p>
                            <p class="text-secondary small mb-3">First check that this number is on WhatsApp. If it is not, the bill will not be delivered.</p>
                            <a class="btn btn-outline-success w-100 mb-3" href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener" id="whatsapp-check-link">
                                <i class="bi bi-box-arrow-up-right"></i> Check number on WhatsApp
                            </a>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="whatsapp-checked" name="whatsapp_checked" required>
                                <label class="form-check-label" for="whatsapp-checked">I checked — this number is on WhatsApp</label>
                            </div>
                            @error('whatsapp')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                            @error('whatsapp_checked')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-success" type="submit" id="whatsapp-send-confirm" disabled>
                                <i class="bi bi-whatsapp"></i> Send bill
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const box = document.getElementById('whatsapp-checked');
                const send = document.getElementById('whatsapp-send-confirm');
                const form = document.getElementById('whatsapp-send-form');
                const modal = document.getElementById('whatsapp-send-modal');
                const sync = () => { send.disabled = ! box.checked; };
                box?.addEventListener('change', sync);
                form?.addEventListener('submit', () => { send.disabled = true; send.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…'; });
                @if ($errors->has('whatsapp') || $errors->has('whatsapp_checked'))
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                @endif
            });
        </script>
    @endif
@endsection
