<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Integraciones;

/**
 * Validador de check-ins de Wellhub (antes Gympass).
 */
class ValidadorWellhub extends ValidadorPartnerHttp
{
    public function nombre(): string
    {
        return 'wellhub';
    }

    protected function baseUrlPorDefecto(): string
    {
        return 'https://api.wellhub.com/v1';
    }
}
