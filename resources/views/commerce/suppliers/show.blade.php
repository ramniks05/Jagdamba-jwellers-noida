@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-title h3 mb-1">{{ $supplier->name }}</h1>
            <div class="text-secondary">{{ $supplier->code }} · {{ $supplier->contact_name }}</div>
        </div>
        @can('update', $supplier)
            <a class="btn btn-outline-secondary" href="{{ route('suppliers.edit', $supplier) }}">Edit</a>
        @endcan
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="stat-label">Payable</div><div class="fw-semibold">{{ $payable }}</div><div class="small text-secondary">Amount still owed to this supplier.</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="stat-label">Tax</div><div>GSTIN {{ $supplier->gstin ?: '—' }}</div><div>PAN {{ $supplier->pan ?: '—' }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="stat-label">Bank</div><div>{{ $supplier->bank_name ?: '—' }}</div><div>{{ $supplier->account_number }} {{ $supplier->ifsc }}</div></div></div></div>
    </div>
    @can('create', App\Models\Payment::class)
        <form class="card mb-4" method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
            @csrf
            <div class="card-header bg-white">Pay supplier</div>
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
                <div class="col-md-2"><button class="btn btn-primary" type="submit">Save payment</button></div>
            </div>
        </form>
    @endcan
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
                        <tr><td colspan="4">No supplier bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
