<?php

declare(strict_types=1);

namespace App\Modules\Personas\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación de tutela: un tutor (persona responsable) sobre un dependiente
 * (persona participante, a menudo menor). Modela comprador != participante.
 */
class Tutela extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'tutelas';

    protected $fillable = ['tutor_id', 'dependiente_id', 'parentesco'];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'tutor_id');
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function dependiente(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'dependiente_id');
    }
}
