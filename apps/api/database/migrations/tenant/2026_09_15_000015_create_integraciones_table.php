<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): integraciones con plataformas de bienestar
 * corporativo (Wellhub / TotalPass). Cada estudio conecta SUS credenciales para
 * validar el check-in de los usuarios de esas plataformas. Las credenciales se
 * guardan CIFRADAS y NUNCA se exponen. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('integraciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('proveedor')->unique(); // wellhub | totalpass
            $tabla->boolean('activa')->default(false);
            $tabla->text('credenciales')->nullable(); // cifrado (encrypted:array)
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('integraciones');
    }
};
