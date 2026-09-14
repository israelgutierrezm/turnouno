<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nivel extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'niveles';

    protected $fillable = ['actividad_id', 'nombre', 'orden'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'orden' => 'integer',
    ];

    /**
     * @return BelongsTo<Actividad, $this>
     */
    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }
}
