<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Facturacion\ClienteFacturacion;
use App\Modules\Tenancy\Facturacion\ResultadoTimbre;
use App\Modules\Tenancy\Facturacion\TimbradoFallido;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * Activa Stripe en la plataforma (simulado), genera el cargo de renta, lo paga y lo
 * confirma por webhook. Devuelve el ulid del cargo PAGADO (listo para facturar).
 *
 * @param  array{slug: string, bearer: string}  $e
 */
function cargoRentaPagado(array $e): string
{
    activarStripePlataforma();
    $cargo = cargoRentaPendiente($e);

    $ref = (string) test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/pagar", [
        'proveedor' => 'stripe',
    ], conBearer($e['bearer']))->assertCreated()->json('data.referencia');

    test()->postJson('/api/v1/webhooks/plataforma/stripe', [
        'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $ref]],
    ])->assertOk();

    return $cargo;
}

it('emite el CFDI de la renta de un cargo pagado (IVA incluido) y es idempotente', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    cargarDatosFiscales($e);
    $cargo = cargoRentaPagado($e); // cuota fija 149900

    $r = test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(201)
        ->assertJsonPath('data.estado', 'timbrada');

    $facturaId = (string) $r->json('data.id');
    expect($r->json('data.uuid'))->not->toBeNull();
    // 149900 es el TOTAL (IVA incluido): subtotal + impuesto cuadran exactamente.
    expect((int) $r->json('data.total_minor'))->toBe(149900);
    expect((int) $r->json('data.subtotal_minor') + (int) $r->json('data.impuesto_minor'))->toBe(149900);

    // Idempotente: re-emitir devuelve la MISMA factura (no timbra otra).
    test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(201)->assertJsonPath('data.id', $facturaId);
});

it('no factura la renta sin datos fiscales cargados (FISCAL_DATA_REQUIRED)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $cargo = cargoRentaPagado($e); // sin cargar datos fiscales

    test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(422)->assertJsonPath('code', 'FISCAL_DATA_REQUIRED');
});

it('no factura un cargo de renta no pagado (RENT_CHARGE_NOT_INVOICEABLE)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    cargarDatosFiscales($e);
    $cargo = cargoRentaPendiente($e); // pendiente

    test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(409)->assertJsonPath('code', 'RENT_CHARGE_NOT_INVOICEABLE');
});

it('registra el error si el proveedor rechaza el timbre de la renta', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    cargarDatosFiscales($e);
    $cargo = cargoRentaPagado($e);

    app()->instance(ClienteFacturacion::class, new class implements ClienteFacturacion
    {
        public function timbrar(string $llaveOrganizacion, array $factura): ResultadoTimbre
        {
            throw new TimbradoFallido('RFC del receptor no valido.');
        }

        public function descargar(string $llaveOrganizacion, string $facturaId, string $formato): string
        {
            return '';
        }
    });

    test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(422)
        ->assertJsonPath('data.estado', 'error')
        ->assertJsonPath('data.motivo_error', 'RFC del receptor no valido.');
});

it('descarga el PDF y el XML de la factura de renta timbrada', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    cargarDatosFiscales($e);
    $cargo = cargoRentaPagado($e);

    $facturaId = (string) test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(201)->json('data.id');

    $pdf = test()->get("/api/v1/app/{$e['slug']}/renta/facturas/{$facturaId}/pdf", conBearer($e['bearer']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');

    test()->get("/api/v1/app/{$e['slug']}/renta/facturas/{$facturaId}/xml", conBearer($e['bearer']))
        ->assertOk();
});

it('facturar la renta exige permiso de facturación', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    cargarDatosFiscales($e);
    $cargo = cargoRentaPagado($e);
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($coach))
        ->assertStatus(403);
});

it('el apartado de renta muestra la factura emitida del cargo', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    cargarDatosFiscales($e);
    $cargo = cargoRentaPagado($e);

    test()->postJson("/api/v1/app/{$e['slug']}/renta/cargos/{$cargo}/factura", [], conBearer($e['bearer']))
        ->assertStatus(201);

    test()->getJson("/api/v1/app/{$e['slug']}/renta", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.cargos.0.factura.estado', 'timbrada');
});
