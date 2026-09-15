<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\TipoPersonaTenant;

/**
 * Regla v1 de alumno activo: persona con perfil de alumno (miembro), activa, no
 * archivada y facturable en la BD del tenant. Cuenta personas DISTINTAS. Cuando la
 * operación (derechos/reservas/asistencia/compras) viva en la BD del tenant, una
 * v2 añadirá esas condiciones por periodo sin tocar mediciones históricas.
 */
class PoliticaAlumnosActivosV1 implements PoliticaAlumnosActivos
{
    public function version(): string
    {
        return 'v1';
    }

    public function contar(string $periodo): int
    {
        return PersonaTenant::query()
            ->where('tipo', TipoPersonaTenant::Miembro->value)
            ->where('activo', true)
            ->where('es_facturable', true)
            ->where('archivado', false)
            ->distinct()
            ->count('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function evidencia(string $periodo): array
    {
        return [
            'regla' => 'miembros activos, facturables y no archivados (personas distintas)',
            'periodo' => $periodo,
            'excluye' => ['archivados', 'no facturables', 'no miembros'],
        ];
    }
}
