<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): devoluciones (refunds) de un pago (R11). Un pago puede
 * tener varias devoluciones parciales; la suma de las APROBADAS nunca supera su
 * monto. `revirtio_creditos` marca si la devolución además revocó el entitlement. La
 * devolución en línea puede quedar `pendiente` con `referencia_externa` de la pasarela
 * hasta reconciliarse. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('reembolsos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $tabla->unsignedBigInteger('monto_minor');
            $tabla->char('moneda', 3);
            $tabla->string('estado')->default('pendiente'); // pendiente | aprobado | fallido
            $tabla->string('proveedor');
            $tabla->string('motivo')->nullable();
            $tabla->boolean('revirtio_creditos')->default(false);
            $tabla->string('referencia_externa')->nullable(); // id de la devolución en la pasarela
            $tabla->unsignedBigInteger('actor_id')->nullable();
            $tabla->string('actor_nombre')->nullable();
            $tabla->json('metadata')->nullable();
            $tabla->timestamps();

            $tabla->index(['pago_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('reembolsos');
    }
};
