<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El inicio de sesion con Google no se pudo completar: el ID token es invalido, no
 * corresponde a esta app, el correo no esta verificado, o no hay una cuenta con ese
 * correo en el estudio (Google no crea cuentas; solo autentica a las existentes).
 */
class AutenticacionGoogleInvalida extends TenancyException
{
    public function codigo(): string
    {
        return 'GOOGLE_AUTH_FAILED';
    }
}
