<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): STAFF multi + sustitucion + NOMINA (R17). Una sesion
 * puede tener varios miembros del staff (`asignaciones_sesion`: instructor/asistente/
 * sustituto, con `sustituye_a`); cada staff tiene un `esquema_pago` (por clase / por
 * asistente / por hora) con el que se calcula la nomina de un periodo. Sin `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('asignaciones_sesion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $tabla->string('rol'); // instructor | asistente | sustituto
            $tabla->unsignedBigInteger('sustituye_a')->nullable(); // usuario sustituido
            $tabla->timestamps();

            $tabla->unique(['sesion_id', 'usuario_id']);
            $tabla->index('usuario_id');
        });

        Schema::connection('tenant')->create('esquemas_pago', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $tabla->string('tipo'); // por_clase | por_asistente | por_hora
            $tabla->bigInteger('monto_minor');
            $tabla->char('moneda', 3)->default('MXN');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('esquemas_pago');
        Schema::connection('tenant')->dropIfExists('asignaciones_sesion');
    }
};
