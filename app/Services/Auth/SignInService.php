<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignInService
{
    public function attemptWeb(string $email, string $password, bool $remember): User
    {
        $email = Str::lower(trim($email));

        if (! Auth::attempt([
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ], $remember)) {
            $user = User::query()->where('email', $email)->first();

            if ($user && ! $user->is_active && Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => 'This account is inactive.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('company');

        if (! $user->company?->isOperational()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This shop cannot sign in right now.',
            ]);
        }

        $this->rememberLogin($user);

        return $user;
    }

    /**
     * Issues a Sanctum personal access token.
     * Authorization is checked from the user's roles on each request.
     */
    public function issueToken(string $email, string $password, string $deviceName): string
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account is inactive.',
            ]);
        }

        $user->loadMissing('company');

        if (! $user->company?->isOperational()) {
            throw ValidationException::withMessages([
                'email' => 'This shop cannot sign in right now.',
            ]);
        }

        $this->rememberLogin($user);

        return $user->createToken($deviceName, ['*'])->plainTextToken;
    }

    private function rememberLogin(User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
    }
}
