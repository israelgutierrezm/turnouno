<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Crm\EtapaProspecto;
use App\Modules\Crm\TipoActividadProspecto;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProspectoActividadTenant;
use App\Modules\Tenancy\Models\ProspectoTenant;
use App\Modules\Tenancy\Models\Usuario;
use App\Modules\Tenancy\TipoPersonaTenant;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta el embudo comercial tenant-local (R15): alta de prospectos, avance de
 * etapa con bitácora, registro de interacciones y conversión a miembro. Toda
 * escritura ocurre en la BD del estudio resuelto y deja rastro en `prospecto_actividades`.
 */
class GestionarCrmTenant
{
    public function __construct(private readonly GestionarReferidosTenant $referidos) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?Usuario $actor = null, ?string $codigoReferido = null): ProspectoTenant
    {
        return DB::connection('tenant')->transaction(function () use ($datos, $actor, $codigoReferido): ProspectoTenant {
            // Etapa inicial explícita: un prospecto siempre nace en el embudo (Nuevo).
            $prospecto = ProspectoTenant::query()->create(array_merge(
                ['etapa' => EtapaProspecto::Nuevo->value],
                $datos,
            ));
            $this->registrar($prospecto, TipoActividadProspecto::CambioEtapa, 'Prospecto creado', $actor);

            // Atribución de referido (R23): si viene un código válido, lo enlaza.
            if ($codigoReferido !== null && $codigoReferido !== '') {
                $this->referidos->registrarPorCodigo($codigoReferido, $prospecto);
            }

            return $prospecto;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(ProspectoTenant $prospecto, array $datos): ProspectoTenant
    {
        $prospecto->update($datos);

        return $prospecto->refresh();
    }

    public function cambiarEtapa(ProspectoTenant $prospecto, EtapaProspecto $etapa, ?string $motivo = null, ?Usuario $actor = null): ProspectoTenant
    {
        return DB::connection('tenant')->transaction(function () use ($prospecto, $etapa, $motivo, $actor): ProspectoTenant {
            $prospecto->update([
                'etapa' => $etapa,
                'motivo' => $etapa === EtapaProspecto::Perdido ? $motivo : null,
            ]);

            $detalle = 'Etapa: '.$etapa->value.($motivo !== null && $motivo !== '' ? " ({$motivo})" : '');
            $this->registrar($prospecto, TipoActividadProspecto::CambioEtapa, $detalle, $actor);

            return $prospecto->refresh();
        });
    }

    public function registrarActividad(ProspectoTenant $prospecto, TipoActividadProspecto $tipo, ?string $detalle, ?Usuario $actor = null): ProspectoActividadTenant
    {
        return $this->registrar($prospecto, $tipo, $detalle, $actor);
    }

    /**
     * Convierte un prospecto ganado en miembro: crea la persona (o reusa la ya
     * enlazada), marca la etapa Ganado y registra la conversión. Idempotente: si ya
     * tiene persona enlazada, la devuelve sin duplicar.
     */
    public function convertir(ProspectoTenant $prospecto, ?Usuario $actor = null): PersonaTenant
    {
        return DB::connection('tenant')->transaction(function () use ($prospecto, $actor): PersonaTenant {
            $bloqueado = ProspectoTenant::query()->whereKey($prospecto->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueado->persona_id !== null) {
                $existente = PersonaTenant::query()->find($bloqueado->persona_id);
                if ($existente !== null) {
                    return $existente;
                }
            }

            [$nombre, $apellido] = $this->partirNombre((string) $bloqueado->nombre);

            $persona = PersonaTenant::query()->create([
                'nombre' => $nombre,
                'primer_apellido' => $apellido,
                'email' => $bloqueado->email,
                'tipo' => TipoPersonaTenant::Miembro->value,
                'activo' => true,
                'es_facturable' => true,
                'archivado' => false,
                'sucursal_id' => $bloqueado->sucursal_id,
            ]);

            $bloqueado->update([
                'persona_id' => $persona->getKey(),
                'etapa' => EtapaProspecto::Ganado,
                'motivo' => null,
                'convertido_en' => now(),
            ]);

            $this->registrar($bloqueado, TipoActividadProspecto::Conversion, 'Convertido a miembro', $actor);

            // Referido (R23): si este prospecto vino de un referido, se genera el cupón
            // de recompensa para quien lo refirió.
            $this->referidos->alConvertir($bloqueado, $persona);

            return $persona;
        });
    }

    private function registrar(ProspectoTenant $prospecto, TipoActividadProspecto $tipo, ?string $detalle, ?Usuario $actor): ProspectoActividadTenant
    {
        return ProspectoActividadTenant::query()->create([
            'prospecto_id' => $prospecto->getKey(),
            'usuario_id' => $actor?->getKey(),
            'tipo' => $tipo->value,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Parte un nombre libre en nombre + primer apellido (mejor esfuerzo): la primera
     * palabra es el nombre y el resto el apellido.
     *
     * @return array{0: string, 1: string|null}
     */
    private function partirNombre(string $completo): array
    {
        $partes = preg_split('/\s+/', trim($completo), 2) ?: [];
        $nombre = $partes[0] ?? $completo;
        $apellido = $partes[1] ?? null;

        return [$nombre !== '' ? $nombre : $completo, $apellido];
    }
}
