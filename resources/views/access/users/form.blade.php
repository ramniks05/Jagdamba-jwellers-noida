@extends('layouts.app')

@section('title', $user->exists ? 'Edit user' : 'Add user')

@section('content')
    @include('masters.partials.head', [
        'title' => $user->exists ? ($canSave ? 'Edit '.$user->name : $user->name) : 'Add user',
        'intro' => $canSave ? 'Login details, what they can do, and which branches they work at.' : 'You can see this user. Only an owner or manager can change it.',
    ])
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif
        <div class="row g-3 align-items-start">
            <div class="col-xl-7">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-person"></i> Person</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $user->name, 'col' => 'col-md-6', 'disabled' => ! $canSave, 'autofocus' => ! $user->exists])
                            @include('masters.partials.field', ['name' => 'phone', 'label' => 'Phone', 'value' => $user->phone, 'col' => 'col-md-6', 'inputmode' => 'tel', 'required' => false, 'disabled' => ! $canSave])
                            @include('masters.partials.field', ['name' => 'email', 'label' => 'Email (used to log in)', 'value' => $user->email, 'col' => 'col-12', 'type' => 'email', 'autocomplete' => 'off', 'disabled' => ! $canSave])
                        </div>

                        @if ($canSave)
                            <div class="weigh-section-title mt-4"><i class="bi bi-key"></i> {{ $user->exists ? 'Change password' : 'Password' }}</div>
                            <div class="row g-3">
                                @include('masters.partials.field', ['name' => 'password', 'label' => $user->exists ? 'New password' : 'Password', 'col' => 'col-md-6', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => ! $user->exists, 'help' => $user->exists ? 'Leave blank to keep the current password.' : 'At least 10 characters with upper and lower case, a number and a symbol.'])
                                @include('masters.partials.field', ['name' => 'password_confirmation', 'label' => 'Type it again', 'col' => 'col-md-6', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => ! $user->exists])
                            </div>
                        @endif

                        <div class="mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" id="is_active" name="is_active" type="checkbox" role="switch" value="1" @checked(filter_var(old('is_active', $user->is_active ?? true), FILTER_VALIDATE_BOOLEAN)) @disabled(! $canSave)>
                                <label class="form-check-label" for="is_active">Can log in</label>
                            </div>
                            <div class="form-text">Switch off when someone leaves. Their bills and history stay.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-shield-lock"></i> Role</div>
                        @error('roles')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @foreach ($roles as $role)
                            <div class="form-check mb-2">
                                <input class="form-check-input" id="role-{{ $role->uuid }}" name="roles[]" type="checkbox" value="{{ $role->uuid }}" @checked(in_array($role->uuid, $selectedRoles, true)) @disabled(! $canSave)>
                                <label class="form-check-label" for="role-{{ $role->uuid }}">
                                    <span class="fw-semibold">{{ $role->name }}</span>
                                    @if ($role->description)
                                        <span class="d-block small text-secondary">{{ $role->description }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-diagram-3"></i> Branches</div>
                        <div class="form-text mt-0 mb-2">Leave all unticked to allow every branch.</div>
                        @error('branches')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @foreach ($branches as $branch)
                            <div class="form-check">
                                <input class="form-check-input" id="branch-{{ $branch->uuid }}" name="branches[]" type="checkbox" value="{{ $branch->uuid }}" @checked(in_array($branch->uuid, $selectedBranches, true)) @disabled(! $canSave)>
                                <label class="form-check-label" for="branch-{{ $branch->uuid }}">{{ $branch->name }}@if ($branch->is_head_office) <span class="small text-secondary">(head office)</span>@endif</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @if ($canSave)
            @include('masters.partials.form-foot', ['label' => 'Save user', 'backUrl' => route('users.index')])
        @else
            <a class="btn btn-outline-secondary mt-3" href="{{ route('users.index') }}">Back to users</a>
        @endif
    </form>
@endsection
