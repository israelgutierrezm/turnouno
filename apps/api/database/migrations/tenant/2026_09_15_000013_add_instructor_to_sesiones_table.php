<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): asigna un instructor (usuario tenant-local) a una
 * sesion. Permite acotar lo que un instructor ve/gestiona a SUS clases (privacidad:
 * un instructor no debe ver el roster de todas las clases del estudio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sesiones', function (Blueprint $tabla): void {
            $tabla->foreignId('instructor_id')->nullable()->after('sucursal_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sesiones', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('instructor_id');
        });
    }
};
