<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\ForgotPasswordRequest;
use App\Http\Requests\Access\ResetPasswordRequest;
use App\Services\Access\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function store(ForgotPasswordRequest $request, PasswordResetService $resets): JsonResponse
    {
        $resets->sendLink($request->string('email')->toString());

        return response()->json([
            'message' => 'If an account exists for that email, a reset link is on the way.',
        ]);
    }

    public function update(ResetPasswordRequest $request, PasswordResetService $resets): JsonResponse
    {
        $resets->reset(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }
}
