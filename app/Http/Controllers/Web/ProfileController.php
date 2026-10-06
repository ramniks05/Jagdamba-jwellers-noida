<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\PasswordChangeRequest;
use App\Http\Requests\Access\ProfileRequest;
use App\Services\Access\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user()->load('roles');

        return view('access.profile', ['user' => $user]);
    }

    public function update(ProfileRequest $request, AccountService $accounts): RedirectResponse
    {
        $accounts->updateProfile($request->user(), $request->validated());

        return redirect()->route('profile.edit')->with('status', 'Profile saved.');
    }

    public function password(PasswordChangeRequest $request, AccountService $accounts): RedirectResponse
    {
        $accounts->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
            $request->session()->getId(),
        );

        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('status', 'Password updated.');
    }
}
