<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Models;

use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Reservas\Models\Reserva;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Check-in de una reserva confirmada.
 */
class Asistencia extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'asistencias';

    protected $fillable = [
        'reserva_id',
        'estado',
        'registrada_en',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoAsistencia::class,
        'registrada_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<Reserva, $this>
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
