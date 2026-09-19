<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Llave de API tenant-local (R40) para integraciones de terceros. Solo persiste el
 * hash del secreto; los `scopes` acotan qué puede leer.
 *
 * @property list<string> $scopes
 */
class LlaveApiTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'llaves_api';

    protected $fillable = ['nombre', 'prefijo', 'hash', 'scopes', 'activa', 'ultimo_uso_en'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'scopes' => 'array',
        'activa' => 'boolean',
        'ultimo_uso_en' => 'datetime',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['hash'];

    /**
     * ¿La llave tiene el alcance (scope) indicado?
     */
    public function tieneAlcance(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
