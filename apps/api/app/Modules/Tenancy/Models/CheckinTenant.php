<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Check-in de un usuario de una plataforma de bienestar (Wellhub / TotalPass) en una
 * sesion, tenant-local. Registra el acceso validado contra el proveedor; no consume
 * creditos del estudio.
 */
class CheckinTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'checkins';

    protected $fillable = [
        'sesion_id', 'proveedor', 'referencia_externa', 'nombre_usuario', 'estado', 'registrado_en',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'registrado_en' => 'datetime',
    ];

    /**
     * @return BelongsTo<SesionTenant, $this>
     */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionTenant::class, 'sesion_id');
    }
}
