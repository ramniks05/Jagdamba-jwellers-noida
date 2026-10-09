<div class="weigh-section">
    <div class="weigh-section-title">Show on screens</div>
    <div class="row g-3 align-items-center">
        <div class="col-md-4">
            <label class="form-label" for="sort_order">Order in lists</label>
            <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $record->sort_order ?? 0) }}" required>
            @error('sort_order')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Lower numbers come first.</div>
        </div>
        <div class="col-md-8">
            <input type="hidden" name="is_active" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" id="is_active" name="is_active" type="checkbox" role="switch" value="1" @checked(filter_var(old('is_active', $record->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="form-text">{{ $hint ?? 'Hidden '.$noun.'s stay on old pieces and bills and are left out of new ones.' }}</div>
        </div>
    </div>
</div>
