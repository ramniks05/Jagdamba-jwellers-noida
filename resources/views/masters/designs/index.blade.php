@extends('layouts.app')

@section('title', 'Designs')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Designs</h1>
        @can('create', App\Models\Design::class)
            <a class="btn btn-primary" href="{{ route('designs.create') }}">Add design</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('designs.index') }}">
        <div class="col-md-4">
            <input class="form-control" name="search" value="{{ $search }}" placeholder="Search name or number">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Collection</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($designs as $design)
                        <tr>
                            <td>{{ $design->design_number }}</td>
                            <td>{{ $design->name }}</td>
                            <td>{{ $design->collection?->name ?: '—' }}</td>
                            <td>{{ $design->category?->name ?: '—' }}</td>
                            <td>{{ $design->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                @can('update', $design)
                                    <a href="{{ route('designs.edit', $design) }}">Edit</a>
                                @endcan
                                @can('delete', $design)
                                    <form class="d-inline" method="POST" action="{{ route('designs.destroy', $design) }}" onsubmit="return confirm('Remove this design?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No designs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $designs->links() }}</div>
@endsection
