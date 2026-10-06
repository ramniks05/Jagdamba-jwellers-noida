<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\PasswordChangeRequest;
use App\Http\Requests\Access\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Access\AccountService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(): UserResource
    {
        return new UserResource(auth()->user()->load(['roles', 'branches']));
    }

    public function update(ProfileRequest $request, AccountService $accounts): UserResource
    {
        $user = $accounts->updateProfile($request->user(), $request->validated());

        return new UserResource($user->load(['roles', 'branches']));
    }

    public function password(PasswordChangeRequest $request, AccountService $accounts): JsonResponse
    {
        $accounts->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Password updated. Sign in again.',
        ]);
    }
}
