<?php

declare(strict_types=1);

namespace App\Modules\Personas\Models;

use App\Models\User;
use App\Modules\Personas\Database\Factories\PersonaFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un ser humano dentro de un tenant. Puede vincularse a un User (opcional) y
 * ostentar varios perfiles (Miembro, Instructor, ...). Persona != User != Miembro.
 *
 * Columnas declaradas para el analizador (Larastan pierde el esquema legacy por la
 * colisión de nombres con las tablas del data plane; ver {@see BelongsToTenant}).
 *
 * @property int|null $user_id
 * @property string|null $apellidos
 * @property Carbon|null $fecha_nacimiento
 */
class Persona extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PersonaFactory> */
    use HasFactory;

    use HasPublicId;

    protected $table = 'personas';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'nombre',
        'apellidos',
        'email',
        'fecha_nacimiento',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Perfil, $this>
     */
    public function perfiles(): HasMany
    {
        return $this->hasMany(Perfil::class);
    }

    protected static function newFactory(): PersonaFactory
    {
        return PersonaFactory::new();
    }
}
