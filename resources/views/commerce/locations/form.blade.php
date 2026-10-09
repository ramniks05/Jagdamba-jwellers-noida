@extends('layouts.app')

@section('title', $location->exists ? 'Edit location' : 'Add location')

@section('content')
    @include('masters.partials.head', [
        'title' => $location->exists ? 'Edit '.$location->name : 'Add location',
        'intro' => 'Pieces are kept here when you add or receive them. Hidden locations are left out of the lists.',
    ])
    <form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}">
        @csrf
        @if ($location->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-box-seam"></i> Location</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $location->name, 'col' => 'col-md-6', 'maxlength' => 80, 'placeholder' => 'Counter A, Tray 3, Locker', 'autofocus' => ! $location->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $location->code, 'col' => 'col-6 col-md-3', 'class' => 'text-uppercase', 'maxlength' => 20, 'placeholder' => 'CTRA'])
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="kind">Kind</label>
                                <select class="form-select" id="kind" name="kind">
                                    @foreach ($kinds as $kind)
                                        <option value="{{ $kind->value }}" @selected(old('kind', $location->kind?->value) === $kind->value)>{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="branch_uuid">Branch</label>
                                <select class="form-select @error('branch_uuid') is-invalid @enderror" id="branch_uuid" name="branch_uuid" required>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->uuid }}" @selected(old('branch_uuid', $location->branch?->uuid) === $branch->uuid)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_uuid')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="parent_uuid">Inside</label>
                                <select class="form-select @error('parent_uuid') is-invalid @enderror" id="parent_uuid" name="parent_uuid">
                                    <option value="">Nothing (top level)</option>
                                    @foreach ($parents as $parent)
                                        <option value="{{ $parent->uuid }}" @selected(old('parent_uuid', $location->parent?->uuid) === $parent->uuid)>{{ $parent->name }}</option>
                                    @endforeach
                                </select>
                                @error('parent_uuid')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" id="is_active" name="is_active" type="checkbox" role="switch" value="1" @checked(filter_var(old('is_active', $location->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                                <label class="form-check-label" for="is_active">Show in lists</label>
                            </div>
                        </div>
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save location', 'backUrl' => route('locations.index')])
            </div>
        </div>
    </form>
@endsection
