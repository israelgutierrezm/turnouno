<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Personas\Models\Persona;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Activa ventanilla, crea una orden para la persona del miembro y la cobra por
 * ventanilla (queda pendiente). Devuelve [pago_ulid, persona_ulid].
 *
 * @return array{pago: string, persona: string}
 */
function ventanillaPendiente(User $owner, Persona $personaMiembro): array
{
    Sanctum::actingAs($owner);
    test()->putJson('/api/v1/pasarelas/ventanilla', ['activa' => true, 'modo' => 'test'])->assertOk();

    $producto = crearProductoPack();
    $orden = test()->postJson('/api/v1/ordenes', [
        'persona_id' => $personaMiembro->ulid,
        'items' => [['producto_id' => $producto]],
    ])->assertCreated()->json('data.id');

    $pago = test()->postJson("/api/v1/ordenes/{$orden}/pagos", ['proveedor' => 'ventanilla', 'metodo' => 'ventanilla'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'pendiente')
        ->json('data.id');

    return ['pago' => $pago, 'persona' => $personaMiembro->ulid];
}

it('ventanilla: el miembro sube comprobante y el staff aprueba y concede el derecho', function (): void {
    Storage::fake('local');
    ['tenant' => $tenant, 'owner' => $owner] = tenantConDueno();
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);

    ['pago' => $pago, 'persona' => $persona] = ventanillaPendiente($owner, personaDe($miembro));

    // El miembro sube su comprobante.
    Sanctum::actingAs($miembro);
    $this->post("/api/v1/pagos/{$pago}/comprobante", ['comprobante' => UploadedFile::fake()->image('recibo.jpg')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.comprobante', true);

    // El staff revisa y aprueba → fulfillment.
    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/pagos/{$pago}/aprobar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'aprobado')
        ->assertJsonPath('data.orden_estado', 'pagada');

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(1, 'data');
});

it('no aprueba ventanilla sin comprobante', function (): void {
    Storage::fake('local');
    ['tenant' => $tenant, 'owner' => $owner] = tenantConDueno();
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);

    ['pago' => $pago] = ventanillaPendiente($owner, personaDe($miembro));

    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/pagos/{$pago}/aprobar")
        ->assertStatus(422)
        ->assertJsonPath('code', 'PROOF_REQUIRED');
});

it('rechazar ventanilla deja el pago rechazado sin conceder', function (): void {
    Storage::fake('local');
    ['tenant' => $tenant, 'owner' => $owner] = tenantConDueno();
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);

    ['pago' => $pago, 'persona' => $persona] = ventanillaPendiente($owner, personaDe($miembro));

    Sanctum::actingAs($miembro);
    $this->post("/api/v1/pagos/{$pago}/comprobante", ['comprobante' => UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertCreated();

    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/pagos/{$pago}/rechazar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'rechazado');

    $this->getJson("/api/v1/personas/{$persona}/derechos")->assertOk()->assertJsonCount(0, 'data');
});

it('lista los comprobantes de ventanilla pendientes para el staff', function (): void {
    Storage::fake('local');
    ['tenant' => $tenant, 'owner' => $owner] = tenantConDueno();
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);

    ['pago' => $pago] = ventanillaPendiente($owner, personaDe($miembro));

    Sanctum::actingAs($miembro);
    $this->post("/api/v1/pagos/{$pago}/comprobante", ['comprobante' => UploadedFile::fake()->image('recibo.jpg')], ['Accept' => 'application/json'])
        ->assertCreated();

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/pagos/ventanilla/pendientes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comprobante', true);
});

it('un miembro ajeno no puede subir el comprobante', function (): void {
    Storage::fake('local');
    ['tenant' => $tenant, 'owner' => $owner] = tenantConDueno();
    $miembro = User::factory()->create();
    vincularUsuario($tenant, $miembro, ['miembro']);
    $otro = User::factory()->create();
    vincularUsuario($tenant, $otro, ['miembro']);

    ['pago' => $pago] = ventanillaPendiente($owner, personaDe($miembro));

    // Otro miembro, que no es dueño de la orden.
    Sanctum::actingAs($otro);
    $this->post("/api/v1/pagos/{$pago}/comprobante", ['comprobante' => UploadedFile::fake()->image('recibo.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(403);
});
