<?php

declare(strict_types=1);

namespace App\Modules\Ordenes\Models;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de compra: cabecera con el comprador y el total (snapshot).
 */
class Orden extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'ordenes';

    protected $fillable = ['persona_id', 'estado', 'total_minor', 'moneda'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoOrden::class,
        'total_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return HasMany<LineaOrden, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaOrden::class);
    }
}
