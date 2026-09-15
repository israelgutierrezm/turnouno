<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Nivel de una actividad, tenant-local.
 */
class NivelTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'niveles';

    protected $fillable = ['actividad_id', 'nombre', 'orden'];

    /**
     * @var array<string, string>
     */
    protected $casts = ['orden' => 'integer'];
}
