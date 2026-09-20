<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: pasarelas de pago DE LA PLATAFORMA (para cobrar la renta del SaaS a los
 * dueños). Espejo de `configuraciones_pasarela` (que es por tenant, para que el estudio
 * cobre a sus alumnos): aquí hay una sola configuración por proveedor a nivel plataforma.
 * Las credenciales se guardan cifradas y NUNCA se exponen por la API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_pasarela_plataforma', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('proveedor')->unique();
            $tabla->boolean('activa')->default(false);
            $tabla->string('modo')->default('test'); // test | live
            $tabla->text('credenciales')->nullable(); // cifrado (encrypted:array)
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_pasarela_plataforma');
    }
};
