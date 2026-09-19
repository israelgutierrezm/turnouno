<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos fiscales del emisor (el estudio) para CFDI vía FacturAPI, tenant-local.
 * La llave de la organización FacturAPI del tenant se guarda cifrada y oculta:
 * nunca se serializa ni se devuelve por la API.
 *
 * @property string|null $facturapi_organizacion_id
 * @property string|null $facturapi_llave
 */
class DatosFiscalesTenant extends Model
{
    use HasPublicId;

    protected $connection = 'tenant';

    protected $table = 'datos_fiscales';

    protected $fillable = [
        'razon_social', 'rfc', 'regimen_fiscal', 'codigo_postal',
        'facturapi_organizacion_id', 'facturapi_llave',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['facturapi_llave'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'facturapi_llave' => 'encrypted',
    ];

    /**
     * ¿El tenant ya está vinculado a una organización FacturAPI?
     */
    public function facturapiConectado(): bool
    {
        return $this->facturapi_organizacion_id !== null && $this->facturapi_llave !== null;
    }
}
