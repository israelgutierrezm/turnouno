<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): inventario + punto de venta minorista (R21), separado de
 * las membresías/créditos. El stock por (artículo, sucursal) es la SUMA de los deltas de
 * `movimientos_inventario` (derivado y auditable, como el ledger de créditos). Una venta
 * POS registra un ticket con sus líneas y descuenta stock (movimientos de salida).
 *
 * - `articulos`: catálogo minorista (agua, ropa, suplementos…). Dinero `*_minor` + moneda.
 * - `movimientos_inventario.cantidad`: delta con signo (entrada +, salida -, ajuste ±).
 * - `ventas_pos`: ticket de caja (metodo de pago manual; sin pasarela).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('articulos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('sku')->nullable();
            $tabla->unsignedBigInteger('precio_minor');
            $tabla->char('moneda', 3)->default('MXN');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('movimientos_inventario', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('articulo_id');
            $tabla->unsignedBigInteger('sucursal_id');
            $tabla->string('tipo');
            $tabla->integer('cantidad'); // delta con signo
            $tabla->string('motivo')->nullable();
            $tabla->unsignedBigInteger('venta_pos_id')->nullable();
            $tabla->unsignedBigInteger('usuario_id')->nullable();
            $tabla->timestamps();

            $tabla->index(['articulo_id', 'sucursal_id']);
        });

        Schema::connection('tenant')->create('ventas_pos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('sucursal_id');
            $tabla->unsignedBigInteger('total_minor');
            $tabla->char('moneda', 3)->default('MXN');
            $tabla->string('metodo_pago')->default('efectivo');
            $tabla->unsignedBigInteger('usuario_id')->nullable();
            $tabla->timestamps();

            $tabla->index('sucursal_id');
        });

        Schema::connection('tenant')->create('lineas_venta_pos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('venta_pos_id');
            $tabla->unsignedBigInteger('articulo_id');
            $tabla->unsignedInteger('cantidad');
            $tabla->unsignedBigInteger('precio_unitario_minor');
            $tabla->unsignedBigInteger('subtotal_minor');
            $tabla->timestamps();

            $tabla->index('venta_pos_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('lineas_venta_pos');
        Schema::connection('tenant')->dropIfExists('ventas_pos');
        Schema::connection('tenant')->dropIfExists('movimientos_inventario');
        Schema::connection('tenant')->dropIfExists('articulos');
    }
};
