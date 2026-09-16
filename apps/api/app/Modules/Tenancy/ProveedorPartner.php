<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Plataformas de bienestar corporativo con las que un estudio puede integrarse
 * para aceptar check-ins de sus usuarios (la plataforma cubre la clase).
 */
enum ProveedorPartner: string
{
    case Wellhub = 'wellhub';
    case TotalPass = 'totalpass';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
