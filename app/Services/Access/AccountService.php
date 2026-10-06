<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $user->fill([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'] ?? null,
        ])->save();

        return $user->refresh();
    }

    public function changePassword(User $user, string $current, string $next, ?string $keepSessionId = null): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->forceFill(['password' => $next])->save();
        $user->tokens()->delete();

        $sessions = DB::table('sessions')->where('user_id', $user->id);

        if ($keepSessionId) {
            $sessions->where('id', '!=', $keepSessionId);
        }

        $sessions->delete();
    }
}
