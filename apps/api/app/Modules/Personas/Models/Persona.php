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

/**
 * Un ser humano dentro de un tenant. Una Persona puede estar vinculada a un
 * User (cuenta de acceso, opcional) y ostentar varios perfiles más adelante
 * (Miembro, Tutor, Instructor, ...). Persona != User.
 */
class Persona extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PersonaFactory> */
    use HasFactory;

    use HasPublicId;

    protected $table = 'personas';

    protected $fillable = ['tenant_id', 'user_id', 'nombre', 'apellidos', 'email'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): PersonaFactory
    {
        return PersonaFactory::new();
    }
}
