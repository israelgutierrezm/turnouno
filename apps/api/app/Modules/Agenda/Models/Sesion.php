<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Models;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Models\Recurso;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instancia fechada de una clase. `inicia_en`/`termina_en` viven en UTC; la
 * `capacidad` es el inventario que Booking (Slice 7) protegerá con locks.
 */
class Sesion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'sesiones';

    protected $fillable = [
        'plantilla_horario_id',
        'oferta_id',
        'sucursal_id',
        'recurso_id',
        'inicia_en',
        'termina_en',
        'zona_horaria',
        'capacidad',
        'estado',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'capacidad' => 'integer',
        'estado' => EstadoSesion::class,
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
     * @return BelongsTo<PlantillaHorario, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaHorario::class, 'plantilla_horario_id');
    }

    /**
     * @return HasMany<AsignacionSesion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionSesion::class);
    }
}
