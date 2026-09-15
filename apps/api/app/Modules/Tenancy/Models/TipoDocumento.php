<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de documento requerido por el estudio (definido por el administrador), en
 * la BD del tenant. Ej.: "INE", "Comprobante médico", "Certificación".
 */
class TipoDocumento extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'tipos_documento';

    protected $fillable = ['nombre', 'descripcion', 'obligatorio', 'aplica_a', 'activo'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'obligatorio' => 'boolean',
        'activo' => 'boolean',
    ];
}
