@php
    if (is_array(old('lines')) && old('lines') !== []) {
        $lineRows = array_values(old('lines'));
    } elseif (! empty($lineRows)) {
        $lineRows = $lineRows;
    } else {
        $lineRows = [[
            'metal_uuid' => old('metal_uuid', ''),
            'purity_uuid' => old('purity_uuid', ''),
            'expected_weight' => old('expected_weight', ''),
            'rate_per_gram' => old('rate_per_gram', ''),
            'estimated_making' => '0',
        ]];
    }
@endphp
<div class="weigh-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="weigh-section-title mb-0">Metal lines</div>
        <span class="small text-secondary">One description for the order; add a line per purity (e.g. ring 18K + tops 20K).</span>
    </div>
    <div id="order-lines" class="d-flex flex-column gap-2">
        @foreach ($lineRows as $index => $line)
            @include('commerce.orders.partials.metal-line-row', [
                'index' => $index,
                'line' => $line,
                'metals' => $metals,
                'canRemove' => count($lineRows) > 1,
            ])
        @endforeach
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="add-order-line"><i class="bi bi-plus-lg"></i> Add another purity</button>
    <div class="text-danger small mt-2 d-none" id="rate-missing"></div>
</div>

<template id="order-line-template">
    @include('commerce.orders.partials.metal-line-row', [
        'index' => '__INDEX__',
        'line' => ['metal_uuid' => '', 'purity_uuid' => '', 'expected_weight' => '', 'rate_per_gram' => '', 'estimated_making' => '0'],
        'metals' => $metals,
        'canRemove' => true,
        'template' => true,
    ])
</template>
