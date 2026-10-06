@extends('layouts.app')

@section('title', $purity->exists ? 'Edit purity' : 'Add purity')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $purity->exists ? 'Edit purity' : 'Add purity' }} for {{ $metal->name }}</h1>
    <form method="POST" action="{{ $purity->exists ? route('metals.purities.update', [$metal, $purity]) : route('metals.purities.store', $metal) }}">
        @csrf
        @if ($purity->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $purity->name) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control" id="code" name="code" value="{{ old('code', $purity->code) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="fineness_percent">Fineness %</label>
                    <input class="form-control" id="fineness_percent" name="fineness_percent" value="{{ old('fineness_percent', $finenessPercent) }}" required>
                    <div class="form-text">Pure metal share. 22K is usually 91.6.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $purity->sort_order ?? 0) }}" required>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $purity->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save purity</button>
    </form>
@endsection
