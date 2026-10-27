@extends('layouts.app')

@section('title', $tray->exists ? 'Edit tray' : 'Add tray')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $tray->exists ? 'Edit tray' : 'Add tray' }}</h1>
    <form method="POST" action="{{ $tray->exists ? route('trays.update', $tray) : route('trays.store') }}">
        @csrf
        @if ($tray->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row g-3">
                @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $tray->name, 'col' => 'col-md-6', 'maxlength' => 80, 'placeholder' => 'Silver payal tray', 'autofocus' => true])
                @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $tray->code, 'col' => 'col-md-3', 'maxlength' => 20])
                @include('masters.partials.field', ['name' => 'sort_order', 'label' => 'Sort order', 'value' => $tray->sort_order ?? 0, 'col' => 'col-md-3', 'type' => 'number', 'min' => 0, 'max' => 9999])
                <div class="col-md-4">
                    <label class="form-label" for="grams">Weight on tray (g)</label>
                    <input class="form-control @error('grams') is-invalid @enderror" id="grams" name="grams" value="{{ old('grams', $tray->exists ? rtrim(rtrim((string) $tray->grams, '0'), '.') : '') }}" inputmode="decimal" required>
                    @error('grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($tray->exists)
                        <div class="form-text">Saving changes the balance and is logged as an adjustment.</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pieces">Pieces on tray</label>
                    <input class="form-control @error('pieces') is-invalid @enderror" id="pieces" name="pieces" type="number" min="{{ $tray->exists ? 0 : 1 }}" value="{{ old('pieces', $tray->pieces ?? '') }}" required>
                    @error('pieces')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $tray->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        @include('masters.partials.form-foot', ['label' => 'Save tray', 'backUrl' => route('trays.index')])
    </form>
    @if ($tray->exists)
        @can('delete', $tray)
            @if ($tray->deletionBlocker())
                <p class="text-secondary small mt-4 mb-0">{{ $tray->deletionBlocker() }}</p>
            @else
                <form class="mt-4" method="POST" action="{{ route('trays.destroy', $tray) }}" onsubmit="return confirm('Remove this tray?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">Remove tray</button>
                </form>
            @endif
        @endcan
    @endif
@endsection
