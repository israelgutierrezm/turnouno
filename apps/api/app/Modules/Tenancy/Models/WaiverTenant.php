<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Waiver / consentimiento versionado tenant-local (R27). Cada publicacion de una
 * `clave` crea una nueva `version` con su `hash`; la ultima version activa es la
 * vigente que las personas deben aceptar.
 */
class WaiverTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'waivers';

    protected $fillable = ['clave', 'titulo', 'contenido', 'version', 'hash', 'activo'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'version' => 'integer',
        'activo' => 'boolean',
    ];
}
