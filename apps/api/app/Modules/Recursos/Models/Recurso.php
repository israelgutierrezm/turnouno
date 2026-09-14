<?php

declare(strict_types=1);

namespace App\Modules\Recursos\Models;

use App\Modules\Recursos\ModoRecurso;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Activo reservable o limitante de capacidad. Jerarquía por `recurso_padre_id`
 * (Instalación → Recurso → Recurso hijo). Modo UNIDAD o POOL.
 */
class Recurso extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'recursos';

    protected $fillable = ['instalacion_id', 'recurso_padre_id', 'nombre', 'tipo', 'modo', 'capacidad', 'estado'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'modo' => ModoRecurso::class,
        'capacidad' => 'integer',
    ];

    /**
     * @return BelongsTo<Instalacion, $this>
     */
    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class);
    }

    /**
     * @return BelongsTo<Recurso, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Recurso::class, 'recurso_padre_id');
    }

    /**
     * @return HasMany<Recurso, $this>
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(Recurso::class, 'recurso_padre_id');
    }
}
