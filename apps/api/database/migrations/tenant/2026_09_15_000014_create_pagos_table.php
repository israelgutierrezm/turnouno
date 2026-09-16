<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): pagos de una orden. El cobro en linea es ASINCRONO:
 * se crea un pago `pendiente` con `referencia_externa` (el intent de la pasarela) y
 * el webhook lo confirma -> fulfillment. El cobro manual/ventanilla se aprueba en el
 * momento. `idempotency_key` unico (aislado por estudio al ser BD por tenant). Sin
 * `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('pagos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $tabla->string('proveedor');            // manual | ventanilla | stripe | openpay | mercadopago
            $tabla->string('metodo')->nullable();   // tarjeta | oxxo | spei | efectivo | ventanilla
            $tabla->string('estado')->default('pendiente'); // pendiente | aprobado | rechazado | reembolsado
            $tabla->unsignedBigInteger('monto_minor');
            $tabla->char('moneda', 3);
            $tabla->string('referencia_externa')->nullable();
            $tabla->string('idempotency_key')->nullable()->unique();
            $tabla->timestamps();

            $tabla->index(['orden_id', 'estado']);
            $tabla->index('referencia_externa');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pagos');
    }
};
