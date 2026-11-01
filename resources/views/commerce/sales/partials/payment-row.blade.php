<div class="bill-payment-row row g-2 mb-2 align-items-center" data-payment-row>
    <div class="col-6 col-lg-3">
        <select class="form-select" name="payments[{{ $slot }}][method]">
            @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank', 'cheque' => 'Cheque'] as $payMethod => $payLabel)
                <option value="{{ $payMethod }}" @selected(($savedPayment['method'] ?? ($first ? 'cash' : '')) === $payMethod)>{{ $payLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-lg-3">
        <input class="form-control bill-pay counter-num" name="payments[{{ $slot }}][amount]" value="{{ $savedPayment['amount'] ?? '' }}" placeholder="Amount" inputmode="decimal">
    </div>
    <div class="col-12 col-lg-4 bill-pay-date-wrap d-none">
        <input class="form-control form-control-sm bill-pay-date" type="datetime-local" name="payments[{{ $slot }}][received_at]" value="{{ $savedPayment['received_at'] ?? '' }}" step="60" aria-label="Payment date and time">
    </div>
    <div class="col-{{ $first ? '12' : '10' }} col-lg-{{ $first ? '6' : '5' }} bill-pay-ref-wrap">
        <input class="form-control" name="payments[{{ $slot }}][reference]" value="{{ $savedPayment['reference'] ?? '' }}" placeholder="Reference">
    </div>
    @unless ($first)
        <div class="col-2 col-lg-1 text-end">
            <button class="btn btn-outline-secondary btn-sm bill-payment-remove" type="button" aria-label="Remove payment"><i class="bi bi-x-lg"></i></button>
        </div>
    @endunless
</div>
