<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Exceptions\AutenticacionGoogleInvalida;
use App\Modules\Tenancy\Models\Usuario;

/**
 * Inicio de sesion con Google tenant-local. Verifica el ID token y resuelve al
 * usuario en la BD del estudio YA resuelto (por google_id, o por correo para
 * enlazarlo la primera vez). Google NO crea cuentas: solo autentica a las que ya
 * existen (invitadas/registradas), y al hacerlo activa la cuenta (correo verificado
 * por Google) y enlaza el google_id.
 */
class AutenticacionGoogleTenant
{
    public function __construct(private readonly VerificadorGoogle $verificador) {}

    public function ejecutar(string $credential): Usuario
    {
        $identidad = $this->verificador->verificar($credential);

        if ($identidad === null) {
            throw new AutenticacionGoogleInvalida('No se pudo validar el acceso con Google.');
        }

        $usuario = Usuario::query()->where('google_id', $identidad->googleId)->first()
            ?? Usuario::query()->where('email', $identidad->email)->first();

        if (! $usuario instanceof Usuario) {
            throw new AutenticacionGoogleInvalida('No hay una cuenta con ese correo en este estudio.');
        }

        $usuario->forceFill([
            'google_id' => $identidad->googleId,
            'activo' => true,
            'activation_token' => null,
            'email_verified_at' => now(),
        ])->save();

        return $usuario;
    }
}
