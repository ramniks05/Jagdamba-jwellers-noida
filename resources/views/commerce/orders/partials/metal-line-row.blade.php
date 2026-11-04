@php
    $idx = $index;
    $prefix = is_numeric($idx) ? "lines[{$idx}]" : 'lines[__INDEX__]';
    $metalUuid = $line['metal_uuid'] ?? '';
    $purityUuid = $line['purity_uuid'] ?? '';
    $weight = $line['expected_weight'] ?? '';
    $rate = $line['rate_per_gram'] ?? '';
    $lineMaking = $line['estimated_making'] ?? '0';
@endphp
<div class="stone-box mt-0 order-line" data-index="{{ $idx }}">
    <div class="d-flex justify-content-between align-items-center stone-box-head">
        <span class="weigh-step-label order-line-label">Line {{ is_numeric($idx) ? ((int) $idx + 1) : '' }}</span>
        @if ($canRemove)
            <button type="button" class="btn btn-link btn-sm text-danger p-0 order-line-remove" aria-label="Remove line">Remove</button>
        @endif
    </div>
    <div class="metal-row">
        <div>
            <label>Metal</label>
            <select class="form-select order-line-metal" name="{{ $prefix }}[metal_uuid]" @if(empty($template)) required @endif>
                @foreach ($metals as $metal)
                    <option value="{{ $metal->uuid }}" data-id="{{ $metal->id }}" @selected($metalUuid === $metal->uuid)>{{ $metal->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Purity</label>
            <select class="form-select order-line-purity" name="{{ $prefix }}[purity_uuid]" @if(empty($template)) required @endif>
                @foreach ($metals as $metal)
                    @foreach ($metal->purities->sortByDesc('fineness') as $purity)
                        <option value="{{ $purity->uuid }}" data-id="{{ $purity->id }}" data-metal="{{ $metal->id }}" @selected($purityUuid === $purity->uuid)>{{ $purity->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label>Expected wt g</label>
            <input class="form-control metal-net counter-num order-line-weight" name="{{ $prefix }}[expected_weight]" value="{{ $weight }}" inputmode="decimal" placeholder="0.000" @if(empty($template)) required @endif>
        </div>
        <div>
            <label>Rate / g</label>
            <input class="form-control order-line-rate" name="{{ $prefix }}[rate_per_gram]" value="{{ $rate }}" inputmode="decimal" placeholder="Today's rate">
        </div>
        <div>
            <label>Value</label>
            <input class="form-control order-line-value" readonly tabindex="-1" placeholder="0">
        </div>
    </div>
    <input type="hidden" class="order-line-making" name="{{ $prefix }}[estimated_making]" value="{{ $lineMaking }}">
</div>
