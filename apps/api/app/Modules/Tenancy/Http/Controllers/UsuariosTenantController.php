<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\CatalogoDePermisosTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de usuarios tenant-local (personal del estudio). El propietario/admin
 * invita a personal con un rol; la cuenta se crea inactiva con un token de
 * activación de un solo uso (la contraseña la define el invitado al activar, en
 * `/app/{estudio}/activar`). Opera sobre la BD del estudio resuelto.
 */
class UsuariosTenantController
{
    public function invitar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'rol' => ['required', Rule::in(CatalogoDePermisosTenant::rolesAsignables())],
        ]);

        // Email único dentro de la BD del tenant.
        if (Usuario::query()->where('email', $validado['email'])->exists()) {
            throw ValidationException::withMessages(['email' => ['Ya existe un usuario con ese correo en este estudio.']]);
        }

        $token = Str::random(48);

        $usuario = Usuario::query()->create([
            'name' => $validado['nombre'],
            'email' => $validado['email'],
            'rol' => $validado['rol'],
            'activo' => false,
            'password' => null,
            'activation_token' => hash('sha256', $token),
        ]);

        return response()->json(['data' => [
            'usuario' => ['ulid' => $usuario->ulid, 'nombre' => $usuario->name, 'email' => $usuario->email, 'rol' => $usuario->rol],
            'activacion' => app()->environment('production') ? null : ['email' => $usuario->email, 'token' => $token],
        ]], 201);
    }
}
