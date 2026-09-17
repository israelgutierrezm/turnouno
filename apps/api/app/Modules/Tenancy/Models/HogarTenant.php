<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hogar tenant-local (R26): agrupa a las personas de una familia. El comprador (tutor)
 * y los participantes (dependientes) pueden pertenecer al mismo hogar.
 */
class HogarTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'hogares';

    protected $fillable = ['nombre'];

    /**
     * @return HasMany<PersonaTenant, $this>
     */
    public function personas(): HasMany
    {
        return $this->hasMany(PersonaTenant::class, 'hogar_id');
    }
}
