<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): sello digital (CSD) del emisor para timbrar CFDI. El
 * certificado (.cer) y la llave privada (.key) se guardan en base64 y, junto con su
 * contraseña, CIFRADOS en la aplicación (nunca se serializan ni se devuelven).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('datos_fiscales', function (Blueprint $tabla): void {
            $tabla->text('sello_cer')->nullable();       // .cer en base64 (cifrado)
            $tabla->text('sello_key')->nullable();       // .key en base64 (cifrado)
            $tabla->text('sello_password')->nullable();  // contraseña de la llave (cifrada)
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datos_fiscales', function (Blueprint $tabla): void {
            $tabla->dropColumn(['sello_cer', 'sello_key', 'sello_password']);
        });
    }
};
