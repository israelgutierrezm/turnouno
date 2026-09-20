<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R30 (Rentabilidad de clase): precio "drop-in" opcional por oferta. Si el estudio lo
 * configura, el ingreso por sesión se calcula como asistentes × precio_clase_minor;
 * si no, se aproxima por los créditos consumidos. En minor (nunca float).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('ofertas', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('precio_clase_minor')->nullable()->after('lugares');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ofertas', function (Blueprint $tabla): void {
            $tabla->dropColumn('precio_clase_minor');
        });
    }
};
