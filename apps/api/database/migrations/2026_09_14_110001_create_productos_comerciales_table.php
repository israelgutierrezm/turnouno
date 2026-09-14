<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Producto comercial vendible (membresía, paquete, pase, add-on, ...).
// Dinero como amount_minor + moneda; créditos como enteros escalados (nunca float).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_comerciales', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo');
            $table->bigInteger('precio_minor')->default(0);
            $table->char('moneda', 3)->default('MXN');
            $table->boolean('ilimitado')->default(false);
            // Créditos otorgados, en unidades escaladas (1 crédito = 1000 unidades).
            $table->unsignedBigInteger('creditos_incluidos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_comerciales');
    }
};
