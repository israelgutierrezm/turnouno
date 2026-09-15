<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): estructura del estudio. Organización → Sucursal (con
 * zona horaria, base para la agenda). Sin `tenant_id`: el aislamiento es por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('organizaciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('sucursales', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $tabla->string('nombre');
            $tabla->string('zona_horaria')->default('America/Mexico_City');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('sucursales');
        Schema::connection('tenant')->dropIfExists('organizaciones');
    }
};
