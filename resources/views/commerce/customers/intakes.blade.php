@extends('layouts.app')

@section('title', 'Waiting approval')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Waiting approval</h1>
        <a class="btn btn-outline-secondary" href="{{ route('customers.qr') }}">Customer QR</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Name</th><th>Mobile</th><th>Address</th><th></th></tr></thead>
                <tbody>
                    @forelse ($intakes as $intake)
                        <tr>
                            <td>{{ $intake->name }}<div class="small text-secondary">{{ $intake->email }}</div></td>
                            <td>{{ $intake->mobile }}</td>
                            <td>{{ $intake->address_line1 }}, {{ $intake->city }} {{ $intake->postal_code }}</td>
                            <td class="text-end">
                                @can('create', App\Models\Customer::class)
                                    <form class="d-inline" method="POST" action="{{ route('customer-intakes.approve', $intake) }}">
                                        @csrf
                                        <button class="btn btn-primary btn-sm" type="submit">Approve</button>
                                    </form>
                                    <form class="d-inline" method="POST" action="{{ route('customer-intakes.reject', $intake) }}">
                                        @csrf
                                        <button class="btn btn-outline-secondary btn-sm" type="submit">Set aside</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No forms are waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $intakes->links() }}</div>
@endsection
