@extends('layouts.app')

@section('title', $customer->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-title h3 mb-1">{{ $customer->name }}</h1>
            <div class="text-secondary">{{ $customer->code }} · {{ $customer->mobile }}</div>
        </div>
        @can('update', $customer)
            <a class="btn btn-outline-secondary" href="{{ route('customers.edit', $customer) }}">Edit</a>
        @endcan
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="stat-label">Outstanding</div><div class="fw-semibold">{{ $balance }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="stat-label">KYC</div><div>{{ $customer->kyc_status->label() }}</div><div class="small text-secondary">{{ $customer->customer_type->label() }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="stat-label">Tax</div><div>PAN {{ $customer->pan ?: '—' }}</div><div>GSTIN {{ $customer->gstin ?: '—' }}</div></div></div></div>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-white">Customer details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="stat-label">Email</div><div>{{ $customer->email ?: '—' }}</div></div>
                <div class="col-md-4"><div class="stat-label">Date of birth</div><div>{{ $customer->dob?->format('d M Y') ?: '—' }}</div></div>
                <div class="col-md-4"><div class="stat-label">Anniversary</div><div>{{ $customer->anniversary?->format('d M Y') ?: '—' }}</div></div>
                <div class="col-12">
                    <div class="stat-label">Address</div>
                    <div>{{ $customer->address_line1 ?: '—' }}</div>
                    @if ($customer->address_line2)
                        <div>{{ $customer->address_line2 }}</div>
                    @endif
                    <div>{{ collect([$customer->city, $customer->state, $customer->postal_code])->filter()->join(', ') }}</div>
                </div>
            </div>
        </div>
    </div>
    @can('create', App\Models\Payment::class)
        @if (! $customer->is_system)
            <form class="card mb-4" method="POST" action="{{ route('customers.payments.store', $customer) }}">
                @csrf
                <div class="card-header bg-white">Receive payment</div>
                <div class="card-body row g-2">
                    <div class="col-md-3">
                        <select class="form-select" name="method" required>
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input class="form-control" name="amount" placeholder="Amount" required></div>
                    <div class="col-md-3"><input class="form-control" name="reference" placeholder="Reference"></div>
                    <div class="col-md-2"><button class="btn btn-primary" type="submit">Save receipt</button></div>
                </div>
            </form>
        @endif
    @endcan
    <div class="card mb-4">
        <div class="card-header bg-white">Bills</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Number</th><th>When</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->number }}</td>
                            <td>{{ $sale->sold_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $sale->total }}</td>
                            <td class="text-end"><a href="{{ route('sales.show', $sale) }}">Invoice</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-white">Ledger</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>When</th><th>Narration</th><th>Debit</th><th>Credit</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->occurred_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>{{ $entry->narration }}</td>
                            <td>{{ $entry->direction->value === 'debit' ? $entry->amount : '' }}</td>
                            <td>{{ $entry->direction->value === 'credit' ? $entry->amount : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
