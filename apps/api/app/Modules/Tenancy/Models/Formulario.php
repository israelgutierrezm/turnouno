<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Formulario dinámico definido por el administrador para solicitar información a
 * miembros/instructores, en la BD del tenant.
 */
class Formulario extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'formularios';

    protected $fillable = ['nombre', 'descripcion', 'aplica_a', 'activo'];

    /**
     * @var array<string, string>
     */
    protected $casts = ['activo' => 'boolean'];

    /**
     * @return HasMany<CampoFormulario, $this>
     */
    public function campos(): HasMany
    {
        return $this->hasMany(CampoFormulario::class, 'formulario_id')->orderBy('orden')->orderBy('id');
    }
}
