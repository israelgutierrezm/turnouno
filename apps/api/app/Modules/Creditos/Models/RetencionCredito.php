<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Models;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retención (hold) de unidades de un derecho. Reserva sin consumir; al confirmarse
 * se asienta un consumo en el ledger, al liberarse vuelve a estar disponible.
 */
class RetencionCredito extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'retenciones_credito';

    protected $fillable = ['derecho_id', 'unidades', 'estado', 'descripcion'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unidades' => 'integer',
        'estado' => EstadoRetencion::class,
    ];

    /**
     * @return BelongsTo<Derecho, $this>
     */
    public function derecho(): BelongsTo
    {
        return $this->belongsTo(Derecho::class);
    }
}
