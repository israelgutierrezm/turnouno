<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Models;

use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Models\Recurso;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definición recurrente de una clase: una oferta impartida en una sucursal según
 * sus reglas de recurrencia. De aquí se materializan las sesiones (ADR-0010).
 */
class PlantillaHorario extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'plantillas_horario';

    protected $fillable = [
        'oferta_id',
        'sucursal_id',
        'recurso_id',
        'nombre',
        'duracion_minutos',
        'capacidad',
        'vigente_desde',
        'vigente_hasta',
        'activa',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'duracion_minutos' => 'integer',
        'capacidad' => 'integer',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'activa' => 'boolean',
    ];

    /**
     * @return BelongsTo<Oferta, $this>
     */
    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<Recurso, $this>
     */
    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }

    /**
     * @return HasMany<ReglaRecurrencia, $this>
     */
    public function reglas(): HasMany
    {
        return $this->hasMany(ReglaRecurrencia::class);
    }

    /**
     * @return HasMany<Sesion, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }
}
