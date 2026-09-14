<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Database\Factories\TenantFactory;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Tenant is the SaaS security and billing boundary. Branches, people and
 * commerce live inside a tenant (see docs/TENANCY.md).
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = ['name', 'slug', 'status'];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Persona, $this>
     */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
