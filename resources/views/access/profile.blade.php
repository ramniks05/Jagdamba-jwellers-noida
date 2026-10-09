@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Your profile',
        'intro' => 'Your name is printed on the bills you make. Change your password here.',
    ])
    <div class="row g-3 align-items-start">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-person"></i> About you</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $user->name, 'col' => 'col-12'])
                            @include('masters.partials.field', ['name' => 'email', 'label' => 'Email (used to log in)', 'value' => $user->email, 'col' => 'col-md-7', 'type' => 'email', 'autocomplete' => 'username'])
                            @include('masters.partials.field', ['name' => 'phone', 'label' => 'Phone', 'value' => $user->phone, 'col' => 'col-md-5', 'inputmode' => 'tel', 'required' => false])
                        </div>
                        <div class="mt-3">
                            <div class="stat-label mb-1">Your role</div>
                            @forelse ($user->roles as $role)
                                <span class="order-status is-active">{{ $role->name }}</span>
                            @empty
                                <span class="text-secondary">No role yet. Ask the owner to give you one.</span>
                            @endforelse
                        </div>
                        @include('masters.partials.form-foot', ['label' => 'Save profile', 'backUrl' => route('overview')])
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-key"></i> Change password</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'current_password', 'label' => 'Current password', 'col' => 'col-12', 'type' => 'password', 'autocomplete' => 'current-password'])
                            @include('masters.partials.field', ['name' => 'password', 'label' => 'New password', 'col' => 'col-md-6', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 10 characters with upper and lower case, a number and a symbol.'])
                            @include('masters.partials.field', ['name' => 'password_confirmation', 'label' => 'Type it again', 'col' => 'col-md-6', 'type' => 'password', 'autocomplete' => 'new-password'])
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-shield-check"></i> Update password</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
