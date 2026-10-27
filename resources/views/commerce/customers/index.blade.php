@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="page-title h3 mb-0">Customers</h1>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('customers.qr') }}">Customer QR</a>
            <a class="btn btn-outline-secondary" href="{{ route('customer-intakes.index') }}">Waiting approval</a>
            @can('create', App\Models\Customer::class)
                <a class="btn btn-primary" href="{{ route('customers.create') }}"><i class="bi bi-person-plus"></i> Add customer</a>
            @endcan
        </div>
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('customers.index') }}">
        <div class="col-md-3"><input class="form-control" name="search" value="{{ $search }}" placeholder="Name, code, or mobile"></div>
        <div class="col-md-3">
            <select class="form-select" name="society">
                <option value="">All societies</option>
                @foreach ($societies as $society)
                    <option value="{{ $society->uuid }}" @selected($societyUuid === $society->uuid)>{{ $society->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="premium">
                <option value="" @selected($premium === '')>All customers</option>
                <option value="yes" @selected($premium === 'yes')>Premium only</option>
                <option value="no" @selected($premium === 'no')>Not premium</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Mobile</th><th>Society</th><th>Type</th><th></th></tr></thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->code }}</td>
                            <td>
                                {{ $customer->name }}
                                @if ($customer->is_premium)
                                    <span class="badge text-bg-warning ms-1">Premium</span>
                                @endif
                            </td>
                            <td>{{ $customer->mobile }}</td>
                            <td>{{ $customer->society?->name ?: '—' }}</td>
                            <td>{{ $customer->customer_type->label() }}</td>
                            <td class="text-end"><a href="{{ route('customers.show', $customer) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
@endsection
