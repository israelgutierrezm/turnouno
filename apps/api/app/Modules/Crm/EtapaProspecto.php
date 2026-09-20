<?php

declare(strict_types=1);

namespace App\Modules\Crm;

/**
 * Etapa del prospecto (lead) dentro del embudo comercial tenant-local (R15). El flujo
 * abierto avanza Nuevo -> Contactado -> Interesado -> Prueba y cierra en Ganado
 * (convertido en miembro) o Perdido.
 */
enum EtapaProspecto: string
{
    case Nuevo = 'nuevo';
    case Contactado = 'contactado';
    case Interesado = 'interesado';
    case Prueba = 'prueba';
    case Ganado = 'ganado';
    case Perdido = 'perdido';

    /**
     * Etapas abiertas (en gestión), en orden de avance del embudo.
     *
     * @return list<self>
     */
    public static function abiertas(): array
    {
        return [self::Nuevo, self::Contactado, self::Interesado, self::Prueba];
    }

    /**
     * ¿Es una etapa terminal (ya no se gestiona el prospecto)?
     */
    public function esCerrada(): bool
    {
        return $this === self::Ganado || $this === self::Perdido;
    }
}
