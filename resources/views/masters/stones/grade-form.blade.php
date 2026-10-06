@extends('layouts.app')

@section('title', $grade->exists ? 'Edit stone grade' : 'Add stone grade')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $grade->exists ? 'Edit stone grade' : 'Add stone grade' }}</h1>
    <form method="POST" action="{{ $grade->exists ? route('stone-grades.update', $grade) : route('stone-grades.store') }}">
        @csrf
        @if ($grade->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="kind">Kind</label>
                    <select class="form-select" id="kind" name="kind">
                        @foreach ($kinds as $kind)
                            <option value="{{ $kind->value }}" @selected(old('kind', $grade->kind?->value ?? $grade->kind) === $kind->value)>{{ $kind->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $grade->name) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control" id="code" name="code" value="{{ old('code', $grade->code) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $grade->sort_order ?? 0) }}" required>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $grade->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save grade</button>
    </form>
@endsection
