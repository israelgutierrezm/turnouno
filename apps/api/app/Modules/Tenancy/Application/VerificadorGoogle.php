<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

/**
 * Verifica un ID token de Google (credential de Google Identity Services) y
 * devuelve la identidad si es valido para esta app, o null. La validacion incluye
 * firma/expiracion, `aud` (client_id de la app) e `iss` de Google.
 */
interface VerificadorGoogle
{
    public function verificar(string $credential): ?IdentidadGoogle;
}
