<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Creditos\TipoMovimiento;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento del ledger de creditos tenant-local. La suma de `unidades` de un derecho
 * es su saldo (fuente de verdad, nunca un saldo guardado).
 */
class MovimientoCreditoTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'movimientos_credito';

    protected $fillable = ['derecho_id', 'tipo', 'unidades', 'descripcion'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoMovimiento::class,
        'unidades' => 'integer',
    ];

    /**
     * @return BelongsTo<DerechoTenant, $this>
     */
    public function derecho(): BelongsTo
    {
        return $this->belongsTo(DerechoTenant::class, 'derecho_id');
    }
}
