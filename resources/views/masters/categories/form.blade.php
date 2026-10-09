@extends('layouts.app')

@section('title', $category->exists ? 'Edit category' : 'Add category')

@section('content')
    <h1 class="page-title h3 mb-1">{{ $category->exists ? 'Edit category' : 'Add category' }}</h1>
    <p class="text-secondary mb-3">Pieces, bills and the stock report group by category.</p>
    <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" autocomplete="off">
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title">Category</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $category->name, 'col' => 'col-md-8', 'maxlength' => 80, 'placeholder' => 'Ring', 'autofocus' => ! $category->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $category->code, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 20, 'placeholder' => 'RING', 'help' => '2 to 20 letters or numbers.'])
                            <div class="col-md-8">
                                <label class="form-label" for="parent_uuid">Parent</label>
                                <select class="form-select @error('parent_uuid') is-invalid @enderror" id="parent_uuid" name="parent_uuid">
                                    <option value="">None, top level</option>
                                    @foreach ($parents as $parent)
                                        <option value="{{ $parent->uuid }}" @selected(old('parent_uuid', $category->parent?->uuid) === $parent->uuid)>{{ $parent->name }}</option>
                                    @endforeach
                                </select>
                                @error('parent_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Pick Ring to make Engagement ring a subcategory of Ring.</div>
                            </div>
                        </div>
                        @include('masters.partials.visibility', ['record' => $category, 'noun' => 'category', 'hint' => 'Hidden categories stay on old pieces and bills and are left out of new ones.'])
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save category', 'backUrl' => route('categories.index')])
            </div>
        </div>
    </form>
@endsection
