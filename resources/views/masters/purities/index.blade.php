@extends('layouts.app')

@section('title', $metal->name.' purity')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title h3 mb-1">{{ $metal->name }} purity</h1>
            <a href="{{ route('metals.index') }}">Back to metals</a>
        </div>
        @can('create', App\Models\Purity::class)
            <a class="btn btn-primary" href="{{ route('metals.purities.create', $metal) }}">Add purity</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('metals.purities.index', $metal) }}">
        <div class="col-md-4">
            <input class="form-control" name="search" value="{{ $search }}" placeholder="Search name or code">
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
                        <th>Name</th>
                        <th>Code</th>
                        <th>Fineness</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purities as $purity)
                        <tr>
                            <td>{{ $purity->name }}</td>
                            <td>{{ $purity->code }}</td>
                            <td>{{ \App\Services\Masters\Fineness::percentFromRatio((string) $purity->fineness) }}%</td>
                            <td>{{ $purity->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                @can('update', $purity)
                                    <a href="{{ route('metals.purities.edit', [$metal, $purity]) }}">Edit</a>
                                @endcan
                                @can('delete', $purity)
                                    <form class="d-inline" method="POST" action="{{ route('metals.purities.destroy', [$metal, $purity]) }}" onsubmit="return confirm('Remove this purity?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No purities yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $purities->links() }}</div>
@endsection
