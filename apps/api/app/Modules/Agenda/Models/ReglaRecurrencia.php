<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Models;

use App\Modules\Agenda\DiaSemana;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una repetición semanal de una plantilla: día (ISO) + hora local de inicio.
 */
class ReglaRecurrencia extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'reglas_recurrencia';

    protected $fillable = [
        'plantilla_horario_id',
        'dia_semana',
        'hora_inicio',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'dia_semana' => DiaSemana::class,
    ];

    /**
     * @return BelongsTo<PlantillaHorario, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaHorario::class, 'plantilla_horario_id');
    }
}
