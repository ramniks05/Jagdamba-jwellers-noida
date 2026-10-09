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
                <p>Per gram, before GST. Change any purity, then save it as the shop rate. Platinum is entered by hand.</p>
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
        @elseif ($quote)
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
            @if ($canEnter)
                <form method="POST" action="{{ route('rates.market.store') }}">
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
                                        <input class="form-control purity-rate" name="lines[{{ $line }}][rate_per_gram]" value="{{ $rateValue }}" inputmode="decimal" aria-label="{{ $metal }} {{ $row['purity']->name }} rate per gram">
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
                        <span>Only the ticked purities are saved.</span>
                    </div>
                </form>
            @endif
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
                        <tr><td colspan="6">No rates yet. Enter today's rate before billing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $rates->links() }}</div>
@endsection
