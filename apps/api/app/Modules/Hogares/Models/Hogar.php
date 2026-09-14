<?php

declare(strict_types=1);

namespace App\Modules\Hogares\Models;

use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupa a las personas de una familia. El comprador (tutor) y los
 * participantes (dependientes) pueden pertenecer al mismo hogar.
 */
class Hogar extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'hogares';

    protected $fillable = ['nombre'];

    /**
     * @return HasMany<Persona, $this>
     */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }
}
