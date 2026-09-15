<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El token de activación es inválido, expiró o ya se usó.
 */
class ActivacionInvalida extends TenancyException
{
    public function codigo(): string
    {
        return 'ACTIVATION_INVALID';
    }
}
