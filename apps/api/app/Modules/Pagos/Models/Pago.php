<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intento de cobro de una orden a través de una pasarela.
 */
class Pago extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'pagos';

    protected $fillable = [
        'orden_id',
        'proveedor',
        'estado',
        'monto_minor',
        'moneda',
        'referencia_externa',
        'idempotency_key',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoPago::class,
        'monto_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<Orden, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }
}
