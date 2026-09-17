<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): ACCESOS (R12) — bitacora de entradas evaluadas por la
 * politica de acceso (reserva vigente u OPEN_ACCESS por membresia). Cada intento
 * (permitido/denegado) queda registrado con su metodo (QR/PIN/NFC) y el codigo de
 * razon. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('accesos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $tabla->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $tabla->foreignId('sesion_id')->nullable()->constrained('sesiones')->nullOnDelete();
            $tabla->string('metodo');       // qr | pin | nfc | manual
            $tabla->string('resultado');    // permitido | denegado
            $tabla->string('codigo');       // ACCESS_BY_BOOKING | ACCESS_OPEN | NO_ACCESS
            $tabla->dateTime('registrado_en');
            $tabla->timestamps();

            $tabla->index('persona_id');
            $tabla->index(['registrado_en', 'id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('accesos');
    }
};
