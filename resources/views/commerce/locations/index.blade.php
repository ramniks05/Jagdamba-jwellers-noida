@extends('layouts.app')

@section('title', 'Stock locations')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Stock locations</h1>
        @can('create', App\Models\StockLocation::class)
            <a class="btn btn-primary" href="{{ route('locations.create') }}">Add location</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Name</th><th>Kind</th><th>Branch</th><th>Pieces</th><th></th></tr></thead>
                <tbody>
                    @foreach ($locations as $location)
                        <tr>
                            <td>{{ $location->label() }} <span class="text-secondary">{{ $location->code }}</span></td>
                            <td>{{ $location->kind->label() }}</td>
                            <td>{{ $location->branch?->name }}</td>
                            <td>{{ $location->items_count }}</td>
                            <td class="text-end">@can('update', $location)<a href="{{ route('locations.edit', $location) }}">Edit</a>@endcan</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $locations->links() }}</div>
@endsection
