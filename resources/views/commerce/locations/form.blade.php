@extends('layouts.app')

@section('title', $location->exists ? 'Edit location' : 'Add location')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $location->exists ? 'Edit location' : 'Add location' }}</h1>
    <form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}">
        @csrf
        @if ($location->exists) @method('PUT') @endif
        <div class="card"><div class="card-body row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="branch_uuid">Branch</label>
                <select class="form-select" id="branch_uuid" name="branch_uuid" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->uuid }}" @selected(old('branch_uuid', $location->branch?->uuid) === $branch->uuid)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="parent_uuid">Inside</label>
                <select class="form-select" id="parent_uuid" name="parent_uuid">
                    <option value="">Top level</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->uuid }}" @selected(old('parent_uuid', $location->parent?->uuid) === $parent->uuid)>{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="kind">Kind</label>
                <select class="form-select" id="kind" name="kind">
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}" @selected(old('kind', $location->kind?->value) === $kind->value)>{{ $kind->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3"><label class="form-label" for="code">Code</label><input class="form-control" id="code" name="code" value="{{ old('code', $location->code) }}" required></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name', $location->name) }}" required></div>
            <input type="hidden" name="is_active" value="0">
            <div class="col-12"><div class="form-check"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $location->is_active ?? true), FILTER_VALIDATE_BOOLEAN))><label class="form-check-label" for="is_active">Active</label></div></div>
        </div></div>
        <button class="btn btn-primary mt-4" type="submit">Save location</button>
    </form>
@endsection
