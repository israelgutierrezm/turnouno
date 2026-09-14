<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de una pasarela para un tenant. Las credenciales se guardan
 * cifradas (`encrypted:array`) y NUNCA se exponen en la API (solo se indican como
 * configuradas). Ver ADR-0014.
 */
class ConfiguracionPasarela extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'configuraciones_pasarela';

    protected $fillable = ['proveedor', 'activa', 'modo', 'credenciales'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'activa' => 'boolean',
        'credenciales' => 'encrypted:array',
    ];

    /**
     * Credenciales descifradas como mapa (vacío si no hay).
     *
     * @return array<string, string>
     */
    public function llaves(): array
    {
        $valor = $this->credenciales;

        return is_array($valor) ? $valor : [];
    }

    public function llave(string $nombre): ?string
    {
        $valor = $this->llaves()[$nombre] ?? null;

        return is_string($valor) ? $valor : null;
    }
}
