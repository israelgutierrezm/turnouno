<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Models;

use App\Modules\Agenda\Models\Sesion;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Personas\Models\Persona;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva de una persona en una sesión, respaldada por un derecho y (si es
 * limitado) una retención de crédito.
 */
class Reserva extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'reservas';

    protected $fillable = [
        'sesion_id',
        'persona_id',
        'derecho_id',
        'retencion_id',
        'estado',
        'unidades',
        'idempotency_key',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoReserva::class,
        'unidades' => 'integer',
    ];

    /**
     * @return BelongsTo<Sesion, $this>
     */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return BelongsTo<Derecho, $this>
     */
    public function derecho(): BelongsTo
    {
        return $this->belongsTo(Derecho::class);
    }

    /**
     * @return BelongsTo<RetencionCredito, $this>
     */
    public function retencion(): BelongsTo
    {
        return $this->belongsTo(RetencionCredito::class, 'retencion_id');
    }
}
