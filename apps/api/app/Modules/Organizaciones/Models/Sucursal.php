<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidad operativa dentro de un tenant. Una Sucursal NO es frontera de tenant
 * (ver docs/TENANCY.md); es donde viven agenda, recursos y personal.
 */
class Sucursal extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'sucursales';

    protected $fillable = ['marca_id', 'nombre', 'slug', 'zona_horaria', 'estado'];

    /**
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /**
     * @return HasMany<AsignacionPersonal, $this>
     */
    public function asignacionesPersonal(): HasMany
    {
        return $this->hasMany(AsignacionPersonal::class);
    }
}
