<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pago (intento de cobro) de una orden a través de una pasarela. `proveedor`
// identifica la pasarela (manual, simulada, …). `referencia_externa` guarda el id
// del proveedor; `idempotency_key` evita cobros duplicados ante reintentos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->string('proveedor');
            $table->string('estado')->default('pendiente');
            $table->unsignedBigInteger('monto_minor');
            $table->char('moneda', 3);
            $table->string('referencia_externa')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['orden_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
