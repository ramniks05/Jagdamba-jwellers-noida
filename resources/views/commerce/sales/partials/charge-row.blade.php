<div class="d-flex gap-1 align-items-center mb-1" data-charge-row>
    <input class="form-control form-control-sm" name="charges[{{ $index }}][label]" value="{{ $charge['label'] ?? '' }}" maxlength="80" placeholder="Card charge" aria-label="Charge name">
    <input class="form-control form-control-sm counter-num bill-charge-amount" style="max-width: 8rem;" name="charges[{{ $index }}][amount]" value="{{ $charge['amount'] ?? '' }}" inputmode="decimal" placeholder="0" aria-label="Charge amount">
    <button class="btn btn-link btn-sm text-danger p-0 bill-charge-remove" type="button" aria-label="Remove charge">Remove</button>
</div>
