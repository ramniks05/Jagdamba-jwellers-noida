@extends('layouts.app')

@section('title', 'Outstanding')

@section('content')
    <h1 class="page-title h3 mb-4">Customer outstanding</h1>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Customer</th><th>Mobile</th><th>Balance</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('customers.show', $row['customer']) }}">{{ $row['customer']->name }}</a></td>
                            <td>{{ $row['customer']->mobile }}</td>
                            <td>{{ $row['balance'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Nobody has an outstanding balance.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
