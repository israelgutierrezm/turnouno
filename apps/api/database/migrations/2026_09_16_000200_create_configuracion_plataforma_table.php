<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane (BD compartida): configuración global de la plataforma (clave-valor).
 * Guarda secretos de plataforma como la llave MAESTRA de la cuenta FacturAPI, que la
 * plataforma usa para timbrar por todos los estudios (multi-organización). El valor se
 * cifra en la aplicación y nunca se devuelve por la API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_plataforma', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('clave')->unique();
            $tabla->text('valor')->nullable(); // cifrado en la app
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_plataforma');
    }
};
