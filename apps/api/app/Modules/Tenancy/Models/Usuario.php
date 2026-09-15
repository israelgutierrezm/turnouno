<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Application\CatalogoDePermisosTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario tenant-local: identidad operativa que vive en la BD del propio tenant
 * (conexión `tenant`). El email es único por tenant, así que el mismo correo en
 * otro estudio es una cuenta distinta. Reemplaza, en el data plane, al `User`
 * global del esquema compartido (que queda solo durante la transición).
 */
class Usuario extends Authenticatable
{
    use HasPublicId;
    use Notifiable;

    protected $connection = 'tenant';

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'google_id', 'activo', 'activation_token', 'rol'];

    /**
     * ¿El usuario tiene el permiso dado según su rol tenant-local?
     */
    public function puede(string $permiso): bool
    {
        return CatalogoDePermisosTenant::puede((string) $this->rol, $permiso);
    }

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }
}
