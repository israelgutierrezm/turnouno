<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Models;

use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento del ledger de créditos. `unidades` es un entero con signo (enteros
 * escalados, 1 crédito = 1000 unidades). El saldo de un derecho es su suma.
 */
class MovimientoCredito extends Model
{
    use BelongsToTenant;
    use HasPublicId;

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
     * @return BelongsTo<Derecho, $this>
     */
    public function derecho(): BelongsTo
    {
        return $this->belongsTo(Derecho::class);
    }
}
