<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Plantilla de horario: definición recurrente de una clase de una oferta en una
// sucursal. Sus reglas de recurrencia se materializan en sesiones concretas
// (ver ADR-0010). La duración y capacidad son las de cada sesión generada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_horario', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('recurso_id')->nullable()->constrained('recursos')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->unsignedInteger('duracion_minutos');
            $table->unsignedInteger('capacidad')->nullable();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('oferta_id');
            $table->index('sucursal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_horario');
    }
};
