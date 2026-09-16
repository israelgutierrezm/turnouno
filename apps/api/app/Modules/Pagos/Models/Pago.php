<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\MetodoPago;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Intento de cobro de una orden a través de una pasarela.
 *
 * Columnas declaradas para el analizador (Larastan pierde el esquema legacy por la
 * colisión de nombres con las tablas del data plane; ver {@see BelongsToTenant}).
 *
 * @property string|null $comprobante_ruta
 * @property Carbon|null $comprobante_subido_en
 */
class Pago extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'pagos';

    /**
     * Datos de checkout para el cliente (client_secret, init_point, voucher…).
     * Transitorio: NO se persiste, solo viaja en la respuesta del cobro.
     *
     * @var array<string, mixed>
     */
    public array $checkout = [];

    /**
     * Datos que aporta el cliente para el cobro (card_token, device_session_id…).
     * Transitorio.
     *
     * @var array<string, mixed>
     */
    public array $datosCliente = [];

    protected $fillable = [
        'orden_id',
        'proveedor',
        'metodo',
        'estado',
        'monto_minor',
        'moneda',
        'referencia_externa',
        'idempotency_key',
        'comprobante_ruta',
        'comprobante_subido_en',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoPago::class,
        'metodo' => MetodoPago::class,
        'monto_minor' => 'integer',
        'comprobante_subido_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<Orden, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }
}
