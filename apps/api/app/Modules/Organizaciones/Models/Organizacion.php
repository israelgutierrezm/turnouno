<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organización dentro de un tenant. Contiene marcas, que contienen sucursales
 * (Tenant → Organización → Marca → Sucursal, ver docs/DOMAIN_MODEL.md).
 *
 * Columna declarada para el analizador (Larastan pierde el esquema legacy por la
 * colisión de nombres con las tablas del data plane; ver {@see BelongsToTenant}).
 *
 * @property string $slug
 */
class Organizacion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'organizaciones';

    protected $fillable = ['nombre', 'slug'];

    /**
     * @return HasMany<Marca, $this>
     */
    public function marcas(): HasMany
    {
        return $this->hasMany(Marca::class);
    }
}
