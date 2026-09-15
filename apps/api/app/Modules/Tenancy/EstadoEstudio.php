<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Ciclo de vida de un estudio (tenant) en el control plane.
 * `provisioning` → `trialing` → `active`; `suspended`/`cancelled` cortan el acceso.
 */
enum EstadoEstudio: string
{
    case Provisioning = 'provisioning';
    case Trialing = 'trialing';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * ¿El estudio puede operar (login/uso del tenant)?
     */
    public function operativo(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }
}
