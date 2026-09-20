<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * Se intento facturar sin que el estudio (receptor) tenga cargados sus datos fiscales
 * (RFC, razón social, régimen, CP). El dueño debe completarlos antes de facturar.
 */
class DatosFiscalesRequeridos extends TenancyException
{
    public function codigo(): string
    {
        return 'FISCAL_DATA_REQUIRED';
    }

    public function estadoHttp(): int
    {
        return 422;
    }
}
