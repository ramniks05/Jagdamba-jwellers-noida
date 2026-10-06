@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="brand-mark">JJ</span>
                <div>
                    <div style="color: var(--maroon); font-weight: 700;">{{ config('app.name') }}</div>
                    <div class="small text-secondary">Shop counter</div>
                </div>
            </div>
            <h1 class="h4 mb-3">Sign in</h1>
            @include('partials.alerts')
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password">
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                        <label class="form-check-label" for="remember">Remember this browser</label>
                    </div>
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                </div>
                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>
        </div>
    </div>
@endsection
