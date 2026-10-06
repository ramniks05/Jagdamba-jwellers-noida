@extends('layouts.app')

@section('title', $user->exists ? 'Edit user' : 'Add user')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $user->exists ? ($canSave ? 'Edit user' : 'View user') : 'Add user' }}</h1>
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif
        <div class="card mb-4">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required @disabled(! $canSave)>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required @disabled(! $canSave)>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" @disabled(! $canSave)>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="password">{{ $user->exists ? 'New password' : 'Password' }}</label>
                    <input class="form-control" id="password" name="password" type="password" @if (! $user->exists) required @endif autocomplete="new-password" @disabled(! $canSave)>
                    @if ($user->exists)
                        <div class="form-text">Leave blank to keep the current password.</div>
                    @endif
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @disabled(! $canSave)>
                </div>
                <div class="col-12 mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $user->is_active ?? true), FILTER_VALIDATE_BOOLEAN)) @disabled(! $canSave)>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">Roles</div>
                    <div class="card-body">
                        @foreach ($roles as $role)
                            <div class="form-check">
                                <input class="form-check-input" id="role-{{ $role->uuid }}" name="roles[]" type="checkbox" value="{{ $role->uuid }}" @checked(in_array($role->uuid, $selectedRoles, true)) @disabled(! $canSave)>
                                <label class="form-check-label" for="role-{{ $role->uuid }}">{{ $role->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">Branches</div>
                    <div class="card-body">
                        <p class="form-text">Leave every branch unchecked to allow all branches.</p>
                        @foreach ($branches as $branch)
                            <div class="form-check">
                                <input class="form-check-input" id="branch-{{ $branch->uuid }}" name="branches[]" type="checkbox" value="{{ $branch->uuid }}" @checked(in_array($branch->uuid, $selectedBranches, true)) @disabled(! $canSave)>
                                <label class="form-check-label" for="branch-{{ $branch->uuid }}">{{ $branch->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @if ($canSave)
            <button class="btn btn-primary mt-4" type="submit">Save user</button>
        @endif
    </form>
@endsection
