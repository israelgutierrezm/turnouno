<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sucursal del estudio (con zona horaria), tenant-local. Base para materializar la
 * agenda en UTC según su zona.
 */
class SucursalTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'sucursales';

    protected $fillable = ['organizacion_id', 'nombre', 'zona_horaria'];

    /**
     * @return BelongsTo<OrganizacionTenant, $this>
     */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(OrganizacionTenant::class, 'organizacion_id');
    }
}
