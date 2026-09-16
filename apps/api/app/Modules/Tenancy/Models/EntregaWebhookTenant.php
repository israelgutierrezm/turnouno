<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intento de entrega de un evento a un {@see WebhookSalienteTenant} (R40). Registra el
 * estado (pendiente/entregado/fallido), el código HTTP, los intentos y el último
 * error, para reintentos y auditoría de la entrega.
 */
class EntregaWebhookTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'entregas_webhook';

    protected $fillable = [
        'webhook_id', 'evento_ulid', 'evento_tipo', 'payload', 'estado',
        'http_status', 'intentos', 'ultimo_error', 'entregado_en',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'http_status' => 'integer',
        'intentos' => 'integer',
        'entregado_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<WebhookSalienteTenant, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(WebhookSalienteTenant::class, 'webhook_id');
    }
}
