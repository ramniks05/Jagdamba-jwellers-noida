@extends('layouts.app')

@section('title', 'Metal rates')

@section('content')
    <h1 class="page-title h3 mb-2">Metal rates</h1>
    <p class="text-secondary">A new rate is added to the history. Bills keep the rate that was current when they were saved.</p>
    <section class="rate-board mb-4">
        <div class="rate-board-head">
            <div>
                <div class="rate-board-kicker">Rate board</div>
                <h2>Gold and silver today</h2>
                <p>Per gram, before GST. Type the 24K price and the other gold carats follow their fineness: 22K is 91.6, 18K is 75. Silver follows the 999 price. Platinum is entered by hand.</p>
            </div>
            <div class="rate-board-aside">
                @if ($quote)
                    <div class="rate-board-time">{{ \Illuminate\Support\Carbon::parse($quote->quotedAt)->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</div>
                @endif
                @if ($canEnter)
                    <form method="POST" action="{{ route('rates.market') }}">
                        @csrf
                        <button class="btn btn-outline-light btn-sm" type="submit">Refresh board</button>
                    </form>
                @endif
            </div>
        </div>
        @if ($quoteError)
            <div class="rate-board-body">
                <div class="alert alert-warning mb-0">{{ $quoteError }}</div>
            </div>
        @endif
        @if ($quote)
            <div class="spot-row">
                <article class="spot-card spot-gold">
                    <span>Gold</span>
                    <strong>{{ $money($quote->goldPerGram) }}</strong>
                    <em>999 fine · per gram</em>
                </article>
                <article class="spot-card spot-silver">
                    <span>Silver</span>
                    <strong>{{ $money($quote->silverPerGram) }}</strong>
                    <em>999 fine · per gram</em>
                </article>
            </div>
        @endif
        @if ($canEnter && $suggestionGroups->isNotEmpty())
            <form method="POST" action="{{ route('rates.market.store') }}" id="market-rates" data-reference="{{ config('metal-prices.reference_fineness') }}">
                @csrf
                @php
                    $line = 0;
                @endphp
                @foreach ($suggestionGroups as $metal => $rows)
                    <div class="purity-group">
                        <h3>{{ $metal }}</h3>
                        <div class="purity-grid">
                            @foreach ($rows as $row)
                                @php
                                    $submitted = collect(old('lines', []));
                                    $previous = $submitted->first(fn ($item) => ($item['purity_uuid'] ?? null) === $row['purity']->uuid);
                                    $rateValue = $previous['rate_per_gram'] ?? $row['rate'];
                                    $checked = old('lines') === null ? $row['selected'] : $previous !== null;
                                @endphp
                                <label class="purity-tile">
                                    <span class="purity-top">
                                        <span class="purity-name">{{ $row['purity']->name }}</span>
                                        <input type="checkbox" name="lines[{{ $line }}][use]" value="1" @checked($checked) aria-label="Save {{ $metal }} {{ $row['purity']->name }}">
                                    </span>
                                    <input type="hidden" name="lines[{{ $line }}][purity_uuid]" value="{{ $row['purity']->uuid }}">
                                    <input class="form-control purity-rate" name="lines[{{ $line }}][rate_per_gram]" value="{{ $rateValue }}" inputmode="decimal" data-code="{{ $row['purity']->code }}" data-metal="{{ $row['purity']->metalType?->code }}" data-fineness="{{ $row['purity']->fineness }}" aria-label="{{ $metal }} {{ $row['purity']->name }} rate per gram">
                                    <span class="purity-unit">per gram</span>
                                </label>
                                @php
                                    $line++;
                                @endphp
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="rate-board-save">
                    <button class="btn btn-primary" type="submit">Save shop rates</button>
                    <span>Only the ticked purities are saved. Bills use a rate after it is saved.</span>
                </div>
            </form>
        @endif
    </section>
    @if ($canEnter)
        @php
            $counterPurity = $purities->first(fn ($purity) => strtoupper($purity->name) === '22K');
            $pickedPurity = old('purity_uuid', $counterPurity?->uuid);
            $pickedMetal = old('metal_uuid', $counterPurity?->metalType?->uuid);
        @endphp
        <form class="card mb-4" method="POST" action="{{ route('rates.store') }}">
            @csrf
            <div class="card-body row">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="metal_uuid">Metal</label>
                    <select class="form-select" id="metal_uuid" name="metal_uuid" required>
                        @foreach ($metals as $metal)
                            <option value="{{ $metal->uuid }}" @selected($pickedMetal === $metal->uuid)>{{ $metal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="purity_uuid">Purity</label>
                    <select class="form-select" id="purity_uuid" name="purity_uuid" required>
                        @foreach ($purities as $purity)
                            <option value="{{ $purity->uuid }}" @selected($pickedPurity === $purity->uuid)>{{ $purity->metalType?->name }} {{ $purity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="rate_per_gram">Rate / gram</label>
                    <input class="form-control" id="rate_per_gram" name="rate_per_gram" value="{{ old('rate_per_gram') }}" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="branch_uuid">Branch</label>
                    <select class="form-select" id="branch_uuid" name="branch_uuid">
                        <option value="">Whole shop</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->uuid }}" @selected(old('branch_uuid') === $branch->uuid)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="effective_at">Effective</label>
                    <input class="form-control" id="effective_at" name="effective_at" type="datetime-local" value="{{ old('effective_at') }}">
                </div>
                <div class="col-md-4">
                    <input class="form-control" name="note" value="{{ old('note') }}" placeholder="Note, optional">
                </div>
                <input type="hidden" name="source" value="manual">
                <div class="col-md-2">
                    <button class="btn btn-primary" type="submit">Save rate</button>
                </div>
            </div>
        </form>
    @endif
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $view === 'current' ? 'active' : '' }}" href="{{ route('rates.index') }}">Current rates</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $view === 'history' ? 'active' : '' }}" href="{{ route('rates.index', ['view' => 'history']) }}">History</a>
        </li>
    </ul>
    @if ($view === 'current')
        <p class="text-secondary small">The last saved rate for each purity. Bills use these.</p>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Metal</th><th>Rate / g</th><th>Branch</th><th>Saved for</th><th>Source</th><th>Note</th></tr></thead>
                    <tbody>
                        @forelse ($current as $rate)
                            <tr>
                                <td><strong>{{ $rate->metalType?->name }} {{ $rate->purity?->name }}</strong></td>
                                <td class="num"><strong>{{ $money((string) $rate->rate_per_gram) }}</strong></td>
                                <td>{{ $rate->branch?->name ?: 'Whole shop' }}</td>
                                <td>{{ $rate->effective_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>{{ $rate->source === 'market' ? 'Market' : 'Entered' }}</td>
                                <td>{{ $rate->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No rates yet. Enter today's rate before billing.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <form class="row g-2 align-items-end mb-3" method="GET" action="{{ route('rates.index') }}">
            <input type="hidden" name="view" value="history">
            <div class="col-md-4">
                <label class="form-label" for="filter-purity">Purity</label>
                <select class="form-select" id="filter-purity" name="purity">
                    <option value="">All purities</option>
                    @foreach ($purities as $purity)
                        <option value="{{ $purity->uuid }}" @selected($filter['purity'] === $purity->uuid)>{{ $purity->metalType?->name }} {{ $purity->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-from">From</label>
                <input class="form-control" id="filter-from" name="from" type="date" value="{{ $filter['from'] }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-to">To</label>
                <input class="form-control" id="filter-to" name="to" type="date" value="{{ $filter['to'] }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('rates.index', ['view' => 'history']) }}">Clear</a>
            </div>
        </form>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Effective</th><th>Metal</th><th>Rate / g</th><th>Branch</th><th>Source</th><th>Note</th></tr></thead>
                    <tbody>
                        @forelse ($rates as $rate)
                            <tr>
                                <td>{{ $rate->effective_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>{{ $rate->metalType?->name }} {{ $rate->purity?->name }}</td>
                                <td class="num">{{ $money((string) $rate->rate_per_gram) }}</td>
                                <td>{{ $rate->branch?->name ?: 'Whole shop' }}</td>
                                <td>{{ $rate->source === 'market' ? 'Market' : 'Entered' }}</td>
                                <td>{{ $rate->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No rates match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $rates->links() }}</div>
    @endif
@endsection

@push('scripts')
    <script>
        const marketRates = document.getElementById('market-rates');
        if (marketRates) {
            const reference = Number(marketRates.dataset.reference || '0.999');
            marketRates.addEventListener('input', (event) => {
                const field = event.target;
                if (!field.classList.contains('purity-rate')) return;
                const metal = field.dataset.metal;
                const baseCode = metal === 'GOLD' ? '24K' : (metal === 'SILVER' ? '999' : '');
                if (field.dataset.code !== baseCode) return;
                const base = Number(field.value);
                if (!Number.isFinite(base) || base <= 0 || reference <= 0) return;
                marketRates.querySelectorAll('.purity-rate').forEach((other) => {
                    if (other === field || other.dataset.metal !== metal) return;
                    const fineness = Number(other.dataset.fineness);
                    if (!Number.isFinite(fineness) || fineness <= 0) return;
                    other.value = halfUp(base * fineness / reference);
                });
            });
        }

        function halfUp(amount) {
            const negative = amount < 0;
            const scaled = Math.abs(amount) * 100;
            const whole = Math.floor(scaled);
            const rounded = scaled - whole >= 0.5 ? whole + 1 : whole;
            return ((negative ? -rounded : rounded) / 100).toFixed(2);
        }
    </script>
@endpush
