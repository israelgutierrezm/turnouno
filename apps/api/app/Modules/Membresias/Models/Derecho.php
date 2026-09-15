<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Models;

use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Creditos\Models\MovimientoCredito;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Membresias\PoliticaReset;
use App\Modules\Membresias\PoliticaRollover;
use App\Modules\Organizaciones\Models\Sucursal;
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

    protected $fillable = [
        'acuerdo_id',
        'ambito',
        'actividad_id',
        'sucursal_id',
        'ilimitado',
        'politica_reset',
        'unidades_por_ciclo',
        'politica_rollover',
        'rollover_max',
        'ciclo_inicio',
        'ciclo_fin',
        'valido_desde',
        'valido_hasta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'ilimitado' => 'boolean',
        'politica_reset' => PoliticaReset::class,
        'politica_rollover' => PoliticaRollover::class,
        'unidades_por_ciclo' => 'integer',
        'rollover_max' => 'integer',
        'ciclo_inicio' => 'date',
        'ciclo_fin' => 'date',
        'valido_desde' => 'date',
        'valido_hasta' => 'date',
    ];

    /**
     * @return BelongsTo<Actividad, $this>
     */
    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

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
