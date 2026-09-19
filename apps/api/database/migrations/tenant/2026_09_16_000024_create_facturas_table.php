<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): facturas (CFDI) timbradas vía FacturAPI. Guarda el
 * snapshot del receptor y los montos (en minor, entero — nunca float), el estado del
 * timbre y las referencias del CFDI (UUID, PDF/XML). El detalle de conceptos se
 * envía a FacturAPI al timbrar; aquí se conserva el resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('facturas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('orden_id')->nullable()->index();

            $tabla->string('receptor_nombre');
            $tabla->string('receptor_rfc', 13);
            $tabla->string('receptor_email')->nullable();
            $tabla->string('uso_cfdi', 4);
            $tabla->string('receptor_cp', 5);

            $tabla->char('moneda', 3)->default('MXN');
            $tabla->unsignedBigInteger('subtotal_minor');
            $tabla->unsignedBigInteger('impuesto_minor');
            $tabla->unsignedBigInteger('total_minor');

            $tabla->string('estado');
            $tabla->string('facturapi_id')->nullable();
            $tabla->string('uuid')->nullable();
            $tabla->string('pdf_url')->nullable();
            $tabla->string('xml_url')->nullable();
            $tabla->string('motivo_error')->nullable();
            $tabla->timestamp('timbrada_en')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('facturas');
    }
};
