<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Asiento del OUTBOX de eventos de dominio tenant-local (R39). Se escribe en la misma
 * transaccion que el cambio de estado; el relay lo publica despues y marca
 * `publicado_en`. `payload` guarda los datos del evento.
 */
class EventoOutboxTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'eventos_outbox';

    protected $fillable = [
        'tipo', 'agregado_tipo', 'agregado_id', 'payload', 'correlation_id',
        'ocurrido_en', 'publicado_en', 'intentos', 'ultimo_error',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'ocurrido_en' => 'datetime',
        'publicado_en' => 'datetime',
        'intentos' => 'integer',
    ];
}
