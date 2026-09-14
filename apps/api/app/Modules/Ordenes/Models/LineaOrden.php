<?php

declare(strict_types=1);

namespace App\Modules\Ordenes\Models;

use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una orden. `beneficiario` es quien recibirá el derecho (puede diferir
 * del comprador de la orden); si es nulo, se asume el comprador.
 */
class LineaOrden extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'lineas_orden';

    protected $fillable = [
        'orden_id',
        'producto_comercial_id',
        'beneficiario_id',
        'cantidad',
        'precio_unitario_minor',
        'subtotal_minor',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario_minor' => 'integer',
        'subtotal_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<Orden, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }

    /**
     * @return BelongsTo<ProductoComercial, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoComercial::class, 'producto_comercial_id');
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'beneficiario_id');
    }
}
