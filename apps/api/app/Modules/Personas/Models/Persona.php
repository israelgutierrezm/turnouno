<?php

declare(strict_types=1);

namespace App\Modules\Personas\Models;

use App\Models\User;
use App\Modules\Hogares\Models\Hogar;
use App\Modules\Personas\Database\Factories\PersonaFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un ser humano dentro de un tenant. Puede vincularse a un User (opcional),
 * pertenecer a un Hogar y ostentar varios perfiles (Miembro, Tutor, ...).
 * Persona != User != Miembro.
 *
 * Columnas declaradas para el analizador (Larastan pierde el esquema legacy por la
 * colisión de nombres con las tablas del data plane; ver {@see BelongsToTenant}).
 *
 * @property int|null $user_id
 * @property int|null $hogar_id
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
        'hogar_id',
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
     * @return BelongsTo<Hogar, $this>
     */
    public function hogar(): BelongsTo
    {
        return $this->belongsTo(Hogar::class);
    }

    /**
     * @return HasMany<Perfil, $this>
     */
    public function perfiles(): HasMany
    {
        return $this->hasMany(Perfil::class);
    }

    /**
     * Personas que esta persona tiene a su cargo (como tutor).
     *
     * @return BelongsToMany<Persona, $this>
     */
    public function dependientes(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'tutelas', 'tutor_id', 'dependiente_id')
            ->withPivot(['parentesco'])
            ->withTimestamps();
    }

    /**
     * Personas responsables de esta persona (sus tutores).
     *
     * @return BelongsToMany<Persona, $this>
     */
    public function tutores(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'tutelas', 'dependiente_id', 'tutor_id')
            ->withPivot(['parentesco'])
            ->withTimestamps();
    }

    protected static function newFactory(): PersonaFactory
    {
        return PersonaFactory::new();
    }
}
