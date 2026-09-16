<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): COMUNICACIONES (R28), consumidor del outbox (R39). Una
 * `plantilla_mensaje` por (clave de evento, canal) define asunto/cuerpo con
 * marcadores {{...}}; ante un evento, se genera un `mensaje` (encolado) que un relay
 * envia (interno = bandeja in-app; email) con estados y reintentos. Sin `tenant_id`:
 * aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('plantillas_mensaje', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('clave');   // tipo de evento que la dispara, p. ej. reserva.creada
            $tabla->string('canal');   // interno | email
            $tabla->string('asunto');
            $tabla->text('cuerpo');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->unique(['clave', 'canal']);
        });

        Schema::connection('tenant')->create('mensajes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $tabla->foreignId('plantilla_id')->nullable()->constrained('plantillas_mensaje')->nullOnDelete();
            $tabla->string('canal');
            $tabla->string('destinatario')->nullable(); // email (interno usa la bandeja de la persona)
            $tabla->string('asunto');
            $tabla->text('cuerpo');
            $tabla->string('estado')->default('encolado'); // encolado | enviado | fallido
            $tabla->unsignedInteger('intentos')->default(0);
            $tabla->string('ultimo_error')->nullable();
            $tabla->string('evento_ulid')->nullable(); // trazabilidad/deduplicacion
            $tabla->dateTime('enviado_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['estado', 'id']);
            $tabla->index('persona_id');
            $tabla->index('evento_ulid');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('mensajes');
        Schema::connection('tenant')->dropIfExists('plantillas_mensaje');
    }
};
