@extends('layouts.app')

@section('title', 'Edit calculation method')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $method->applies_to->label() }}: {{ $method->name }}</h1>
    <form method="POST" action="{{ route('charge-methods.update', $method) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $method->name) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Code</label>
                    <input class="form-control" value="{{ $method->code }}" disabled>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $method->sort_order) }}" required>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $method->is_active), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <div class="form-text">Hidden methods stay on record and can be turned back on.</div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save method</button>
    </form>
@endsection
