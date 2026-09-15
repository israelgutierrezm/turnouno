<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): ordenes y sus lineas. Una orden congela el precio de
 * cada producto (snapshot) y, al liquidarse, hace el fulfillment: concede un
 * derecho por cada unidad de cada linea al beneficiario (o comprador). El cobro con
 * pasarela real es un modulo posterior (cuando haya llaves); aqui la liquidacion es
 * manual/ventanilla y se registra en la propia orden. Sin `tenant_id`: aislamiento
 * por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('ordenes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->string('estado')->default('pendiente'); // pendiente | pagada | cancelada
            $tabla->unsignedBigInteger('total_minor');
            $tabla->char('moneda', 3);
            $tabla->string('metodo_pago')->nullable();      // efectivo | transferencia | ventanilla | manual
            $tabla->string('referencia_pago')->nullable();
            $tabla->timestamp('pagada_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['persona_id', 'estado']);
        });

        Schema::connection('tenant')->create('lineas_orden', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $tabla->foreignId('producto_comercial_id')->constrained('productos_comerciales')->cascadeOnDelete();
            $tabla->foreignId('beneficiario_id')->nullable()->constrained('personas')->nullOnDelete();
            $tabla->unsignedInteger('cantidad')->default(1);
            $tabla->unsignedBigInteger('precio_unitario_minor');
            $tabla->unsignedBigInteger('subtotal_minor');
            $tabla->timestamps();

            $tabla->index('orden_id');
        });

        // Liga cada acuerdo a su linea de orden (para poder revertirlo en un futuro
        // reembolso). Nulo cuando el acuerdo se creo por venta directa (sin orden).
        Schema::connection('tenant')->table('acuerdos', function (Blueprint $tabla): void {
            $tabla->foreignId('linea_orden_id')->nullable()->after('producto_comercial_id')
                ->constrained('lineas_orden')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('acuerdos', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('linea_orden_id');
        });
        Schema::connection('tenant')->dropIfExists('lineas_orden');
        Schema::connection('tenant')->dropIfExists('ordenes');
    }
};
