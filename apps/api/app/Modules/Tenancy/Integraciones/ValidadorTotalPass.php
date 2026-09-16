<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Integraciones;

/**
 * Validador de check-ins de TotalPass.
 */
class ValidadorTotalPass extends ValidadorPartnerHttp
{
    public function nombre(): string
    {
        return 'totalpass';
    }

    protected function baseUrlPorDefecto(): string
    {
        return 'https://api.totalpass.com/v1';
    }
}
