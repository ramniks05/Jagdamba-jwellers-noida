@extends('layouts.app')

@section('title', $item->exists ? 'Edit piece' : 'Add piece')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $item->exists ? 'Edit piece' : 'Add piece' }}</h1>
    <form method="POST" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif
        <div class="card mb-4">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $item->name) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="item_code">Item code</label>
                    <input class="form-control" id="item_code" name="item_code" value="{{ old('item_code', $item->item_code) }}" required @disabled($item->exists)>
                    @if ($item->exists)
                        <input type="hidden" name="item_code" value="{{ $item->item_code }}">
                        <input type="hidden" name="sku" value="{{ $item->sku }}">
                    @endif
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="sku">SKU</label>
                    <input class="form-control" id="sku" name="sku" value="{{ old('sku', $item->sku) }}" placeholder="Same as item code if blank" @disabled($item->exists)>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="barcode">Barcode</label>
                    <input class="form-control" id="barcode" name="barcode" value="{{ old('barcode', $item->barcode) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="rfid">RFID</label>
                    <input class="form-control" id="rfid" name="rfid" value="{{ old('rfid', $item->rfid) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="huid">HUID</label>
                    <input class="form-control" id="huid" name="huid" value="{{ old('huid', $item->huid) }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="category_uuid">Category</label>
                    <select class="form-select" id="category_uuid" name="category_uuid">
                        <option value="">None</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->uuid }}" @selected(old('category_uuid', $item->category?->uuid) === $category->uuid)>{{ $category->parent ? $category->parent->name.' / '.$category->name : $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="brand_uuid">Brand</label>
                    <select class="form-select" id="brand_uuid" name="brand_uuid">
                        <option value="">None</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->uuid }}" @selected(old('brand_uuid', $item->brand?->uuid) === $brand->uuid)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="collection_uuid">Collection</label>
                    <select class="form-select" id="collection_uuid" name="collection_uuid">
                        <option value="">None</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection->uuid }}" @selected(old('collection_uuid', $item->collection?->uuid) === $collection->uuid)>{{ $collection->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="design_uuid">Design</label>
                    <select class="form-select" id="design_uuid" name="design_uuid">
                        <option value="">None</option>
                        @foreach ($designs as $design)
                            <option value="{{ $design->uuid }}" @selected(old('design_uuid', $item->design?->uuid) === $design->uuid)>{{ $design->design_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white">Metal and weight</div>
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="metal_uuid">Metal</label>
                    <select class="form-select" id="metal_uuid" name="metal_uuid" required>
                        @foreach ($metals as $metal)
                            <option value="{{ $metal->uuid }}" @selected(old('metal_uuid', $item->metalType?->uuid) === $metal->uuid)>{{ $metal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="purity_uuid">Purity</label>
                    <select class="form-select" id="purity_uuid" name="purity_uuid" required>
                        @foreach ($purities as $purity)
                            <option value="{{ $purity->uuid }}" @selected(old('purity_uuid', $item->purity?->uuid) === $purity->uuid)>{{ $purity->metalType?->name }} {{ $purity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="location_uuid">Location</label>
                    <select class="form-select" id="location_uuid" name="location_uuid" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->uuid }}" @selected(old('location_uuid', $item->location?->uuid) === $location->uuid)>{{ $location->branch?->name }} / {{ $location->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="gross_weight">Gross weight (g)</label>
                    <input class="form-control" id="gross_weight" name="gross_weight" value="{{ old('gross_weight', $item->gross_weight) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="stone_weight">Stone weight (g)</label>
                    <input class="form-control" id="stone_weight" name="stone_weight" value="{{ old('stone_weight', $item->stone_weight ?? '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="other_weight">Other weight (g)</label>
                    <input class="form-control" id="other_weight" name="other_weight" value="{{ old('other_weight', $item->other_weight ?? '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Net metal weight</label>
                    <input class="form-control" value="{{ $item->net_weight ?: 'Calculated on save' }}" disabled>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white">Charges and price</div>
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="making_method_uuid">Making</label>
                    <select class="form-select" id="making_method_uuid" name="making_method_uuid">
                        <option value="">None</option>
                        @foreach ($makingMethods as $method)
                            <option value="{{ $method->uuid }}" @selected(old('making_method_uuid', $item->makingMethod?->uuid) === $method->uuid)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="making_value">Making value</label>
                    <input class="form-control" id="making_value" name="making_value" value="{{ old('making_value', $item->making_value ?? '0') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="wastage_method_uuid">Wastage</label>
                    <select class="form-select" id="wastage_method_uuid" name="wastage_method_uuid">
                        <option value="">None</option>
                        @foreach ($wastageMethods as $method)
                            <option value="{{ $method->uuid }}" @selected(old('wastage_method_uuid', $item->wastageMethod?->uuid) === $method->uuid)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="wastage_value">Wastage value</label>
                    <input class="form-control" id="wastage_value" name="wastage_value" value="{{ old('wastage_value', $item->wastage_value ?? '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="stone_value">Stone value</label>
                    <input class="form-control" id="stone_value" name="stone_value" value="{{ old('stone_value', $item->stone_value ?? '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="cost_price">Cost price</label>
                    <input class="form-control" id="cost_price" name="cost_price" value="{{ old('cost_price', $item->cost_price ?? '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="selling_price">Selling price</label>
                    <input class="form-control" id="selling_price" name="selling_price" value="{{ old('selling_price', $item->selling_price ?? '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="mrp">MRP</label>
                    <input class="form-control" id="mrp" name="mrp" value="{{ old('mrp', $item->mrp ?? '0') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="certificate_number">Certificate</label>
                    <input class="form-control" id="certificate_number" name="certificate_number" value="{{ old('certificate_number', $item->certificate_number) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="hallmark">Hallmark</label>
                    <input class="form-control" id="hallmark" name="hallmark" value="{{ old('hallmark', $item->hallmark) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="image">Photo</label>
                    <input class="form-control" id="image" name="image" type="file" accept="image/*">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $item->notes) }}</textarea>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" type="submit">Save piece</button>
    </form>
@endsection
