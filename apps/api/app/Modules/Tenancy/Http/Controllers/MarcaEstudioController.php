<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Marca (branding) del estudio: nombre y logo. `mostrar` es PUBLICO (sin auth) para
 * que la pantalla de acceso muestre el logo del estudio antes de iniciar sesion; si
 * el estudio no tiene logo, el front cae al logo de la aplicacion. La carga del logo
 * exige `estudio.gestionar`. El logo vive en el disco publico namespaced por estudio.
 */
class MarcaEstudioController
{
    public function mostrar(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        return response()->json(['data' => [
            'slug' => $estudio->slug,
            'nombre' => $estudio->nombre,
            'logo_url' => $estudio->logo_url,
        ]]);
    }

    public function subirLogo(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        $request->validate([
            // SVG excluido a proposito (riesgo de XSS al servirse en el navegador).
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $archivo = $request->file('logo');
        $extension = $archivo->extension();

        // Un solo logo por estudio: limpia el anterior antes de guardar el nuevo.
        Storage::disk('public')->deleteDirectory('estudios/'.$estudio->getKey());

        $ruta = $archivo->storeAs(
            'estudios/'.$estudio->getKey(),
            'logo_'.Str::lower(Str::random(8)).'.'.$extension,
            'public',
        );

        $estudio->update(['logo_url' => Storage::disk('public')->url($ruta)]);

        return response()->json(['data' => ['logo_url' => $estudio->logo_url]]);
    }

    public function eliminarLogo(Request $request): JsonResponse
    {
        $estudio = $this->estudio($request);

        Storage::disk('public')->deleteDirectory('estudios/'.$estudio->getKey());
        $estudio->update(['logo_url' => null]);

        return response()->json(['data' => ['logo_url' => null]]);
    }

    private function estudio(Request $request): Estudio
    {
        $estudio = $request->attributes->get('estudio');
        abort_unless($estudio instanceof Estudio, 404);

        return $estudio;
    }
}
