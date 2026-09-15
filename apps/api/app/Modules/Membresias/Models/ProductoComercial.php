<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Models;

use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto comercial vendible. Otorga derechos (entitlements) al comprarse, con
 * su plantilla de ciclo/rollover/restricciones. Dinero: `precio_minor` (BIGINT) +
 * `moneda`. Créditos: enteros escalados.
 */
class ProductoComercial extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'productos_comerciales';

    protected $fillable = [
        'nombre',
        'tipo',
        'precio_minor',
        'moneda',
        'ilimitado',
        'creditos_incluidos',
        'actividad_id',
        'sucursal_id',
        'politica_reset',
        'unidades_por_ciclo',
        'politica_rollover',
        'rollover_max',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoProducto::class,
        'precio_minor' => 'integer',
        'ilimitado' => 'boolean',
        'creditos_incluidos' => 'integer',
        'politica_reset' => PoliticaReset::class,
        'politica_rollover' => PoliticaRollover::class,
        'unidades_por_ciclo' => 'integer',
        'rollover_max' => 'integer',
    ];
}
