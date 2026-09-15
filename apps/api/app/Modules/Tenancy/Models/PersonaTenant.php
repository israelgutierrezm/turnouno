<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\TipoPersonaTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Persona operativa tenant-local (miembro/alumno o instructor), en la BD del
 * tenant. Reemplaza, en el data plane, a la `Persona` del esquema compartido.
 */
class PersonaTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'personas';

    protected $fillable = ['nombre', 'apellidos', 'email', 'tipo', 'activo', 'es_facturable', 'archivado', 'usuario_id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoPersonaTenant::class,
        'activo' => 'boolean',
        'es_facturable' => 'boolean',
        'archivado' => 'boolean',
    ];
}
