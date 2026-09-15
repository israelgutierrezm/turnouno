<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): agenda. Una sesión materializa una oferta en una
 * sucursal a una hora concreta. Las horas se guardan en UTC (calculadas desde la
 * hora local + zona horaria de la sucursal, cuyo snapshot se conserva). Sin
 * `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('sesiones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $tabla->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $tabla->timestamp('inicia_en'); // UTC
            $tabla->timestamp('termina_en'); // UTC
            $tabla->string('zona_horaria'); // snapshot de la zona de la sucursal
            $tabla->unsignedInteger('capacidad')->nullable();
            $tabla->string('estado')->default('programada'); // programada | cancelada
            $tabla->timestamps();

            $tabla->index(['sucursal_id', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('sesiones');
    }
};
