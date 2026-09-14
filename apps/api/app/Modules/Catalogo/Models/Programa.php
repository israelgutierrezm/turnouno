<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa → Actividad → (Nivel, Oferta). Categoría de más alto nivel del
 * catálogo (ver docs/DOMAIN_MODEL.md). tenant-scoped.
 */
class Programa extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'programas';

    protected $fillable = ['nombre', 'slug'];

    /**
     * @return HasMany<Actividad, $this>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class);
    }
}
