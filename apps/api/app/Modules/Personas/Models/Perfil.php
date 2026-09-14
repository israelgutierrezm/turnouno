<?php

declare(strict_types=1);

namespace App\Modules\Personas\Models;

use App\Modules\Personas\TipoPerfil;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un rol que ostenta una Persona (Miembro, Tutor, ...). Miembro != Persona:
 * el perfil "miembro" es solo uno de los roles que una persona puede tener.
 */
class Perfil extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'perfiles';

    protected $fillable = ['persona_id', 'tipo'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoPerfil::class,
    ];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
