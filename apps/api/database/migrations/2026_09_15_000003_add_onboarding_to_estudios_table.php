<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: progreso del wizard de onboarding del estudio (guardar y
 * continuar). JSON con los pasos completados; los datos de cada paso (sucursal,
 * horarios, actividades, productos, políticas) se materializan en la BD del tenant
 * a medida que esos módulos migran al data plane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->json('onboarding_pasos')->nullable()->after('aprovisionado_en');
            $tabla->boolean('onboarding_completo')->default(false)->after('onboarding_pasos');
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->dropColumn(['onboarding_pasos', 'onboarding_completo']);
        });
    }
};
