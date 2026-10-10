@extends('layouts.app')

@section('title', $item->item_code)

@section('content')
    @php
        $pill = match ($item->status->value) {
            'available' => 'is-ready',
            'reserved' => 'is-booked',
            'repair' => 'is-repairing',
            'damaged', 'lost' => 'is-cancelled',
            default => 'is-delivered',
        };
        $chargeText = function ($method, $value) use ($money): string {
            if (! $method || (float) $value <= 0) {
                return '—';
            }

            $amount = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

            return match ($method->code) {
                'per_gram' => $money((string) $value).' / g',
                'percentage' => $amount.'%',
                default => $money((string) $value),
            };
        };
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a href="{{ route('items.index') }}"><i class="bi bi-arrow-left"></i> All pieces</a>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Item::class)
                <a class="btn btn-outline-secondary" href="{{ route('items.create', ['like' => $item->uuid]) }}"><i class="bi bi-copy"></i> Add another like this</a>
            @endcan
            @can('update', $item)
                <a class="btn btn-outline-secondary" href="{{ route('items.edit', $item) }}"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('inventory.print')
                <a class="btn btn-outline-secondary" href="{{ route('items.label', $item) }}"><i class="bi bi-upc-scan"></i> Print tag</a>
            @endcan
            @if ($item->status->value === 'available')
                @can('create', App\Models\Sale::class)
                    <a class="btn btn-primary" href="{{ route('sales.create', ['search' => $item->item_code]) }}"><i class="bi bi-receipt"></i> Sell this piece</a>
                @endcan
            @endif
        </div>
    </div>

    <div class="card order-panel mb-3">
        <div class="card-body">
            <div class="order-panel-head">
                <div>
                    <div class="stat-label">{{ $item->item_code }}@if ($item->category) · {{ $item->category->parent ? $item->category->parent->name.' / ' : '' }}{{ $item->category->name }}@endif</div>
                    <div class="order-panel-title">{{ $item->name }}</div>
                    <div class="text-secondary small">{{ $item->metalType?->name }} {{ $item->purity?->name }} · {{ $item->location?->branch?->name }} / {{ $item->location?->label() }}</div>
                </div>
                <span class="order-status {{ $pill }}">{{ $item->status->label() }}</span>
            </div>
            <div class="customer-stats mb-0 mt-3">
                <div class="customer-stat"><div class="stat-label">Gross</div><div>{{ $weight((string) $item->gross_weight) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Net</div><div>{{ $weight((string) $item->net_weight) }}</div></div>
                <div class="customer-stat"><div class="stat-label">Price today</div><div>{{ $price ? $money($price['line_amount']) : '—' }}</div></div>
                <div class="customer-stat"><div class="stat-label">Source</div><div>{{ $item->source->label() }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white">Weight and charges</div>
                <div class="card-body bill-sums">
                    <div class="bill-block mt-0">
                        <div class="bill-row"><span>Gross weight</span><span>{{ $weight((string) $item->gross_weight) }}</span></div>
                        @foreach ($item->stones as $stone)
                            <div class="bill-row bill-row-muted">
                                <span>
                                    Less {{ $stone->name }}
                                    @if ($stone->rate !== null && $stone->rate_unit === 'carat')
                                        · {{ App\Support\StoneRate::carats((string) $stone->weight) }} ct × {{ $money((string) $stone->rate) }}
                                    @elseif ($stone->rate !== null && $stone->rate_unit === 'gram')
                                        · {{ $money((string) $stone->rate) }} / g
                                    @endif
                                </span>
                                <span>{{ $weight((string) $stone->weight) }}</span>
                            </div>
                        @endforeach
                        @if ($item->stones->isEmpty() && (float) $item->stone_weight > 0)
                            <div class="bill-row bill-row-muted"><span>Less stones</span><span>{{ $weight((string) $item->stone_weight) }}</span></div>
                        @endif
                        @if ((float) $item->other_weight > 0)
                            <div class="bill-row bill-row-muted"><span>Less other</span><span>{{ $weight((string) $item->other_weight) }}</span></div>
                        @endif
                        <div class="bill-row bill-row-sub"><span>Net weight</span><span>{{ $weight((string) $item->net_weight) }}</span></div>
                    </div>
                    <div class="bill-block">
                        <div class="bill-row"><span>Making</span><span>{{ $chargeText($item->makingMethod, $item->making_value) }}</span></div>
                        <div class="bill-row"><span>Wastage</span><span>{{ $chargeText($item->wastageMethod, $item->wastage_value) }}</span></div>
                        <div class="bill-row"><span>Stones value</span><span>{{ (float) $item->stone_value > 0 ? $money((string) $item->stone_value) : '—' }}</span></div>
                        <div class="bill-row"><span>Cost price</span><span>{{ (float) $item->cost_price > 0 ? $money((string) $item->cost_price) : '—' }}</span></div>
                        @if ((float) $item->selling_price > 0)
                            <div class="bill-row"><span>Tag price</span><span>{{ $money((string) $item->selling_price) }}</span></div>
                        @endif
                        @if ((float) $item->mrp > 0)
                            <div class="bill-row"><span>MRP</span><span>{{ $money((string) $item->mrp) }}</span></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white">Price today</div>
                <div class="card-body bill-sums">
                    @if ($price)
                        <div class="bill-block mt-0">
                            <div class="bill-row"><span>Metal at {{ $money((string) $rate->rate_per_gram) }} / g</span><span>{{ $money($price['metal_amount']) }}</span></div>
                            @if ((float) $price['wastage_amount'] > 0)
                                <div class="bill-row"><span>Wastage</span><span>{{ $money($price['wastage_amount']) }}</span></div>
                            @endif
                            @if ((float) $price['making_amount'] > 0)
                                <div class="bill-row"><span>Making</span><span>{{ $money($price['making_amount']) }}</span></div>
                            @endif
                            @if ((float) $price['stone_amount'] > 0)
                                <div class="bill-row"><span>Stones</span><span>{{ $money($price['stone_amount']) }}</span></div>
                            @endif
                        </div>
                        <div class="bill-grand"><span>Price before GST</span><strong>{{ $money($price['line_amount']) }}</strong></div>
                        @if ((float) $item->cost_price > 0)
                            @php($margin = (float) $price['line_amount'] - (float) $item->cost_price)
                            <div class="bill-row mt-2"><span>Margin over cost</span><strong class="{{ $margin < 0 ? 'text-danger' : '' }}">{{ $money(number_format($margin, 2, '.', '')) }}</strong></div>
                        @endif
                        <p class="text-secondary small mb-0 mt-2">From the {{ $item->metalType?->name }} {{ $item->purity?->name }} rate of {{ $rate->effective_at?->timezone(config('app.timezone'))->format('d-m-Y') }}. GST is added on the bill.</p>
                    @else
                        <p class="mb-0 text-secondary">No {{ $item->metalType?->name }} {{ $item->purity?->name }} rate is saved yet. Add it in <a href="{{ route('rates.index') }}">Metal rates</a>.</p>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-white">Marks</div>
                <div class="card-body bill-sums">
                    <div class="bill-row"><span>HUID</span><span>{{ $item->huid ?: '—' }}</span></div>
                    <div class="bill-row"><span>Hallmark</span><span>{{ $item->hallmark ?: '—' }}</span></div>
                    <div class="bill-row"><span>Certificate</span><span>{{ $item->certificate_number ?: '—' }}</span></div>
                    @if ($item->barcode)
                        <div class="bill-row"><span>Barcode</span><span>{{ $item->barcode }}</span></div>
                    @endif
                    @if ($item->brand || $item->collection || $item->design)
                        <div class="bill-row"><span>Brand / design</span><span>{{ collect([$item->brand?->name, $item->collection?->name, $item->design?->design_number])->filter()->implode(' · ') }}</span></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($item->image_path || $item->notes)
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-start">
                @if ($item->image_path)
                    <a href="{{ route('items.photo', $item) }}" target="_blank" rel="noopener"><img class="piece-photo" src="{{ route('items.photo', $item) }}" alt="Photo of {{ $item->name }}"></a>
                @endif
                @if ($item->notes)
                    <div><div class="stat-label">Notes</div><div>{{ $item->notes }}</div></div>
                @endif
            </div>
        </div>
    @endif

    @can('inventory.adjust')
        @if (in_array($item->status->value, ['available', 'reserved'], true))
            <div class="d-flex flex-wrap gap-2 mb-3">
                @if ($item->status->value === 'available')
                    <form method="POST" action="{{ route('items.reserve', $item) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-bookmark"></i> Reserve</button></form>
                    <form method="POST" action="{{ route('items.damage', $item) }}" onsubmit="return confirm('Mark {{ $item->item_code }} as damaged? It will leave saleable stock.')">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Mark damaged</button></form>
                    <form method="POST" action="{{ route('items.lost', $item) }}" onsubmit="return confirm('Mark {{ $item->item_code }} as lost? It will leave stock.')">@csrf<button class="btn btn-outline-danger btn-sm" type="submit">Mark lost</button></form>
                @else
                    <form method="POST" action="{{ route('items.release', $item) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-bookmark-x"></i> Release reservation</button></form>
                @endif
            </div>
        @elseif (in_array($item->status->value, ['damaged', 'lost'], true))
            <div class="d-flex flex-wrap gap-2 mb-3">
                <form method="POST" action="{{ route('items.restore', $item) }}" onsubmit="return confirm('Put {{ $item->item_code }} back in saleable stock?')">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise"></i> {{ $item->status->value === 'lost' ? 'Found · back in stock' : 'Mended · back in stock' }}</button></form>
            </div>
        @endif
    @endcan

    <div class="card">
        <div class="card-header bg-white">Stock history</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>When</th><th>What happened</th><th class="num">Qty</th><th>Note</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="text-nowrap">{{ $movement->occurred_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}</td>
                            <td>{{ $movement->type->label() }}</td>
                            <td class="num">{{ (float) $movement->quantity }}</td>
                            <td>{{ $movement->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No stock history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
