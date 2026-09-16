<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El proveedor de bienestar rechazo el codigo de check-in (invalido, vencido o ya
 * usado).
 */
class CheckinInvalido extends TenancyException
{
    public function codigo(): string
    {
        return 'CHECKIN_INVALID';
    }
}
