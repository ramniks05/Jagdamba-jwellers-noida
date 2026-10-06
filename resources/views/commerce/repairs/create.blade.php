@extends('layouts.app')

@section('title', 'Take repair')

@section('content')
    <h1 class="page-title h3 mb-4">Take repair</h1>
    <form method="POST" action="{{ route('repairs.store') }}">
        @csrf
        <div class="card"><div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Customer</label>
                <select class="form-select" name="customer_uuid" required>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->uuid }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Shop piece, if this is ours</label>
                <select class="form-select" name="item_uuid">
                    <option value="">Customer jewellery</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->uuid }}">{{ $item->item_code }} · {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Description</label><input class="form-control" name="description" value="{{ old('description') }}" required></div>
            <div class="col-md-6"><label class="form-label">Problem</label><input class="form-control" name="problem" value="{{ old('problem') }}" required></div>
            <div class="col-md-3"><label class="form-label">Technician</label><input class="form-control" name="technician" value="{{ old('technician') }}"></div>
            <div class="col-md-3"><label class="form-label">Estimate</label><input class="form-control" name="estimated_cost" value="{{ old('estimated_cost', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Expected</label><input class="form-control" type="date" name="expected_on" value="{{ old('expected_on') }}"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save repair</button></div>
        </div></div>
    </form>
@endsection
