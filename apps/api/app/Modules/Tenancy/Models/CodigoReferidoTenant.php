<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de referido de un miembro tenant-local (R23): lo comparte para invitar.
 *
 * @property int $persona_id
 */
class CodigoReferidoTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'codigos_referido';

    protected $fillable = ['persona_id', 'codigo'];

    /**
     * @return BelongsTo<PersonaTenant, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(PersonaTenant::class, 'persona_id');
    }
}
