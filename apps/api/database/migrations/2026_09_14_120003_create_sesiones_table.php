<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sesión: instancia concreta y fechada de una clase. `inicia_en`/`termina_en` se
// guardan en UTC (calculadas desde la hora local + zona de la sucursal); se
// conserva `zona_horaria` como snapshot para mostrar. La capacidad es el
// inventario que Booking (Slice 7) protegerá con locks. Una sesión ad-hoc
// (privada) tiene `plantilla_horario_id` nulo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plantilla_horario_id')->nullable()->constrained('plantillas_horario')->nullOnDelete();
            $table->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('recurso_id')->nullable()->constrained('recursos')->nullOnDelete();
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en');
            $table->string('zona_horaria');
            $table->unsignedInteger('capacidad')->nullable();
            $table->string('estado')->default('programada');
            $table->timestamps();

            // Materialización idempotente: no se duplica una sesión de la misma
            // plantilla en el mismo instante al regenerar un rango (ADR-0010).
            $table->unique(['plantilla_horario_id', 'inicia_en'], 'sesiones_plantilla_inicio_unica');
            $table->index(['sucursal_id', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
