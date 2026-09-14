<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Global authentication identity. A User may belong to several tenants and,
 * within each tenant, is represented by a Person (User != Person).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPublicId;
    use HasRoles;
    use Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Tenants this user belongs to (SaaS memberships).
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    /**
     * Las personas (una por tenant) vinculadas a esta cuenta de acceso.
     *
     * @return HasMany<Persona, $this>
     */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }
}
