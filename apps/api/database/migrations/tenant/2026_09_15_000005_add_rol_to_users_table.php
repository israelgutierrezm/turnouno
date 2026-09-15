<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): rol del usuario tenant-local. Los roles son por
 * tenant (viven en la BD del estudio), así que la misma persona puede tener un rol
 * distinto en otro estudio. Se usa un RBAC propio (catálogo de permisos en código)
 * para el data plane; spatie permanece en el esquema compartido legacy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $tabla): void {
            $tabla->string('rol')->default('miembro')->after('activo');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $tabla): void {
            $tabla->dropColumn('rol');
        });
    }
};
