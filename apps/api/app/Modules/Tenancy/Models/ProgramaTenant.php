<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa del catálogo, tenant-local (BD del tenant).
 */
class ProgramaTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'programas';

    protected $fillable = ['nombre', 'slug'];

    /**
     * @return HasMany<ActividadTenant, $this>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadTenant::class, 'programa_id')->orderBy('id');
    }
}
