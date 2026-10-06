@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Suppliers</h1>
        @can('create', App\Models\Supplier::class)
            <a class="btn btn-primary" href="{{ route('suppliers.create') }}">Add supplier</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('suppliers.index') }}">
        <div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Name, code, or mobile"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Mobile</th><th></th></tr></thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->code }}</td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->mobile }}</td>
                            <td class="text-end"><a href="{{ route('suppliers.show', $supplier) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No suppliers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
