<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): motor de automatización (R16). Una regla reacciona a un
 * evento de dominio (trigger), evalúa una condición sobre su payload y, tras un retraso
 * opcional, ejecuta una acción. La única acción v1 es crear una TAREA de seguimiento
 * para el staff (el envío de mensajes lo cubre Comunicaciones/R28, no se duplica).
 *
 * - `reglas_automatizacion.condiciones`: JSON {campo: valor} que TODOS deben coincidir
 *   contra el payload del evento (comparación por igualdad de texto). {} = sin filtro.
 * - `reglas_automatizacion.delay_minutos`: minutos que se suman a "ahora" para el
 *   vencimiento de la tarea generada (retraso del seguimiento).
 * - `tareas.regla_id` + `tareas.evento_ulid`: origen (null si es manual) e idempotencia
 *   (una regla no genera dos tareas por el mismo evento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('reglas_automatizacion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('evento');
            $tabla->json('condiciones')->nullable();
            $tabla->string('accion')->default('crear_tarea');
            $tabla->string('titulo_plantilla');
            $tabla->text('detalle_plantilla')->nullable();
            $tabla->unsignedInteger('delay_minutos')->default(0);
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();

            $tabla->index('evento');
        });

        Schema::connection('tenant')->create('tareas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('titulo');
            $tabla->text('detalle')->nullable();
            $tabla->unsignedBigInteger('persona_id')->nullable();
            $tabla->unsignedBigInteger('responsable_id')->nullable();
            $tabla->unsignedBigInteger('regla_id')->nullable();
            $tabla->string('evento_ulid')->nullable();
            $tabla->timestamp('vence_en')->nullable();
            $tabla->string('estado')->default('pendiente');
            $tabla->timestamp('completada_en')->nullable();
            $tabla->unsignedBigInteger('completada_por')->nullable();
            $tabla->timestamps();

            $tabla->index('estado');
            $tabla->index('vence_en');
            $tabla->index('responsable_id');
            // Idempotencia: una regla no crea dos tareas por el mismo evento.
            $tabla->unique(['regla_id', 'evento_ulid']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tareas');
        Schema::connection('tenant')->dropIfExists('reglas_automatizacion');
    }
};
