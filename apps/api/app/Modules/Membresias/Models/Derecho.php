<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Models;

use App\Modules\Creditos\Models\MovimientoCredito;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Derecho (entitlement) otorgado por un acuerdo. El saldo NO se guarda aquí:
 * se deriva del ledger (`movimientos`). Puede ser ilimitado.
 */
class Derecho extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'derechos';

    protected $fillable = ['acuerdo_id', 'ambito', 'ilimitado', 'valido_desde', 'valido_hasta'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'ilimitado' => 'boolean',
        'valido_desde' => 'date',
        'valido_hasta' => 'date',
    ];

    /**
     * @return BelongsTo<Acuerdo, $this>
     */
    public function acuerdo(): BelongsTo
    {
        return $this->belongsTo(Acuerdo::class);
    }

    /**
     * @return HasMany<MovimientoCredito, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCredito::class);
    }

    /**
     * @return HasMany<RetencionCredito, $this>
     */
    public function retenciones(): HasMany
    {
        return $this->hasMany(RetencionCredito::class);
    }
}
