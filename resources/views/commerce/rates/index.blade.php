@extends('layouts.app')

@section('title', 'Metal rates')

@section('content')
    <h1 class="page-title h3 mb-2">Metal rates</h1>
    <p class="text-secondary">A new rate is added to the history. Bills keep the rate that was current when they were saved.</p>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Market price</h2>
                    <p class="text-secondary mb-0">Gold and silver buy price per gram, before GST, from a free India feed. Change any amount, then save. Bills use the amount you save. Platinum is entered by hand.</p>
                </div>
                @if ($canEnter)
                    <form method="POST" action="{{ route('rates.market') }}">
                        @csrf
                        <button class="btn btn-outline-secondary" type="submit">Refresh</button>
                    </form>
                @endif
            </div>
            @if ($quoteError)
                <div class="alert alert-warning mb-0">{{ $quoteError }}</div>
            @elseif ($quote)
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="stat-label">Gold, 999</div><div class="fw-semibold">{{ $money($quote->goldPerGram) }} / g</div></div>
                    <div class="col-md-4"><div class="stat-label">Silver, 999</div><div class="fw-semibold">{{ $money($quote->silverPerGram) }} / g</div></div>
                    <div class="col-md-4"><div class="stat-label">Quoted</div><div>{{ \Illuminate\Support\Carbon::parse($quote->quotedAt)->timezone(config('app.timezone'))->format('d M Y H:i') }}</div></div>
                </div>
                @if ($canEnter)
                    <form method="POST" action="{{ route('rates.market.store') }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table mb-3">
                                <thead><tr><th></th><th>Purity</th><th>Rate / gram</th></tr></thead>
                                <tbody>
                                    @foreach ($suggestions as $index => $row)
                                        @php
                                            $submitted = collect(old('lines', []));
                                            $previous = $submitted->first(fn ($line) => ($line['purity_uuid'] ?? null) === $row['purity']->uuid);
                                            $rateValue = $previous['rate_per_gram'] ?? $row['rate'];
                                            $checked = old('lines') === null ? $row['selected'] : $previous !== null;
                                        @endphp
                                        <tr>
                                            <td><input type="checkbox" name="lines[{{ $index }}][use]" value="1" @checked($checked)></td>
                                            <td>{{ $row['purity']->metalType?->name }} {{ $row['purity']->name }}</td>
                                            <td>
                                                <input type="hidden" name="lines[{{ $index }}][purity_uuid]" value="{{ $row['purity']->uuid }}">
                                                <input class="form-control" name="lines[{{ $index }}][rate_per_gram]" value="{{ $rateValue }}" inputmode="decimal">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-primary" type="submit">Save selected rates</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
    @if ($canEnter)
        <form class="card mb-4" method="POST" action="{{ route('rates.store') }}">
            @csrf
            <div class="card-body row">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="metal_uuid">Metal</label>
                    <select class="form-select" id="metal_uuid" name="metal_uuid" required>
                        @foreach ($metals as $metal)
                            <option value="{{ $metal->uuid }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="purity_uuid">Purity</label>
                    <select class="form-select" id="purity_uuid" name="purity_uuid" required>
                        @foreach ($purities as $purity)
                            <option value="{{ $purity->uuid }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $purity->metalType?->name }} {{ $purity->name }}</option>
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
                            <td>{{ $rate->rate_per_gram }}</td>
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
