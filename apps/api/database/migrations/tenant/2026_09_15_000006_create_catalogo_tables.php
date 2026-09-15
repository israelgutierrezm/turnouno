<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): catálogo del estudio. Programa → Actividad →
 * (Nivel, Oferta). Primer módulo operativo migrado al data plane; ya no necesita
 * `tenant_id` (el aislamiento es por base). Los slugs son únicos dentro del tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('programas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('slug')->unique();
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('actividades', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $tabla->string('nombre');
            $tabla->string('slug')->unique();
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('niveles', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('actividad_id')->constrained('actividades')->cascadeOnDelete();
            $tabla->string('nombre');
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('ofertas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('actividad_id')->constrained('actividades')->cascadeOnDelete();
            $tabla->string('nombre');
            $tabla->string('modalidad')->default('grupal'); // grupal | privada | individual
            $tabla->unsignedInteger('capacidad')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ofertas');
        Schema::connection('tenant')->dropIfExists('niveles');
        Schema::connection('tenant')->dropIfExists('actividades');
        Schema::connection('tenant')->dropIfExists('programas');
    }
};
