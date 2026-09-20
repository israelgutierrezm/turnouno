<?php

declare(strict_types=1);

namespace App\Modules\Crm;

/**
 * Tipo de interacción registrada en la bitácora de un prospecto (R15). `CambioEtapa`
 * y `Conversion` los registra el sistema; el resto los captura el staff.
 */
enum TipoActividadProspecto: string
{
    case Nota = 'nota';
    case Llamada = 'llamada';
    case Correo = 'correo';
    case Whatsapp = 'whatsapp';
    case Cita = 'cita';
    case CambioEtapa = 'cambio_etapa';
    case Conversion = 'conversion';
}
