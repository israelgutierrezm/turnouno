<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Comprobante de depósito en ventanilla: ruta del archivo subido y cuándo. El
// staff lo revisa y aprueba/rechaza el pago.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->string('comprobante_ruta')->nullable()->after('referencia_externa');
            $table->dateTime('comprobante_subido_en')->nullable()->after('comprobante_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropColumn(['comprobante_ruta', 'comprobante_subido_en']);
        });
    }
};
