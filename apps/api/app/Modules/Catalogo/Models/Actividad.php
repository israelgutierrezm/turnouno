<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'actividades';

    protected $fillable = ['programa_id', 'nombre', 'slug'];

    /**
     * @return BelongsTo<Programa, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /**
     * @return HasMany<Nivel, $this>
     */
    public function niveles(): HasMany
    {
        return $this->hasMany(Nivel::class);
    }

    /**
     * @return HasMany<Oferta, $this>
     */
    public function ofertas(): HasMany
    {
        return $this->hasMany(Oferta::class);
    }
}
