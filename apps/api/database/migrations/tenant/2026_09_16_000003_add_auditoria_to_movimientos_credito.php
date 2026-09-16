<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de créditos AUDITABLE (R2): cada asiento pasa a registrar de quién es
 * (`persona_id`), de dónde nace (`origen`), el saldo resultante (`saldo_posterior`,
 * instantánea para conciliar; el saldo verdadero SIGUE derivándose del SUM), a qué
 * entidad se refiere (`referencia_tipo`/`referencia_id`: reserva, acuerdo…), quién lo
 * provocó (`actor_id`/`actor_nombre`) y datos extra (`metadata`). Sin FK en el ALTER:
 * SQLite no las agrega por ALTER TABLE y el aislamiento del tenant ya es por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('movimientos_credito', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('persona_id')->nullable()->after('derecho_id');
            $tabla->string('origen')->nullable()->after('tipo');
            $tabla->bigInteger('saldo_posterior')->nullable()->after('unidades');
            $tabla->string('referencia_tipo')->nullable()->after('descripcion');
            $tabla->string('referencia_id')->nullable()->after('referencia_tipo');
            $tabla->unsignedBigInteger('actor_id')->nullable()->after('referencia_id');
            $tabla->string('actor_nombre')->nullable()->after('actor_id');
            $tabla->json('metadata')->nullable()->after('actor_nombre');

            $tabla->index('persona_id');
            $tabla->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('movimientos_credito', function (Blueprint $tabla): void {
            $tabla->dropIndex(['persona_id']);
            $tabla->dropIndex(['referencia_tipo', 'referencia_id']);
            $tabla->dropColumn([
                'persona_id', 'origen', 'saldo_posterior', 'referencia_tipo',
                'referencia_id', 'actor_id', 'actor_nombre', 'metadata',
            ]);
        });
    }
};
