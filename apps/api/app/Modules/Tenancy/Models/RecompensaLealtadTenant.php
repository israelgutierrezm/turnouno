<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Recompensa canjeable del programa de lealtad (tenant-local): un premio con costo en
 * puntos que el miembro obtiene al canjear.
 *
 * @property int $costo_puntos
 * @property bool $activa
 */
class RecompensaLealtadTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'recompensas_lealtad';

    protected $fillable = ['nombre', 'descripcion', 'costo_puntos', 'activa'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'costo_puntos' => 'integer',
        'activa' => 'boolean',
    ];
}
