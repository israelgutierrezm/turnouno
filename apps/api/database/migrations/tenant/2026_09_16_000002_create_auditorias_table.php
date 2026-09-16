<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): bitacora de auditoria APPEND-ONLY de operaciones
 * sensibles (ajuste/otorgamiento de credito, override, reembolso, cambio de
 * membresia/precio/permiso, validacion de documentos, etc.). Registra quien (actor),
 * que (accion), sobre que (entidad), el antes/despues, el motivo, la IP y el
 * correlation-id. No tiene `updated_at`: los asientos no se editan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('auditorias', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('actor_id')->nullable(); // usuario tenant que ejecuto (snapshot informativo)
            $tabla->string('actor_nombre')->nullable();
            $tabla->string('accion'); // p. ej. credito.top_up, pago.reembolso, acceso.override
            $tabla->string('entidad_tipo')->nullable();
            $tabla->string('entidad_id')->nullable();
            $tabla->string('motivo')->nullable();
            $tabla->json('antes')->nullable();
            $tabla->json('despues')->nullable();
            $tabla->string('ip', 45)->nullable();
            $tabla->string('correlation_id')->nullable();
            $tabla->timestamp('created_at')->nullable()->index();

            $tabla->index(['entidad_tipo', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('auditorias');
    }
};
