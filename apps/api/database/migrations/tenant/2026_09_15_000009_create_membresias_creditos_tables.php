<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): membresias y creditos. Producto comercial → acuerdo
 * → derecho (entitlement) + ledger de creditos (movimientos) y retenciones (holds).
 * El saldo NUNCA se guarda: se deriva de `movimientos_credito`. Dinero en
 * `precio_minor` (BIGINT) + moneda; creditos en enteros escalados (1000 = 1). Sin
 * `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('productos_comerciales', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('tipo');
            $tabla->bigInteger('precio_minor')->default(0);
            $tabla->char('moneda', 3)->default('MXN');
            $tabla->boolean('ilimitado')->default(false);
            $tabla->unsignedBigInteger('creditos_incluidos')->nullable(); // unidades escaladas
            $tabla->foreignId('actividad_id')->nullable()->constrained('actividades')->nullOnDelete();
            $tabla->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $tabla->string('politica_reset')->default('ninguno');
            $tabla->unsignedBigInteger('unidades_por_ciclo')->nullable();
            $tabla->string('politica_rollover')->default('ninguno');
            $tabla->unsignedBigInteger('rollover_max')->nullable();
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('acuerdos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->foreignId('producto_comercial_id')->constrained('productos_comerciales')->cascadeOnDelete();
            $tabla->date('fecha_inicio');
            $tabla->string('estado')->default('activo');
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('derechos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('acuerdo_id')->constrained('acuerdos')->cascadeOnDelete();
            $tabla->string('ambito')->default('general');
            $tabla->foreignId('actividad_id')->nullable()->constrained('actividades')->nullOnDelete();
            $tabla->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $tabla->boolean('ilimitado')->default(false);
            $tabla->string('politica_reset')->default('ninguno');
            $tabla->unsignedBigInteger('unidades_por_ciclo')->nullable();
            $tabla->string('politica_rollover')->default('ninguno');
            $tabla->unsignedBigInteger('rollover_max')->nullable();
            $tabla->date('ciclo_inicio')->nullable();
            $tabla->date('ciclo_fin')->nullable();
            $tabla->date('valido_desde')->nullable();
            $tabla->date('valido_hasta')->nullable();
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('movimientos_credito', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('derecho_id')->constrained('derechos')->cascadeOnDelete();
            $tabla->string('tipo');
            $tabla->bigInteger('unidades'); // + concesion / - consumo
            $tabla->string('descripcion')->nullable();
            $tabla->timestamps();

            $tabla->index('derecho_id');
        });

        Schema::connection('tenant')->create('retenciones_credito', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('derecho_id')->constrained('derechos')->cascadeOnDelete();
            $tabla->unsignedBigInteger('unidades');
            $tabla->string('estado')->default('activa');
            $tabla->string('descripcion')->nullable();
            $tabla->timestamps();

            $tabla->index('derecho_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('retenciones_credito');
        Schema::connection('tenant')->dropIfExists('movimientos_credito');
        Schema::connection('tenant')->dropIfExists('derechos');
        Schema::connection('tenant')->dropIfExists('acuerdos');
        Schema::connection('tenant')->dropIfExists('productos_comerciales');
    }
};
