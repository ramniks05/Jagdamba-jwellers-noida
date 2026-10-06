@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Customers</h1>
        @can('create', App\Models\Customer::class)
            <a class="btn btn-primary" href="{{ route('customers.create') }}"><i class="bi bi-person-plus"></i> Add customer</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('customers.index') }}">
        <div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Name, code, or mobile"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Mobile</th><th>Type</th><th></th></tr></thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->code }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->mobile }}</td>
                            <td>{{ $customer->customer_type->label() }}</td>
                            <td class="text-end"><a href="{{ route('customers.show', $customer) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
@endsection
