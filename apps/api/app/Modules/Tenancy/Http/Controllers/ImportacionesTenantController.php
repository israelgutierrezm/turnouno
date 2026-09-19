<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\ImportarMiembrosTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Importación masiva por CSV (R37), tenant-local. Ofrece PREVIEW (valida sin
 * escribir, con errores por fila) e IMPORT (todo-o-nada con rollback). Primer
 * recurso: miembros. Opera sobre la BD del estudio resuelto.
 */
class ImportacionesTenantController
{
    public function __construct(private readonly ImportarMiembrosTenant $importador) {}

    public function previewMiembros(Request $request): JsonResponse
    {
        $filas = $this->parsear($request);

        return response()->json(['data' => $this->importador->analizar($filas)]);
    }

    public function importarMiembros(Request $request): JsonResponse
    {
        $filas = $this->parsear($request);

        $resultado = $this->importador->importar($filas);

        // Todo-o-nada: si hubo filas inválidas no se escribió nada (rollback) -> 422.
        return response()->json(['data' => $resultado], $resultado['ok'] ? 201 : 422);
    }

    /**
     * Lee el CSV subido en `archivo` a filas asociativas por su encabezado.
     *
     * @return list<array<string, string>>
     */
    private function parsear(Request $request): array
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:2048'],
        ]);

        /** @var UploadedFile $archivo */
        $archivo = $request->file('archivo');

        $manejador = fopen($archivo->getRealPath(), 'r');
        if ($manejador === false) {
            throw ValidationException::withMessages(['archivo' => ['No se pudo leer el archivo.']]);
        }

        try {
            $encabezado = fgetcsv($manejador);
            if ($encabezado === false) {
                throw ValidationException::withMessages(['archivo' => ['El archivo está vacío.']]);
            }

            $columnas = array_map($this->normalizarEncabezado(...), $encabezado);
            if (! in_array('nombre', $columnas, true)) {
                throw ValidationException::withMessages(['archivo' => ['Falta la columna obligatoria "nombre".']]);
            }

            $filas = [];
            while (($cruda = fgetcsv($manejador)) !== false) {
                // Omite filas totalmente vacías.
                if (count(array_filter($cruda, static fn ($v): bool => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                if (count($filas) >= ImportarMiembrosTenant::MAX_FILAS) {
                    throw ValidationException::withMessages([
                        'archivo' => ['El archivo excede el máximo de '.ImportarMiembrosTenant::MAX_FILAS.' filas.'],
                    ]);
                }

                $filas[] = $this->combinar($columnas, $cruda);
            }

            return $filas;
        } finally {
            fclose($manejador);
        }
    }

    private function normalizarEncabezado(?string $valor): string
    {
        // Minúsculas, sin espacios ni BOM inicial.
        $limpio = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $valor) ?? '');

        return str_replace(' ', '_', mb_strtolower($limpio));
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<string|null>  $valores
     * @return array<string, string>
     */
    private function combinar(array $columnas, array $valores): array
    {
        $fila = [];
        foreach ($columnas as $i => $columna) {
            if ($columna === '') {
                continue;
            }
            $fila[$columna] = (string) ($valores[$i] ?? '');
        }

        return $fila;
    }
}
