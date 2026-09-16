<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Asiento de auditoria tenant-local (append-only): quien hizo que sobre que entidad,
 * con antes/despues, motivo, IP y correlation-id. Ver la migracion `auditorias`.
 */
class AuditoriaTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'auditorias';

    // Append-only: los asientos no se editan (sin updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id', 'actor_nombre', 'accion', 'entidad_tipo', 'entidad_id',
        'motivo', 'antes', 'despues', 'ip', 'correlation_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'antes' => 'array',
        'despues' => 'array',
    ];
}
