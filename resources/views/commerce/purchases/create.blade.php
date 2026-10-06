@extends('layouts.app')

@section('title', 'Receive purchase')

@section('content')
    <h1 class="page-title h3 mb-4">Receive purchase</h1>
    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf
        <div class="card mb-3"><div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Supplier</label>
                <select class="form-select" name="supplier_uuid" required>
                    <option value="">Choose</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->uuid }}" @selected(old('supplier_uuid') === $supplier->uuid)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Piece name</label>
                <input class="form-control" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Item code</label>
                <input class="form-control" name="item_code" value="{{ old('item_code') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Metal</label>
                <select class="form-select" name="metal_uuid" required>
                    @foreach ($metals as $metal)
                        <option value="{{ $metal->uuid }}" @selected(old('metal_uuid') === $metal->uuid)>{{ $metal->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Purity</label>
                <select class="form-select" name="purity_uuid" required>
                    @foreach ($metals as $metal)
                        @foreach ($metal->purities as $purity)
                            <option value="{{ $purity->uuid }}" @selected(old('purity_uuid') === $purity->uuid)>{{ $metal->name }} {{ $purity->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Location</label>
                <select class="form-select" name="location_uuid" required>
                    @foreach ($locations as $location)
                        <option value="{{ $location->uuid }}" @selected(old('location_uuid') === $location->uuid)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Gross g</label><input class="form-control" name="gross_weight" value="{{ old('gross_weight', '10') }}" required></div>
            <div class="col-md-3"><label class="form-label">Stone g</label><input class="form-control" name="stone_weight" value="{{ old('stone_weight', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Other g</label><input class="form-control" name="other_weight" value="{{ old('other_weight', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Stone value</label><input class="form-control" name="stone_value" value="{{ old('stone_value', '0') }}"></div>
            <div class="col-md-3">
                <label class="form-label">Making</label>
                <select class="form-select" name="making_method_uuid">
                    <option value="">None</option>
                    @foreach ($making as $method)
                        <option value="{{ $method->uuid }}" @selected(old('making_method_uuid') === $method->uuid)>{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Making value</label><input class="form-control" name="making_value" value="{{ old('making_value', '0') }}"></div>
            <div class="col-md-3">
                <label class="form-label">Wastage</label>
                <select class="form-select" name="wastage_method_uuid">
                    <option value="">None</option>
                    @foreach ($wastage as $method)
                        <option value="{{ $method->uuid }}" @selected(old('wastage_method_uuid') === $method->uuid)>{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Wastage value</label><input class="form-control" name="wastage_value" value="{{ old('wastage_value', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Discount</label><input class="form-control" name="discount" value="{{ old('discount', '0') }}"></div>
            <div class="col-md-9"><label class="form-label">Notes</label><input class="form-control" name="notes" value="{{ old('notes') }}"></div>
        </div></div>
        <div class="card mb-3"><div class="card-body row g-3">
            <div class="col-12 fw-semibold">Payment now</div>
            <div class="col-md-3">
                <select class="form-select" name="payments[0][method]">
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" name="payments[0][amount]" placeholder="Amount" value="{{ old('payments.0.amount') }}"></div>
            <div class="col-md-3"><input class="form-control" name="payments[0][reference]" placeholder="Reference" value="{{ old('payments.0.reference') }}"></div>
        </div></div>
        <button class="btn btn-primary" type="submit">Save purchase</button>
    </form>
@endsection
