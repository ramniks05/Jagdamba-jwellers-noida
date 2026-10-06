<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\ForgotPasswordRequest;
use App\Http\Requests\Access\ResetPasswordRequest;
use App\Services\Access\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, PasswordResetService $resets): RedirectResponse
    {
        $resets->sendLink($request->string('email')->toString());

        return back()->with('status', 'If an account exists for that email, a reset link is on the way.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(ResetPasswordRequest $request, PasswordResetService $resets): RedirectResponse
    {
        $resets->reset(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()->route('login')->with('status', 'Password updated. Sign in with the new password.');
    }
}
