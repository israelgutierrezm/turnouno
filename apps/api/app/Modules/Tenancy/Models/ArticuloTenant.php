<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Artículo minorista tenant-local (R21): producto de venta en caja (agua, ropa,
 * suplementos…), con stock por sucursal en el ledger de inventario. Separado de los
 * productos comerciales (membresías/paquetes).
 *
 * @property int $precio_minor
 */
class ArticuloTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'articulos';

    protected $fillable = ['nombre', 'sku', 'precio_minor', 'moneda', 'activo'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'precio_minor' => 'integer',
        'activo' => 'boolean',
    ];
}
