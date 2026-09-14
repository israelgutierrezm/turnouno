<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Traza el origen comercial de un acuerdo: si vino de una orden, qué línea lo
// concedió. Permite revertir el fulfillment al reembolsar (Slice 9b). Nulo en
// ventas directas (sin orden).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acuerdos', function (Blueprint $table): void {
            $table->foreignId('linea_orden_id')->nullable()->after('producto_comercial_id')
                ->constrained('lineas_orden')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acuerdos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('linea_orden_id');
        });
    }
};
