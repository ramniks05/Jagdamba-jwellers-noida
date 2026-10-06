@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Reset password</h1>
            <p class="text-secondary">Enter the email on the account. If it matches, a reset link will be sent.</p>
            @include('partials.alerts')
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <button class="btn btn-primary w-100" type="submit">Send reset link</button>
            </form>
            <div class="mt-3">
                <a href="{{ route('login') }}">Back to sign in</a>
            </div>
        </div>
    </div>
@endsection
