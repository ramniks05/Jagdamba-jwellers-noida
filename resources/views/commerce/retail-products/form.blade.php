@extends('layouts.app')

@section('title', $product->exists ? 'Edit other item' : 'Add other item')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $product->exists ? 'Edit other item' : 'Add other item' }}</h1>
    <form method="POST" action="{{ $product->exists ? route('retail-products.update', $product) : route('retail-products.store') }}">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row g-3">
                @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $product->name, 'col' => 'col-md-6', 'maxlength' => 160, 'placeholder' => 'Ganesh murti 2 inch', 'autofocus' => true])
                @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $product->code, 'col' => 'col-md-3', 'maxlength' => 20])
                @include('masters.partials.field', ['name' => 'sort_order', 'label' => 'Sort order', 'value' => $product->sort_order ?? 0, 'col' => 'col-md-3', 'type' => 'number', 'min' => 0, 'max' => 9999])
                <div class="col-md-4">
                    <label class="form-label" for="default_unit_price">Default price (₹)</label>
                    <input class="form-control @error('default_unit_price') is-invalid @enderror" id="default_unit_price" name="default_unit_price" value="{{ old('default_unit_price', $product->default_unit_price) }}" inputmode="decimal">
                    @error('default_unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="default_tax_mode">GST on bill</label>
                    <select class="form-select @error('default_tax_mode') is-invalid @enderror" id="default_tax_mode" name="default_tax_mode">
                        @foreach (App\Enums\RetailTaxMode::cases() as $mode)
                            <option value="{{ $mode->value }}" @selected(old('default_tax_mode', $product->default_tax_mode?->value ?? 'none') === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                    @error('default_tax_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="default_tax_percent">Default GST %</label>
                    <input class="form-control @error('default_tax_percent') is-invalid @enderror" id="default_tax_percent" name="default_tax_percent" value="{{ old('default_tax_percent', $product->default_tax_percent) }}" inputmode="decimal" placeholder="Shop default if blank">
                    @error('default_tax_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @include('masters.partials.field', ['name' => 'hsn_code', 'label' => 'HSN (optional)', 'value' => $product->hsn_code, 'col' => 'col-md-4', 'maxlength' => 20])
                <div class="col-md-4 d-flex align-items-end">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $product->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        @include('masters.partials.form-foot', ['label' => 'Save item', 'backUrl' => route('retail-products.index')])
    </form>
    @if ($product->exists)
        @can('delete', $product)
            <form class="mt-4" method="POST" action="{{ route('retail-products.destroy', $product) }}" onsubmit="return confirm('Remove this item from the list?');">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit">Remove</button>
            </form>
        @endcan
    @endif
@endsection
