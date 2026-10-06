@extends('layouts.app')

@section('title', 'Metal rates')

@section('content')
    <h1 class="page-title h3 mb-2">Metal rates</h1>
    <p class="text-secondary">A new rate is added to the history. Bills keep the rate that was current when they were saved.</p>
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
                <thead><tr><th>Effective</th><th>Metal</th><th>Rate / g</th><th>Branch</th><th>Note</th></tr></thead>
                <tbody>
                    @forelse ($rates as $rate)
                        <tr>
                            <td>{{ $rate->effective_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $rate->metalType?->name }} {{ $rate->purity?->name }}</td>
                            <td>{{ $rate->rate_per_gram }}</td>
                            <td>{{ $rate->branch?->name ?: 'Whole shop' }}</td>
                            <td>{{ $rate->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No rates yet. Enter today's rate before billing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $rates->links() }}</div>
@endsection
