@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title h3 mb-4">{{ $title }}</h1>
    <form method="POST" action="{{ $record->exists ? route($routeName.'.update', $record) : route($routeName.'.store') }}">
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $record->name) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control" id="code" name="code" value="{{ old('code', $record->code) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $record->sort_order ?? 0) }}" required>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $record->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save {{ strtolower($singular) }}</button>
    </form>
@endsection
