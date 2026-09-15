<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organización del estudio, tenant-local (BD del tenant).
 */
class OrganizacionTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'organizaciones';

    protected $fillable = ['nombre'];

    /**
     * @return HasMany<SucursalTenant, $this>
     */
    public function sucursales(): HasMany
    {
        return $this->hasMany(SucursalTenant::class, 'organizacion_id')->orderBy('id');
    }
}
