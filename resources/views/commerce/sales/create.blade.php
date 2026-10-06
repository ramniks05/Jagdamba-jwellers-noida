@extends('layouts.app')

@section('title', 'New bill')

@section('content')
    <h1 class="page-title h3 mb-2">New bill</h1>
    <p class="text-secondary">Each piece is billed with the rate saved for its metal and purity. Split the payment across cash, UPI, card, bank, or cheque. Anything left unpaid stays on the customer.</p>
    <form class="row g-2 mb-3" method="GET" action="{{ route('sales.create') }}">
        <div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Find a piece"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>
    <form method="POST" action="{{ route('sales.store') }}">
        @csrf
        <div class="card mb-4">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="customer_uuid">Customer</label>
                    <select class="form-select" id="customer_uuid" name="customer_uuid" required>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->uuid }}" @selected(old('customer_uuid') === $customer->uuid)>{{ $customer->name }}{{ $customer->is_system ? '' : ' ('.$customer->code.')' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="discount">Bill discount</label>
                    <input class="form-control" id="discount" name="discount" value="{{ old('discount', '0') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="notes">Note</label>
                    <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}">
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white">Available pieces</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th></th><th>Code</th><th>Name</th><th>Metal</th><th>Net g</th></tr></thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td><input type="checkbox" name="item_ids[]" value="{{ $item->uuid }}" @checked(in_array($item->uuid, old('item_ids', []), true))></td>
                                <td>{{ $item->item_code }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->metalType?->name }} {{ $item->purity?->name }}</td>
                                <td>{{ $item->net_weight }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No available pieces match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white">Payments</div>
            <div class="card-body">
                @foreach ([0, 1, 2] as $slot)
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <select class="form-select" name="payments[{{ $slot }}][method]">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="bank">Bank transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-3"><input class="form-control" name="payments[{{ $slot }}][amount]" value="{{ old('payments.'.$slot.'.amount') }}" placeholder="Amount"></div>
                        <div class="col-md-4"><input class="form-control" name="payments[{{ $slot }}][reference]" value="{{ old('payments.'.$slot.'.reference') }}" placeholder="Reference"></div>
                    </div>
                @endforeach
            </div>
        </div>
        <button class="btn btn-primary" type="submit">Save invoice</button>
    </form>
@endsection
