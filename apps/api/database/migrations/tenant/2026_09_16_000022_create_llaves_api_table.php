<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): llaves de API tenant-local (R40) para integraciones
 * de terceros. Se guarda solo el hash del secreto (nunca el secreto en claro) y un
 * prefijo para identificarla. Los `scopes` acotan qué puede leer la llave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('llaves_api', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('prefijo', 16);        // parte visible para identificarla
            $tabla->string('hash', 64)->unique();  // sha256 del secreto en claro
            $tabla->json('scopes');
            $tabla->boolean('activa')->default(true);
            $tabla->timestamp('ultimo_uso_en')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('llaves_api');
    }
};
