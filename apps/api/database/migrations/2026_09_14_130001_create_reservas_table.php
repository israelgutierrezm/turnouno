<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reserva: una persona (participante) toma cupo de una sesión usando un derecho.
// Si el derecho es limitado se crea una retención (hold) que se confirma (consume)
// o se libera al cancelar según la política. `idempotency_key` evita reservas
// duplicadas ante reintentos (ver BOOKING_ENGINE.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('derecho_id')->constrained('derechos')->cascadeOnDelete();
            $table->foreignId('retencion_id')->nullable()->constrained('retenciones_credito')->nullOnDelete();
            $table->string('estado')->default('confirmada');
            $table->unsignedInteger('unidades')->default(0);
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['sesion_id', 'estado']);
            $table->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
