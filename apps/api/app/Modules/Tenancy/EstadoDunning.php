<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Estado de un proceso de morosidad (dunning, R10) de una membresía cuyo cobro falló.
 *
 * - `EnMora`: en periodo de gracia. El acuerdo sigue `Activo` (el socio puede
 *   reservar) mientras se reintenta el cobro.
 * - `Suspendido`: venció la gracia. El acuerdo pasa a `suspendido` (se bloquean
 *   reservas y acceso).
 * - `Regularizado`: el cobro se resolvió; el proceso se cierra y el acuerdo vuelve a
 *   `Activo`.
 */
enum EstadoDunning: string
{
    case EnMora = 'en_mora';
    case Suspendido = 'suspendido';
    case Regularizado = 'regularizado';
}
