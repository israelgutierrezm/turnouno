<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Listeners;

use App\Modules\Tenancy\Application\EntregarWebhookTenant;
use App\Modules\Tenancy\Events\EventoDeDominioTenant;
use App\Modules\Tenancy\Models\WebhookSalienteTenant;

/**
 * Consumidor del outbox (R40): ante un {@see EventoDeDominioTenant} publicado por el
 * relay, entrega el evento (firmado) a cada endpoint saliente ACTIVO suscrito a ese
 * tipo. Corre dentro de la conexión del tenant activa. No propaga errores de entrega
 * (los deja como `fallido` para reintento) para no afectar la publicación del outbox.
 */
class EnviarWebhooksSalientes
{
    public function __construct(private readonly EntregarWebhookTenant $entregador) {}

    public function handle(EventoDeDominioTenant $evento): void
    {
        $endpoints = WebhookSalienteTenant::query()
            ->where('activo', true)
            ->get()
            ->filter(fn (WebhookSalienteTenant $w): bool => $w->suscritoA($evento->tipo));

        if ($endpoints->isEmpty()) {
            return;
        }

        $sobre = [
            'id' => $evento->eventoUlid,
            'tipo' => $evento->tipo,
            'agregado_tipo' => $evento->agregadoTipo,
            'agregado_id' => $evento->agregadoId,
            'datos' => $evento->payload,
            'correlation_id' => $evento->correlationId,
        ];

        foreach ($endpoints as $endpoint) {
            $entrega = $endpoint->entregas()->create([
                'evento_ulid' => $evento->eventoUlid,
                'evento_tipo' => $evento->tipo,
                'payload' => $sobre,
                'estado' => 'pendiente',
                'intentos' => 0,
            ]);

            $this->entregador->entregar($entrega, $endpoint);
        }
    }
}
