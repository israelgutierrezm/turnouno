<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Línea de una orden: qué producto, cuántas veces y para quién (beneficiario, que
// puede diferir del comprador — comprador != participante). El precio se
// congela al momento de la orden (`precio_unitario_minor`).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas_orden', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->foreignId('producto_comercial_id')->constrained('productos_comerciales')->cascadeOnDelete();
            $table->foreignId('beneficiario_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
            $table->unsignedBigInteger('precio_unitario_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->timestamps();

            $table->index('orden_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_orden');
    }
};
