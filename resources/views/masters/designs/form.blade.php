@extends('layouts.app')

@section('title', $design->exists ? 'Edit design' : 'Add design')

@section('content')
    <h1 class="page-title h3 mb-1">{{ $design->exists ? 'Edit design' : 'Add design' }}</h1>
    <p class="text-secondary mb-3">Link the design to a collection and category so pieces made from it are easy to find.</p>
    <form method="POST" action="{{ $design->exists ? route('designs.update', $design) : route('designs.store') }}" autocomplete="off">
        @csrf
        @if ($design->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title">Design</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'design_number', 'label' => 'Design number', 'value' => $design->design_number, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 40, 'placeholder' => 'JD-101', 'autofocus' => ! $design->exists])
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $design->name, 'col' => 'col-md-8', 'maxlength' => 80, 'placeholder' => 'Peacock jhumka'])
                            <div class="col-md-6">
                                <label class="form-label" for="collection_uuid">Collection</label>
                                <select class="form-select @error('collection_uuid') is-invalid @enderror" id="collection_uuid" name="collection_uuid">
                                    <option value="">None</option>
                                    @foreach ($collections as $collection)
                                        <option value="{{ $collection->uuid }}" @selected(old('collection_uuid', $design->collection?->uuid) === $collection->uuid)>{{ $collection->name }}</option>
                                    @endforeach
                                </select>
                                @error('collection_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="category_uuid">Category</label>
                                <select class="form-select @error('category_uuid') is-invalid @enderror" id="category_uuid" name="category_uuid">
                                    <option value="">None</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->uuid }}" @selected(old('category_uuid', $design->category?->uuid) === $category->uuid)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2" maxlength="500" placeholder="Optional notes for the counter">{{ old('description', $design->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        @include('masters.partials.visibility', ['record' => $design, 'noun' => 'design'])
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save design', 'backUrl' => route('designs.index')])
            </div>
        </div>
    </form>
@endsection
