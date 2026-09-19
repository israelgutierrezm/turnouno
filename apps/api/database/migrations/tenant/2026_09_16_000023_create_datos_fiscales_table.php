<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): datos fiscales del EMISOR (el estudio) para CFDI vía
 * FacturAPI. Cada tenant carga los suyos aunque la plataforma use una sola cuenta
 * FacturAPI (multi-organización): estos datos materializan su "Organization" y
 * `facturapi_organizacion_id`/`facturapi_llave` guardan el vínculo (la llave, cifrada).
 * Una sola fila por BD de tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('datos_fiscales', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('razon_social');
            $tabla->string('rfc', 13);
            $tabla->string('regimen_fiscal', 4);   // clave SAT (p. ej. 601, 626)
            $tabla->string('codigo_postal', 5);     // domicilio fiscal (lugar de expedición)
            $tabla->string('facturapi_organizacion_id')->nullable();
            $tabla->text('facturapi_llave')->nullable(); // cifrada en la app
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datos_fiscales');
    }
};
