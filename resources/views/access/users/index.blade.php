@extends('layouts.app')

@section('title', 'Users')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Users',
        'intro' => 'Everyone who can log in. A role decides what they can do; branches decide where.',
        'actions' => array_values(array_filter([
            auth()->user()->can('viewAny', App\Models\Role::class) ? ['url' => route('roles.index'), 'label' => 'Roles', 'icon' => 'shield-lock'] : null,
            auth()->user()->can('create', App\Models\User::class) ? ['url' => route('users.create'), 'label' => 'Add user', 'icon' => 'plus-lg', 'primary' => true] : null,
        ])),
    ])
    <form class="d-flex flex-wrap align-items-center gap-2 mb-3" method="GET" action="{{ route('users.index') }}">
        @if ($show !== 'all')
            <input type="hidden" name="show" value="{{ $show }}">
        @endif
        <input class="form-control master-search" name="search" value="{{ $search }}" placeholder="Name, email or phone" aria-label="Search">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Search</button>
        @if ($search !== '')
            <a class="btn btn-outline-secondary" href="{{ route('users.index', array_filter(['show' => $show === 'all' ? null : $show])) }}">Clear</a>
        @endif
        <div class="d-flex flex-wrap gap-2 ms-md-auto">
            @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('users.index', array_filter(['search' => $search, 'show' => $key === 'all' ? null : $key])) }}">{{ $label }} <span class="opacity-75">{{ $counts[$key] }}</span></a>
            @endforeach
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Branches</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $user->name }}</span>
                                @if ($user->is(auth()->user()))
                                    <span class="small text-secondary">(you)</span>
                                @endif
                                <div class="small text-secondary">{{ collect([$user->email, $user->phone])->filter()->join(' · ') }}</div>
                            </td>
                            <td>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>{{ $user->branches->isEmpty() ? 'All branches' : $user->branches->pluck('name')->join(', ') }}</td>
                            <td>@include('masters.partials.status', ['active' => $user->is_active, 'off' => 'Inactive'])</td>
                            <td class="text-end text-nowrap">
                                @can('view', $user)
                                    <a href="{{ route('users.edit', $user) }}">{{ auth()->user()->can('update', $user) ? 'Edit' : 'View' }}</a>
                                @endcan
                                @can('delete', $user)
                                    <form class="d-inline" method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Remove {{ addslashes($user->name) }}? They will no longer be able to log in.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2 align-baseline" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ $search !== '' || $show !== 'all' ? 'No user matches this search.' : 'No users yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $users])
@endsection
