<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Ordenes\TipoPromocion;
use App\Modules\Referidos\EstadoReferido;
use App\Modules\Tenancy\Models\CodigoReferidoTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProgramaReferidosTenant;
use App\Modules\Tenancy\Models\PromocionTenant;
use App\Modules\Tenancy\Models\ProspectoTenant;
use App\Modules\Tenancy\Models\ReferidoTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Programa de referidos tenant-local (R23): código por miembro, atribución de un
 * prospecto a quien lo refirió y, al convertirse el referido en miembro, generación del
 * cupón de recompensa (reusa Promociones/R22). Opera sobre la BD del estudio resuelto.
 */
class GestionarReferidosTenant
{
    public function programa(): ProgramaReferidosTenant
    {
        return ProgramaReferidosTenant::query()->firstOrCreate([], [
            'recompensa_tipo' => TipoPromocion::MontoFijo->value,
            'recompensa_valor' => 10000,
            'vigencia_dias' => 90,
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardarPrograma(array $datos): ProgramaReferidosTenant
    {
        $programa = $this->programa();
        $programa->update($datos);

        return $programa->refresh();
    }

    /**
     * Código de referido del miembro (lo crea la primera vez).
     */
    public function codigoDe(PersonaTenant $persona): CodigoReferidoTenant
    {
        $existente = CodigoReferidoTenant::query()->where('persona_id', $persona->getKey())->first();
        if ($existente !== null) {
            return $existente;
        }

        return CodigoReferidoTenant::query()->create([
            'persona_id' => $persona->getKey(),
            'codigo' => $this->codigoUnico(fn (string $c): bool => CodigoReferidoTenant::query()->where('codigo', $c)->exists()),
        ]);
    }

    /**
     * Atribuye un prospecto a quien lo refirió (código). Ignora códigos inexistentes.
     */
    public function registrarPorCodigo(string $codigo, ProspectoTenant $prospecto): ?ReferidoTenant
    {
        $codigo = mb_strtoupper(trim($codigo));
        $ref = CodigoReferidoTenant::query()->where('codigo', $codigo)->first();
        if ($ref === null) {
            return null;
        }

        return ReferidoTenant::query()->create([
            'referidor_id' => $ref->persona_id,
            'codigo' => $codigo,
            'prospecto_id' => $prospecto->getKey(),
            'estado' => EstadoReferido::Pendiente->value,
        ]);
    }

    /**
     * Al convertir un prospecto en miembro: si hay un referido pendiente para ese
     * prospecto y el programa está activo, lo marca convertido y genera el cupón de
     * recompensa para quien refirió.
     */
    public function alConvertir(ProspectoTenant $prospecto, PersonaTenant $personaReferida): ?ReferidoTenant
    {
        $referido = ReferidoTenant::query()
            ->where('prospecto_id', $prospecto->getKey())
            ->where('estado', EstadoReferido::Pendiente->value)
            ->first();

        if ($referido === null) {
            return null;
        }

        $referido->persona_referida_id = $personaReferida->getKey();
        $referido->estado = EstadoReferido::Convertido;
        $referido->convertido_en = Carbon::now();

        $programa = $this->programa();
        if ($programa->activo) {
            $cupon = $this->generarCupon($programa);
            $referido->recompensa_promocion_id = $cupon->getKey();
        }

        $referido->save();

        return $referido->refresh();
    }

    private function generarCupon(ProgramaReferidosTenant $programa): PromocionTenant
    {
        $codigo = $this->codigoUnico(
            fn (string $c): bool => PromocionTenant::query()->where('codigo', $c)->exists(),
            'REF-',
        );

        return PromocionTenant::query()->create([
            'codigo' => $codigo,
            'descripcion' => 'Recompensa por referir',
            'tipo' => $programa->recompensa_tipo->value,
            'valor' => $programa->recompensa_valor,
            'usos_maximos' => 1,
            'usos' => 0,
            'vence_en' => Carbon::now()->addDays($programa->vigencia_dias)->toDateString(),
            'activa' => true,
        ]);
    }

    /**
     * @param  callable(string): bool  $existe
     */
    private function codigoUnico(callable $existe, string $prefijo = ''): string
    {
        do {
            $codigo = $prefijo.mb_strtoupper(Str::random(6));
        } while ($existe($codigo));

        return $codigo;
    }
}
