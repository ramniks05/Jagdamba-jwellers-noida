<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenRequest;
use App\Services\Auth\SignInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTokenController extends Controller
{
    public function store(TokenRequest $request, SignInService $signIn): JsonResponse
    {
        $token = $signIn->issueToken(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString(),
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Token revoked.']);
    }
}
