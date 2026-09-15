<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\EstadoEstudio;
use App\Modules\Tenancy\EstadoFacturacion;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro central de un estudio (tenant SaaS) en el control plane. Vive en la
 * conexión por defecto (central); su BD operativa (data plane) es independiente
 * y se describe con `db_driver` + `db_database`. No usa BelongsToTenant: ES el
 * catálogo de tenants, no un dato tenant-scoped.
 */
class Estudio extends Model
{
    use HasPublicId;

    protected $table = 'estudios';

    protected $fillable = [
        'nombre',
        'slug',
        'logo_url',
        'estado',
        'paso_aprovisionamiento',
        'aprovisionado_en',
        'publicado',
        'privado',
        'pais',
        'ciudad',
        'zona_horaria',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'trial_inicia_en',
        'trial_termina_en',
        'plan',
        'precio_por_alumno_minor',
        'moneda',
        'estado_facturacion',
        'db_driver',
        'db_database',
        'version_migraciones',
        'onboarding_pasos',
        'onboarding_completo',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => EstadoEstudio::class,
        'estado_facturacion' => EstadoFacturacion::class,
        'publicado' => 'boolean',
        'privado' => 'boolean',
        'aprovisionado_en' => 'datetime',
        'trial_inicia_en' => 'date',
        'trial_termina_en' => 'date',
        'precio_por_alumno_minor' => 'integer',
        'onboarding_pasos' => 'array',
        'onboarding_completo' => 'boolean',
    ];

    /**
     * ¿El estudio aparece en el directorio público?
     */
    public function enDirectorio(): bool
    {
        return $this->publicado && ! $this->privado && $this->estado->operativo();
    }
}
