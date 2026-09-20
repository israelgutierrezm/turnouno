<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Referidos\EstadoReferido;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atribución de un referido tenant-local (R23): quién refirió (referidor), el prospecto
 * generado y, al convertirse, la persona resultante y el cupón de recompensa.
 *
 * @property int $referidor_id
 * @property int|null $prospecto_id
 * @property int|null $persona_referida_id
 * @property int|null $recompensa_promocion_id
 */
class ReferidoTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'referidos';

    protected $fillable = [
        'referidor_id', 'codigo', 'prospecto_id', 'persona_referida_id',
        'estado', 'recompensa_promocion_id', 'convertido_en',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoReferido::class,
        'convertido_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<PersonaTenant, $this>
     */
    public function referidor(): BelongsTo
    {
        return $this->belongsTo(PersonaTenant::class, 'referidor_id');
    }

    /**
     * @return BelongsTo<ProspectoTenant, $this>
     */
    public function prospecto(): BelongsTo
    {
        return $this->belongsTo(ProspectoTenant::class, 'prospecto_id');
    }

    /**
     * @return BelongsTo<PromocionTenant, $this>
     */
    public function recompensa(): BelongsTo
    {
        return $this->belongsTo(PromocionTenant::class, 'recompensa_promocion_id');
    }
}
