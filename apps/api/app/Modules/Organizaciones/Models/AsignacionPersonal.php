<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Rol de un usuario con scope de sucursal: el "dónde" de la autorización que
 * complementa los roles tenant-wide de spatie (ver ADR-0008).
 */
class AsignacionPersonal extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'asignaciones_personal';

    protected $fillable = ['sucursal_id', 'user_id', 'role_id'];

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
