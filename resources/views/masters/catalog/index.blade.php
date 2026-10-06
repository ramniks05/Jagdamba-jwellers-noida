@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">{{ $title }}</h1>
        @can('create', $modelClass)
            <a class="btn btn-primary" href="{{ route($routeName.'.create') }}">Add {{ strtolower($singular) }}</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route($routeName.'.index') }}">
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
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->code }}</td>
                            <td>{{ $record->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                @if (isset($record->purities_count))
                                    <a href="{{ route('metals.purities.index', $record) }}">Purities ({{ $record->purities_count }})</a>
                                @endif
                                @can('update', $record)
                                    <a class="ms-2" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                                @endcan
                                @can('delete', $record)
                                    <form class="d-inline" method="POST" action="{{ route($routeName.'.destroy', $record) }}" onsubmit="return confirm('Remove this {{ strtolower($singular) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Nothing here yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $records->links() }}</div>
@endsection
