<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Models\User;
use App\Modules\Identity\Http\Requests\TokenLoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Personal access token authentication for the Flutter/mobile client.
 */
class TokenController
{
    public function store(TokenLoginRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $password = (string) $request->validated('password');
        $deviceName = (string) $request->validated('device_name');

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Token revoked.']);
    }
}
