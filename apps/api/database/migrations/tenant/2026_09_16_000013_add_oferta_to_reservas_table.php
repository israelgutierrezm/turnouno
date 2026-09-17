<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waitlist robusta (R7): cuando se ofrece un cupo al siguiente de la lista de espera,
 * la reserva queda `ofrecida` con `oferta_expira_en` (ventana para aceptar). Si no
 * acepta a tiempo, un relay la marca `expirada` y re-ofrece el cupo. Sin `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->dateTime('oferta_expira_en')->nullable()->after('penaliza_no_show');
            $tabla->index(['estado', 'oferta_expira_en']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['estado', 'oferta_expira_en']);
            $tabla->dropColumn('oferta_expira_en');
        });
    }
};
