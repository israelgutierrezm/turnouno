<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Crm\TipoActividadProspecto;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interacción registrada en la bitácora de un prospecto (R15): nota, llamada, correo,
 * cita, cambio de etapa o conversión. Trazabilidad del seguimiento comercial.
 *
 * @property int|null $usuario_id
 */
class ProspectoActividadTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'prospecto_actividades';

    protected $fillable = ['prospecto_id', 'usuario_id', 'tipo', 'detalle'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoActividadProspecto::class,
    ];

    /**
     * @return BelongsTo<ProspectoTenant, $this>
     */
    public function prospecto(): BelongsTo
    {
        return $this->belongsTo(ProspectoTenant::class, 'prospecto_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
