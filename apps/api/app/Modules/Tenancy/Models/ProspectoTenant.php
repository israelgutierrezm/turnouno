<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Crm\EtapaProspecto;
use App\Modules\Crm\OrigenProspecto;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prospecto (lead) comercial tenant-local (R15): cliente potencial en el embudo. Al
 * ganarlo se enlaza a la `persona` (miembro) creada en la conversión.
 *
 * @property int|null $persona_id
 * @property int|null $responsable_id
 */
class ProspectoTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'prospectos';

    protected $fillable = [
        'persona_id', 'responsable_id', 'sucursal_id', 'nombre', 'email', 'telefono',
        'origen', 'etapa', 'interes', 'motivo', 'proximo_seguimiento', 'convertido_en',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'origen' => OrigenProspecto::class,
        'etapa' => EtapaProspecto::class,
        'proximo_seguimiento' => 'date',
        'convertido_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<PersonaTenant, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(PersonaTenant::class, 'persona_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /**
     * @return BelongsTo<SucursalTenant, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(SucursalTenant::class, 'sucursal_id');
    }

    /**
     * @return HasMany<ProspectoActividadTenant, $this>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(ProspectoActividadTenant::class, 'prospecto_id');
    }
}
