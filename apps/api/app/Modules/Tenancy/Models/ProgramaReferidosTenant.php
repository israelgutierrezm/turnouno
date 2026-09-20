<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Ordenes\TipoPromocion;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración (una fila) del programa de referidos tenant-local (R23): tipo y valor de
 * la recompensa (cupón) y su vigencia en días.
 *
 * @property int $recompensa_valor
 * @property int $vigencia_dias
 */
class ProgramaReferidosTenant extends Model
{
    protected $connection = 'tenant';

    protected $table = 'programa_referidos';

    protected $fillable = ['recompensa_tipo', 'recompensa_valor', 'vigencia_dias', 'activo'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'recompensa_tipo' => TipoPromocion::class,
        'recompensa_valor' => 'integer',
        'vigencia_dias' => 'integer',
        'activo' => 'boolean',
    ];
}
