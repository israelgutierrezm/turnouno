<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): reservas (booking) y asistencias. Una reserva ata una
 * persona a una sesion, respaldada por un derecho y (si es limitado) una retencion
 * de credito. La asistencia liquida esa retencion (presente consume, ausente
 * pierde). `idempotency_key` unico: como la BD es por tenant, la idempotencia queda
 * naturalmente aislada por estudio. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('reservas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->foreignId('derecho_id')->constrained('derechos')->cascadeOnDelete();
            $tabla->foreignId('retencion_id')->nullable()->constrained('retenciones_credito')->nullOnDelete();
            $tabla->string('estado')->default('confirmada'); // confirmada | en_espera | cancelada
            $tabla->unsignedInteger('unidades')->default(0);
            $tabla->string('idempotency_key')->nullable()->unique();
            $tabla->timestamps();

            $tabla->index(['sesion_id', 'estado']);
            $tabla->index('persona_id');
        });

        Schema::connection('tenant')->create('asistencias', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $tabla->string('estado'); // presente | ausente
            $tabla->dateTime('registrada_en');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('asistencias');
        Schema::connection('tenant')->dropIfExists('reservas');
    }
};
