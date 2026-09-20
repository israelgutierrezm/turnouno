<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración (una sola fila) del programa de lealtad del estudio: si está activo y
 * las reglas de acumulación (puntos por asistencia y puntos por unidad de moneda
 * gastada).
 *
 * @property bool $activa
 * @property int $puntos_por_asistencia
 * @property int $puntos_por_moneda
 */
class ProgramaLealtadTenant extends Model
{
    protected $connection = 'tenant';

    protected $table = 'programa_lealtad';

    protected $fillable = ['activa', 'puntos_por_asistencia', 'puntos_por_moneda'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'activa' => 'boolean',
        'puntos_por_asistencia' => 'integer',
        'puntos_por_moneda' => 'integer',
    ];
}
