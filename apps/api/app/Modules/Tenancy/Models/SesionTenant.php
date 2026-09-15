<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\EstadoSesionTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesión de la agenda (oferta materializada en una sucursal), tenant-local. Horas
 * en UTC + snapshot de zona horaria.
 */
class SesionTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'sesiones';

    protected $fillable = ['oferta_id', 'sucursal_id', 'inicia_en', 'termina_en', 'zona_horaria', 'capacidad', 'estado'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'capacidad' => 'integer',
        'estado' => EstadoSesionTenant::class,
    ];

    /**
     * @return BelongsTo<OfertaTenant, $this>
     */
    public function oferta(): BelongsTo
    {
        return $this->belongsTo(OfertaTenant::class, 'oferta_id');
    }

    /**
     * @return BelongsTo<SucursalTenant, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(SucursalTenant::class, 'sucursal_id');
    }
}
