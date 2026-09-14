<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Models\User;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Cookie/session authentication for the first-party Vue SPAs (Sanctum stateful).
 */
class SessionController
{
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => (string) $request->validated('email'),
            'password' => (string) $request->validated('password'),
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        return response()->json([
            'user' => $user instanceof User ? [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
