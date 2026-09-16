<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): check-ins de plataformas de bienestar (Wellhub /
 * TotalPass). Registra el acceso de un usuario de la plataforma a una sesion, tras
 * validar su codigo contra la API del proveedor con las credenciales del estudio.
 * NO consume creditos del estudio (la plataforma cubre la clase). `referencia_externa`
 * unica evita usar el mismo codigo dos veces. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('checkins', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $tabla->string('proveedor'); // wellhub | totalpass
            $tabla->string('referencia_externa')->unique();
            $tabla->string('nombre_usuario')->nullable();
            $tabla->string('estado')->default('validado'); // validado | rechazado
            $tabla->timestamp('registrado_en');
            $tabla->timestamps();

            $tabla->index(['sesion_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('checkins');
    }
};
