<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function sendLink(string $email): void
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->is_active || ! $user->company?->isOperational()) {
            return;
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Please wait before requesting another reset link.',
            ]);
        }
    }

    public function reset(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            [
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $password): void {
                if (! $user->is_active || ! $user->company?->isOperational()) {
                    throw ValidationException::withMessages([
                        'email' => 'This reset link is invalid or has expired.',
                    ]);
                }

                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or has expired.',
            ]);
        }
    }
}
