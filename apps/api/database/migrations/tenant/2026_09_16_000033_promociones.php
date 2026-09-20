<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): promociones / cupones de descuento (R22). Un código
 * aplica un descuento (porcentaje en bps o monto fijo en minor) al total de una orden,
 * con tope de usos, mínimo de compra y vigencia opcionales. La orden guarda el
 * descuento aplicado y la promoción usada (snapshot del efecto).
 *
 * - `promociones.valor`: bps si tipo=porcentaje (1500 = 15%), minor si monto_fijo.
 * - `promociones.usos` / `usos_maximos`: contador y tope (null = ilimitado).
 * - `ordenes.descuento_minor`: descuento aplicado (>=0); total = subtotal - descuento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('promociones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('codigo');
            $tabla->string('descripcion')->nullable();
            $tabla->string('tipo');
            $tabla->unsignedBigInteger('valor');
            $tabla->unsignedBigInteger('monto_minimo_minor')->nullable();
            $tabla->unsignedInteger('usos_maximos')->nullable();
            $tabla->unsignedInteger('usos')->default(0);
            $tabla->date('vence_en')->nullable();
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();

            $tabla->unique('codigo');
        });

        Schema::connection('tenant')->table('ordenes', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('descuento_minor')->default(0)->after('total_minor');
            $tabla->unsignedBigInteger('promocion_id')->nullable()->after('descuento_minor');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ordenes', function (Blueprint $tabla): void {
            $tabla->dropColumn(['descuento_minor', 'promocion_id']);
        });

        Schema::connection('tenant')->dropIfExists('promociones');
    }
};
