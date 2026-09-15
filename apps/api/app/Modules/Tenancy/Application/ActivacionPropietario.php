<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Exceptions\ActivacionInvalida;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Support\Str;

/**
 * Activación de la cuenta de un usuario tenant-local (propietario u otros): genera
 * un token de un solo uso (se guarda su hash en la BD del tenant) y, al activar,
 * fija la contraseña y marca la cuenta como activa. Sin contraseñas por defecto.
 */
class ActivacionPropietario
{
    public function __construct(private readonly GestorDeConexionTenant $gestor) {}

    /**
     * Genera (o refresca) el token de activación y devuelve el valor en claro
     * (para el enlace de activación; solo se muestra una vez).
     */
    public function generar(Estudio $estudio, ?string $email = null): string
    {
        $token = Str::random(48);
        $correo = $email ?? $estudio->contacto_email;

        $this->gestor->ejecutarEn($estudio, function () use ($correo, $token): void {
            $usuario = Usuario::query()->where('email', $correo)->firstOrFail();
            $usuario->forceFill(['activation_token' => hash('sha256', $token), 'activo' => false])->save();
        });

        return $token;
    }

    /**
     * Activa la cuenta: valida el token, fija la contraseña y marca activo.
     */
    public function activar(Estudio $estudio, string $email, string $token, string $password): Usuario
    {
        return $this->gestor->ejecutarEn($estudio, function () use ($email, $token, $password): Usuario {
            $usuario = Usuario::query()->where('email', $email)->first();

            if ($usuario === null
                || $usuario->activation_token === null
                || ! hash_equals((string) $usuario->activation_token, hash('sha256', $token))) {
                throw new ActivacionInvalida('El enlace de activación no es válido.');
            }

            $usuario->forceFill([
                'password' => $password, // el cast `hashed` lo hashea una vez
                'activo' => true,
                'activation_token' => null,
                'email_verified_at' => now(),
            ])->save();

            return $usuario;
        });
    }
}
