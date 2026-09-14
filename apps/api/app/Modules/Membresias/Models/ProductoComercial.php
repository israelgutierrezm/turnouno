<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Models;

use App\Modules\Membresias\TipoProducto;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto comercial vendible. Otorga derechos (entitlements) al comprarse.
 * Dinero: `precio_minor` (BIGINT) + `moneda`. Créditos: enteros escalados.
 */
class ProductoComercial extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'productos_comerciales';

    protected $fillable = ['nombre', 'tipo', 'precio_minor', 'moneda', 'ilimitado', 'creditos_incluidos'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoProducto::class,
        'precio_minor' => 'integer',
        'ilimitado' => 'boolean',
        'creditos_incluidos' => 'integer',
    ];
}
