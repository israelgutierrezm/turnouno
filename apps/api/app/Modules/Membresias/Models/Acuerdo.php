<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Models;

use App\Modules\Membresias\EstadoAcuerdo;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acuerdo: la compra de un producto comercial por una persona.
 */
class Acuerdo extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'acuerdos';

    protected $fillable = ['persona_id', 'producto_comercial_id', 'fecha_inicio', 'estado'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'estado' => EstadoAcuerdo::class,
    ];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return BelongsTo<ProductoComercial, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoComercial::class, 'producto_comercial_id');
    }

    /**
     * @return HasMany<Derecho, $this>
     */
    public function derechos(): HasMany
    {
        return $this->hasMany(Derecho::class);
    }
}
