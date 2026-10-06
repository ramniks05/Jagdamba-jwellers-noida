@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Users</h1>
        @can('create', App\Models\User::class)
            <a class="btn btn-primary" href="{{ route('users.create') }}">Add user</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('users.index') }}">
        <div class="col-md-4">
            <input class="form-control" name="search" value="{{ $search }}" placeholder="Search name or email">
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
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                @can('view', $user)
                                    <a href="{{ route('users.edit', $user) }}">{{ auth()->user()->can('update', $user) ? 'Edit' : 'View' }}</a>
                                @endcan
                                @can('delete', $user)
                                    <form class="d-inline" method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Remove this user?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
@endsection
