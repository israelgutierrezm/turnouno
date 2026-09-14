<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Models;

use App\Modules\Catalogo\ModalidadOferta;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oferta extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'ofertas';

    protected $fillable = ['actividad_id', 'nombre', 'modalidad', 'capacidad'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'modalidad' => ModalidadOferta::class,
        'capacidad' => 'integer',
    ];

    /**
     * @return BelongsTo<Actividad, $this>
     */
    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }
}
