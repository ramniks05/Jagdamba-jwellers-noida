@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Roles</h1>
        @can('create', App\Models\Role::class)
            <a class="btn btn-primary" href="{{ route('roles.create') }}">Add role</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Users</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>
                                {{ $role->name }}
                                @if ($role->is_system)
                                    <span class="badge text-bg-secondary">Locked</span>
                                @endif
                            </td>
                            <td>{{ $role->code }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('roles.edit', $role) }}">{{ auth()->user()->can('update', $role) ? 'Edit' : 'View' }}</a>
                                @can('delete', $role)
                                    <form class="d-inline" method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Remove this role?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
