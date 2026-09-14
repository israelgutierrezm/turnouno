<?php

declare(strict_types=1);

namespace App\Modules\Recursos\Models;

use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Espacio físico dentro de una sucursal. Contiene recursos (ver RESOURCE_ENGINE).
 */
class Instalacion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'instalaciones';

    protected $fillable = ['sucursal_id', 'nombre'];

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return HasMany<Recurso, $this>
     */
    public function recursos(): HasMany
    {
        return $this->hasMany(Recurso::class);
    }
}
