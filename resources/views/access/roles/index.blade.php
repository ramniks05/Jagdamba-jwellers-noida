@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Roles',
        'intro' => 'A role is a set of permissions, like Owner, Manager or Salesman. Locked roles come with the system.',
        'actions' => array_values(array_filter([
            ['url' => route('users.index'), 'label' => 'Users', 'icon' => 'people'],
            auth()->user()->can('create', App\Models\Role::class) ? ['url' => route('roles.create'), 'label' => 'Add role', 'icon' => 'plus-lg', 'primary' => true] : null,
        ])),
    ])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th class="num">Permissions</th>
                        <th class="num">Users</th>
                        <th>Type</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $role->name }}</span>
                                @if ($role->description)
                                    <div class="small text-secondary">{{ $role->description }}</div>
                                @endif
                            </td>
                            <td class="num text-nowrap">{{ $role->permissions_count }} <span class="text-secondary">of {{ $permissionTotal }}</span></td>
                            <td class="num">{{ $role->users_count }}</td>
                            <td>
                                @if ($role->is_system)
                                    <span class="order-status is-closed"><i class="bi bi-lock"></i> Locked</span>
                                @else
                                    <span class="order-status is-active">Custom</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('roles.edit', $role) }}">{{ auth()->user()->can('update', $role) ? 'Edit' : 'View' }}</a>
                                @can('delete', $role)
                                    <form class="d-inline" method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Remove the {{ addslashes($role->name) }} role?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2 align-baseline" type="submit">Remove</button>
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
