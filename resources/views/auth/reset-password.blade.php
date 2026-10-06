@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Choose a new password</h1>
            @include('partials.alerts')
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">New password</label>
                    <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary w-100" type="submit">Update password</button>
            </form>
        </div>
    </div>
@endsection
