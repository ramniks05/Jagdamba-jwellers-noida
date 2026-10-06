@extends('layouts.app')

@section('title', $design->exists ? 'Edit design' : 'Add design')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $design->exists ? 'Edit design' : 'Add design' }}</h1>
    <form method="POST" action="{{ $design->exists ? route('designs.update', $design) : route('designs.store') }}">
        @csrf
        @if ($design->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="design_number">Design number</label>
                    <input class="form-control" id="design_number" name="design_number" value="{{ old('design_number', $design->design_number) }}" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $design->name) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="collection_uuid">Collection</label>
                    <select class="form-select" id="collection_uuid" name="collection_uuid">
                        <option value="">None</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection->uuid }}" @selected(old('collection_uuid', $design->collection?->uuid) === $collection->uuid)>{{ $collection->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="category_uuid">Category</label>
                    <select class="form-select" id="category_uuid" name="category_uuid">
                        <option value="">None</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->uuid }}" @selected(old('category_uuid', $design->category?->uuid) === $category->uuid)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $design->description) }}</textarea>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $design->sort_order ?? 0) }}" required>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $design->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save design</button>
    </form>
@endsection
