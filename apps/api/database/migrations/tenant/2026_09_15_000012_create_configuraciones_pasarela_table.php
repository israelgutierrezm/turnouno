<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): configuracion de pasarelas de pago del estudio. Cada
 * estudio conecta sus propias llaves (Stripe/OpenPay/Mercado Pago) para cobrar a sus
 * alumnos. Las credenciales se guardan CIFRADAS (`encrypted:array`) y NUNCA se
 * exponen por la API (solo se indica que llaves estan configuradas). Sin tenant_id:
 * aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('configuraciones_pasarela', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('proveedor')->unique(); // stripe | openpay | mercadopago | ventanilla
            $tabla->boolean('activa')->default(false);
            $tabla->string('modo')->default('test'); // test | live
            $tabla->text('credenciales')->nullable(); // cifrado (encrypted:array)
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('configuraciones_pasarela');
    }
};
