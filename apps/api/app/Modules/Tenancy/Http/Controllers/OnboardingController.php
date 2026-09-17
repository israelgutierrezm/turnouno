<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\PerfilNegocio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Wizard de onboarding del estudio (guardar y continuar) y publicación en el
 * directorio. El progreso vive en el control plane; los datos de cada paso se
 * materializan en la BD del tenant a medida que esos módulos migran. Opera sobre
 * el estudio ya resuelto (autenticado tenant-local).
 */
class OnboardingController
{
    /**
     * @var list<string>
     */
    private const PASOS = ['marca', 'sucursal', 'horarios', 'actividades', 'productos', 'politicas', 'pasarela', 'personal', 'publicacion'];

    public function show(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        return response()->json(['data' => [
            'pasos' => self::PASOS,
            'completados' => array_keys($estudio->onboarding_pasos ?? []),
            'completo' => $estudio->onboarding_completo,
        ]]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        $validado = $request->validate([
            'paso' => ['required', Rule::in(self::PASOS)],
            'datos' => ['nullable', 'array'],
        ]);

        $completados = $estudio->onboarding_pasos ?? [];
        $completados[(string) $validado['paso']] = $validado['datos'] ?? true;

        $completo = count(array_intersect(self::PASOS, array_keys($completados))) === count(self::PASOS);
        $estudio->update(['onboarding_pasos' => $completados, 'onboarding_completo' => $completo]);

        return response()->json(['data' => [
            'completados' => array_keys($completados),
            'completo' => $completo,
        ]]);
    }

    public function publicacion(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        $validado = $request->validate([
            'publicado' => ['required', 'boolean'],
            'privado' => ['nullable', 'boolean'],
        ]);

        $estudio->update([
            'publicado' => (bool) $validado['publicado'],
            'privado' => (bool) ($validado['privado'] ?? false),
        ]);

        return response()->json(['data' => [
            'publicado' => $estudio->publicado,
            'privado' => $estudio->privado,
            'en_directorio' => $estudio->enDirectorio(),
        ]]);
    }

    /**
     * Cambia el perfil de negocio del estudio (R35): solo ajusta
     * defaults/terminologia/feature-flags, sin forks.
     */
    public function perfil(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        $validado = $request->validate([
            'perfil_negocio' => ['required', Rule::enum(PerfilNegocio::class)],
        ]);

        $estudio->update(['perfil_negocio' => $validado['perfil_negocio']]);

        return response()->json(['data' => [
            'perfil' => $estudio->perfil_negocio->value,
            'perfil_config' => $estudio->perfil_negocio->configuracion(),
        ]]);
    }

    private function estudio(Request $request): Estudio
    {
        $estudio = $request->attributes->get('estudio');
        abort_unless($estudio instanceof Estudio, 404);

        return $estudio;
    }
}
