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
 * Gestión de usuarios tenant-local (personal y personas con acceso). El
 * propietario/admin invita personal con un rol; la cuenta se crea inactiva con un
 * token de activación de un solo uso (la contraseña la define el invitado al
 * activar, en `/app/{estudio}/activar`).
 *
 * Multi-rol: desde el apartado Usuarios se asignan varios roles a la misma cuenta
 * (p. ej. miembro y profesor). El rol `propietario` está protegido: siempre debe
 * existir al menos uno, solo un propietario puede conceder/quitar ese rol, y nadie
 * puede quitárselo a sí mismo. Opera sobre la BD del estudio resuelto.
 */
class UsuariosTenantController
{
    /**
     * Lista los instructores del estudio (usuarios con el rol instructor) para poder
     * asignarlos a sesiones. Solo ulid + nombre; nunca datos sensibles.
     */
    public function instructores(): JsonResponse
    {
        $instructores = Usuario::query()
            ->whereJsonContains('roles', 'instructor')
            ->orderBy('name')
            ->get(['ulid', 'name']);

        return response()->json([
            'data' => $instructores->map(static fn (Usuario $u): array => [
                'id' => $u->ulid,
                'nombre' => $u->name,
            ])->all(),
        ]);
    }

    /**
     * Todos los usuarios del estudio con sus roles, para el apartado Usuarios.
     */
    public function index(): JsonResponse
    {
        $usuarios = Usuario::query()->orderBy('name')->get();

        return response()->json([
            'data' => $usuarios->map(fn (Usuario $u): array => $this->presentar($u))->all(),
            'roles' => CatalogoDePermisosTenant::todosLosRoles(),
        ]);
    }

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
            'roles' => [$validado['rol']],
            'activo' => false,
            'password' => null,
            'activation_token' => hash('sha256', $token),
        ]);

        return response()->json(['data' => [
            'usuario' => $this->presentar($usuario),
            'activacion' => app()->environment('production') ? null : ['email' => $usuario->email, 'token' => $token],
        ]], 201);
    }

    /**
     * Reasigna el conjunto de roles de un usuario. Protege el rol `propietario`.
     */
    public function actualizarRoles(Request $request): JsonResponse
    {
        $usuario = Usuario::query()->where('ulid', (string) $request->route('usuario'))->firstOrFail();

        $validado = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(CatalogoDePermisosTenant::todosLosRoles())],
        ]);

        /** @var list<string> $rolesNuevos */
        $rolesNuevos = array_values(array_unique($validado['roles']));

        $this->protegerPropietario($request, $usuario, $rolesNuevos);

        $usuario->update([
            'roles' => $rolesNuevos,
            'rol' => CatalogoDePermisosTenant::rolPrincipal($rolesNuevos),
        ]);

        return response()->json(['data' => $this->presentar($usuario->refresh())]);
    }

    /**
     * Reglas del rol `propietario`: siempre ≥1, solo un propietario lo concede/quita,
     * y nadie se lo quita a sí mismo.
     *
     * @param  list<string>  $rolesNuevos
     */
    private function protegerPropietario(Request $request, Usuario $usuario, array $rolesNuevos): void
    {
        $eraPropietario = $usuario->tieneRol('propietario');
        $seraPropietario = in_array('propietario', $rolesNuevos, true);

        if ($eraPropietario === $seraPropietario) {
            return; // No se toca el rol de dueño.
        }

        $actor = $request->attributes->get('usuario_tenant');
        if (! ($actor instanceof Usuario) || ! $actor->tieneRol('propietario')) {
            throw ValidationException::withMessages([
                'roles' => ['Solo un dueño puede conceder o quitar el rol de dueño.'],
            ]);
        }

        if ($eraPropietario && ! $seraPropietario) {
            if ((int) $actor->getKey() === (int) $usuario->getKey()) {
                throw ValidationException::withMessages([
                    'roles' => ['No puedes quitarte a ti mismo el rol de dueño.'],
                ]);
            }

            if ($this->contarPropietarios() <= 1) {
                throw ValidationException::withMessages([
                    'roles' => ['Debe existir al menos un dueño en el estudio.'],
                ]);
            }
        }
    }

    private function contarPropietarios(): int
    {
        return Usuario::query()->whereJsonContains('roles', 'propietario')->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Usuario $usuario): array
    {
        $roles = $usuario->rolesEfectivos();

        return [
            'id' => $usuario->ulid,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'rol' => CatalogoDePermisosTenant::rolPrincipal($roles),
            'roles' => $roles,
            'activo' => (bool) $usuario->activo,
        ];
    }
}
