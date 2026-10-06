@extends('layouts.app')

@section('title', $role->exists ? 'Edit role' : 'Add role')

@section('content')
    <h1 class="page-title h3 mb-2">{{ $role->exists ? ($canSave ? 'Edit role' : $role->name) : 'Add role' }}</h1>
    @if ($role->is_system)
        <p class="text-secondary">This role is locked. Its permissions stay aligned with the system catalog.</p>
    @endif
    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif
        <div class="card mb-4">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $role->name) }}" required @disabled(! $canSave)>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="description">Description</label>
                    <input class="form-control" id="description" name="description" value="{{ old('description', $role->description) }}" @disabled(! $canSave)>
                </div>
            </div>
        </div>
        @foreach ($groups as $group => $permissions)
            <div class="card mb-3">
                <div class="card-header bg-white">{{ $groupLabels[$group] ?? $group }}</div>
                <div class="card-body permission-grid">
                    @foreach ($permissions as $permission)
                        <div class="form-check">
                            <input class="form-check-input" id="permission-{{ $permission->code }}" name="permissions[]" type="checkbox" value="{{ $permission->code }}" @checked(in_array($permission->code, $selected, true)) @disabled(! $canSave)>
                            <label class="form-check-label" for="permission-{{ $permission->code }}">{{ $permission->name }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if ($canSave)
            <button class="btn btn-primary" type="submit">Save role</button>
        @endif
    </form>
@endsection
