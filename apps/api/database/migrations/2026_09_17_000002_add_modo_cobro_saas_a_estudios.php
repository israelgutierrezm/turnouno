<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: modo de cobro de la suscripción SaaS por estudio. Por defecto se cobra
 * por alumno activo del periodo (`activos`); el admin de la plataforma puede cambiar a
 * cuota fija mensual (`fijo` + `cuota_fija_minor`). Dinero en minor + moneda (nunca float).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->string('modo_cobro')->default('activos')->after('precio_por_alumno_minor');
            $tabla->unsignedBigInteger('cuota_fija_minor')->default(0)->after('modo_cobro');
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->dropColumn(['modo_cobro', 'cuota_fija_minor']);
        });
    }
};
