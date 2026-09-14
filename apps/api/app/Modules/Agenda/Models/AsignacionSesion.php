<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Models;

use App\Modules\Agenda\RolSesion;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo staff ↔ sesión (instructor o asistente). Base de la visibilidad de
 * agenda del instructor.
 */
class AsignacionSesion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'asignaciones_sesion';

    protected $fillable = [
        'sesion_id',
        'persona_id',
        'rol',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rol' => RolSesion::class,
    ];

    /**
     * @return BelongsTo<Sesion, $this>
     */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
