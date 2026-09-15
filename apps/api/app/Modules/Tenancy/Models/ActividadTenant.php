<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Actividad del catálogo (dentro de un programa), tenant-local.
 */
class ActividadTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'actividades';

    protected $fillable = ['programa_id', 'nombre', 'slug'];

    /**
     * @return BelongsTo<ProgramaTenant, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaTenant::class, 'programa_id');
    }

    /**
     * @return HasMany<NivelTenant, $this>
     */
    public function niveles(): HasMany
    {
        return $this->hasMany(NivelTenant::class, 'actividad_id')->orderBy('orden')->orderBy('id');
    }

    /**
     * @return HasMany<OfertaTenant, $this>
     */
    public function ofertas(): HasMany
    {
        return $this->hasMany(OfertaTenant::class, 'actividad_id')->orderBy('id');
    }
}
