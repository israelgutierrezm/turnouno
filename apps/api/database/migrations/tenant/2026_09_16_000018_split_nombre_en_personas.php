<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): separa el nombre de la persona en partes (estilo
 * hispano): `nombre`, `segundo_nombre` (opcional), `primer_apellido`,
 * `segundo_apellido`. Migra el `apellidos` unico existente a `primer_apellido` y lo
 * elimina. El nombre completo se compone en la aplicacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->string('segundo_nombre')->nullable()->after('nombre');
            $tabla->string('primer_apellido')->nullable()->after('segundo_nombre');
            $tabla->string('segundo_apellido')->nullable()->after('primer_apellido');
        });

        // Conserva los apellidos existentes en `primer_apellido`.
        DB::connection('tenant')->table('personas')
            ->whereNotNull('apellidos')
            ->update(['primer_apellido' => DB::raw('apellidos')]);

        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->dropColumn('apellidos');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->string('apellidos')->nullable()->after('nombre');
        });

        DB::connection('tenant')->table('personas')
            ->whereNotNull('primer_apellido')
            ->update(['apellidos' => DB::raw('primer_apellido')]);

        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->dropColumn(['segundo_nombre', 'primer_apellido', 'segundo_apellido']);
        });
    }
};
