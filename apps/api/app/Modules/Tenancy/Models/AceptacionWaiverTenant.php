<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptacion sellada de un waiver por una persona (R27): registra cuando (aceptado_en),
 * desde donde (ip) y el hash de la version aceptada (evidencia). Unica por
 * (persona, waiver-version).
 */
class AceptacionWaiverTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'aceptaciones_waiver';

    protected $fillable = ['persona_id', 'waiver_id', 'aceptado_en', 'ip', 'hash'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'aceptado_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<WaiverTenant, $this>
     */
    public function waiver(): BelongsTo
    {
        return $this->belongsTo(WaiverTenant::class, 'waiver_id');
    }
}
