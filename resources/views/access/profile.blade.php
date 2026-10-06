@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <h1 class="page-title h3 mb-4">Your profile</h1>
    <div class="row g-4">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="mb-3">
                            <div class="form-label">Roles</div>
                            @forelse ($user->roles as $role)
                                <span class="badge text-bg-secondary">{{ $role->name }}</span>
                            @empty
                                <span class="text-secondary">No role assigned.</span>
                            @endforelse
                        </div>
                        <button class="btn btn-primary" type="submit">Save profile</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header bg-white">Change password</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Current password</label>
                            <input class="form-control" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">New password</label>
                            <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password">
                            <div class="form-text">At least 10 characters, with upper and lower case letters, a number, and a symbol.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Confirm password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary" type="submit">Update password</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
