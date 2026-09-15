<?php

declare(strict_types=1);

use App\Modules\Agenda\Http\Controllers\AsignacionSesionController;
use App\Modules\Agenda\Http\Controllers\MiAgendaController;
use App\Modules\Agenda\Http\Controllers\PlantillaHorarioController;
use App\Modules\Agenda\Http\Controllers\SesionController;
use App\Modules\Asistencia\Http\Controllers\AsistenciaController;
use App\Modules\Catalogo\Http\Controllers\ActividadController;
use App\Modules\Catalogo\Http\Controllers\NivelController;
use App\Modules\Catalogo\Http\Controllers\OfertaController;
use App\Modules\Catalogo\Http\Controllers\ProgramaController;
use App\Modules\Creditos\Http\Controllers\ConsumoController;
use App\Modules\Creditos\Http\Controllers\RetencionController;
use App\Modules\Hogares\Http\Controllers\HogarController;
use App\Modules\Identity\Http\Controllers\Auth\SessionController;
use App\Modules\Identity\Http\Controllers\Auth\TokenController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\UsuarioController;
use App\Modules\Membresias\Http\Controllers\AcuerdoController;
use App\Modules\Membresias\Http\Controllers\DerechoController;
use App\Modules\Membresias\Http\Controllers\ProductoComercialController;
use App\Modules\Ordenes\Http\Controllers\OrdenController;
use App\Modules\Organizaciones\Http\Controllers\OrganizacionController;
use App\Modules\Organizaciones\Http\Controllers\PersonalSucursalController;
use App\Modules\Organizaciones\Http\Controllers\SucursalController;
use App\Modules\Pagos\Http\Controllers\ConfiguracionPasarelaController;
use App\Modules\Pagos\Http\Controllers\PagoController;
use App\Modules\Pagos\Http\Controllers\VentanillaController;
use App\Modules\Pagos\Http\Controllers\WebhookMercadoPagoController;
use App\Modules\Pagos\Http\Controllers\WebhookOpenPayController;
use App\Modules\Pagos\Http\Controllers\WebhookPagoController;
use App\Modules\Pagos\Http\Controllers\WebhookStripeController;
use App\Modules\Personas\Http\Controllers\DependientePersonaController;
use App\Modules\Personas\Http\Controllers\PerfilPersonaController;
use App\Modules\Personas\Http\Controllers\PersonaController;
use App\Modules\Platform\Http\Controllers\HealthController;
use App\Modules\Recursos\Http\Controllers\InstalacionController;
use App\Modules\Recursos\Http\Controllers\RecursoController;
use App\Modules\Reservas\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;

/*
| API v1. El prefijo "api" lo aplica bootstrap/app.php withRouting(), por lo que
| estas rutas resuelven bajo /api/v1/*. Rutas técnicas (auth, me, health) en
| inglés; recursos de dominio en español.
*/
Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Autenticación (sin sesión previa).
    Route::post('/auth/token', [TokenController::class, 'store'])->name('api.v1.auth.token');
    Route::post('/auth/login', [SessionController::class, 'store'])->name('api.v1.auth.login');

    // Webhook de pagos: público (sin sesión ni tenant); idempotente. La firma del
    // proveedor debe verificarse antes de producción.
    Route::post('/webhooks/pagos/{proveedor}', WebhookPagoController::class)->name('api.v1.webhooks.pagos');
    Route::post('/webhooks/stripe', WebhookStripeController::class)->name('api.v1.webhooks.stripe');
    Route::post('/webhooks/openpay', WebhookOpenPayController::class)->name('api.v1.webhooks.openpay');
    Route::post('/webhooks/mercadopago/{tenant}', WebhookMercadoPagoController::class)->name('api.v1.webhooks.mercadopago');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/auth/token', [TokenController::class, 'destroy'])->name('api.v1.auth.token.destroy');
        Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('api.v1.auth.logout');

        Route::middleware('tenant.resolve')->group(function (): void {
            Route::get('/me', MeController::class)->name('api.v1.me');

            Route::middleware('tenant.require')->group(function (): void {
                Route::get('/usuarios', [UsuarioController::class, 'index'])->name('api.v1.usuarios.index');

                Route::get('/personas', [PersonaController::class, 'index'])->name('api.v1.personas.index');
                Route::post('/personas', [PersonaController::class, 'store'])->name('api.v1.personas.store');
                Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('api.v1.personas.show');
                Route::post('/personas/{persona}/perfiles', [PerfilPersonaController::class, 'store'])->name('api.v1.personas.perfiles.store');
                Route::post('/personas/{persona}/dependientes', [DependientePersonaController::class, 'store'])->name('api.v1.personas.dependientes.store');

                Route::get('/hogares', [HogarController::class, 'index'])->name('api.v1.hogares.index');
                Route::post('/hogares', [HogarController::class, 'store'])->name('api.v1.hogares.store');
                Route::get('/hogares/{hogar}', [HogarController::class, 'show'])->name('api.v1.hogares.show');

                // Catálogo: Programa → Actividad → (Nivel, Oferta).
                Route::get('/programas', [ProgramaController::class, 'index'])->name('api.v1.programas.index');
                Route::post('/programas', [ProgramaController::class, 'store'])->name('api.v1.programas.store');
                Route::get('/programas/{programa}', [ProgramaController::class, 'show'])->name('api.v1.programas.show');
                Route::post('/programas/{programa}/actividades', [ActividadController::class, 'store'])->name('api.v1.programas.actividades.store');
                Route::get('/actividades/{actividad}', [ActividadController::class, 'show'])->name('api.v1.actividades.show');
                Route::post('/actividades/{actividad}/niveles', [NivelController::class, 'store'])->name('api.v1.actividades.niveles.store');
                Route::get('/ofertas', [OfertaController::class, 'index'])->name('api.v1.ofertas.index');
                Route::post('/actividades/{actividad}/ofertas', [OfertaController::class, 'store'])->name('api.v1.actividades.ofertas.store');

                // Membresías: producto comercial → acuerdo → derecho (+ ledger).
                Route::get('/productos', [ProductoComercialController::class, 'index'])->name('api.v1.productos.index');
                Route::post('/productos', [ProductoComercialController::class, 'store'])->name('api.v1.productos.store');
                Route::post('/personas/{persona}/acuerdos', [AcuerdoController::class, 'store'])->name('api.v1.personas.acuerdos.store');
                Route::get('/personas/{persona}/derechos', [DerechoController::class, 'index'])->name('api.v1.personas.derechos.index');

                // Órdenes y pagos: orden pendiente → cobro (pasarela) → fulfillment (derechos).
                Route::post('/ordenes', [OrdenController::class, 'store'])->name('api.v1.ordenes.store');
                Route::get('/ordenes/{orden}', [OrdenController::class, 'show'])->name('api.v1.ordenes.show');
                Route::post('/ordenes/{orden}/pagos', [PagoController::class, 'store'])->name('api.v1.ordenes.pagos.store');
                Route::post('/pagos/{pago}/reembolso', [PagoController::class, 'reembolsar'])->name('api.v1.pagos.reembolso');

                // Ventanilla: el miembro sube comprobante; el staff aprueba/rechaza.
                Route::get('/pagos/ventanilla/pendientes', [VentanillaController::class, 'pendientes'])->name('api.v1.pagos.ventanilla.pendientes');
                Route::post('/pagos/{pago}/comprobante', [VentanillaController::class, 'subir'])->name('api.v1.pagos.comprobante.subir');
                Route::get('/pagos/{pago}/comprobante', [VentanillaController::class, 'ver'])->name('api.v1.pagos.comprobante.ver');
                Route::post('/pagos/{pago}/aprobar', [VentanillaController::class, 'aprobar'])->name('api.v1.pagos.aprobar');
                Route::post('/pagos/{pago}/rechazar', [VentanillaController::class, 'rechazar'])->name('api.v1.pagos.rechazar');

                // Configuración de pasarelas por tenant (encender/apagar + llaves).
                Route::get('/pasarelas', [ConfiguracionPasarelaController::class, 'index'])->name('api.v1.pasarelas.index');
                Route::get('/pasarelas/activas', [ConfiguracionPasarelaController::class, 'activas'])->name('api.v1.pasarelas.activas');
                Route::put('/pasarelas/{proveedor}', [ConfiguracionPasarelaController::class, 'upsert'])->name('api.v1.pasarelas.upsert');

                // Créditos: consumo y retenciones (holds) del ledger.
                Route::post('/derechos/{derecho}/consumos', [ConsumoController::class, 'store'])->name('api.v1.derechos.consumos.store');
                Route::post('/derechos/{derecho}/retenciones', [RetencionController::class, 'store'])->name('api.v1.derechos.retenciones.store');
                Route::post('/retenciones/{retencion}/confirmar', [RetencionController::class, 'confirmar'])->name('api.v1.retenciones.confirmar');
                Route::post('/retenciones/{retencion}/liberar', [RetencionController::class, 'liberar'])->name('api.v1.retenciones.liberar');
                Route::post('/retenciones/{retencion}/perder', [RetencionController::class, 'perder'])->name('api.v1.retenciones.perder');

                // Agenda: Oferta → Plantilla de horario → Sesión (materializada) + staff.
                Route::post('/ofertas/{oferta}/plantillas-horario', [PlantillaHorarioController::class, 'store'])->name('api.v1.ofertas.plantillas-horario.store');
                Route::post('/plantillas-horario/{plantilla}/sesiones', [PlantillaHorarioController::class, 'generar'])->name('api.v1.plantillas-horario.sesiones.generar');
                Route::get('/sucursales/{sucursal}/sesiones', [SesionController::class, 'index'])->name('api.v1.sucursales.sesiones.index');
                Route::post('/sucursales/{sucursal}/sesiones', [SesionController::class, 'store'])->name('api.v1.sucursales.sesiones.store');
                Route::post('/sesiones/{sesion}/cancelar', [SesionController::class, 'cancelar'])->name('api.v1.sesiones.cancelar');
                Route::post('/sesiones/{sesion}/asignaciones', [AsignacionSesionController::class, 'store'])->name('api.v1.sesiones.asignaciones.store');
                Route::get('/mis-sesiones', MiAgendaController::class)->name('api.v1.mis-sesiones');

                // Reservas (booking): motor transaccional sobre una sesión.
                Route::get('/sesiones/{sesion}/reservas', [ReservaController::class, 'index'])->name('api.v1.sesiones.reservas.index');
                Route::post('/sesiones/{sesion}/reservas', [ReservaController::class, 'store'])->name('api.v1.sesiones.reservas.store');
                Route::post('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar'])->name('api.v1.reservas.cancelar');
                Route::post('/reservas/{reserva}/asistencia', [AsistenciaController::class, 'store'])->name('api.v1.reservas.asistencia.store');

                // Recursos: Sucursal → Instalación → Recurso (jerárquico).
                Route::get('/sucursales/{sucursal}/instalaciones', [InstalacionController::class, 'index'])->name('api.v1.sucursales.instalaciones.index');
                Route::post('/sucursales/{sucursal}/instalaciones', [InstalacionController::class, 'store'])->name('api.v1.sucursales.instalaciones.store');
                Route::get('/instalaciones/{instalacion}', [InstalacionController::class, 'show'])->name('api.v1.instalaciones.show');
                Route::post('/instalaciones/{instalacion}/recursos', [RecursoController::class, 'store'])->name('api.v1.instalaciones.recursos.store');

                Route::get('/organizaciones', [OrganizacionController::class, 'index'])->name('api.v1.organizaciones.index');
                Route::post('/organizaciones', [OrganizacionController::class, 'store'])->name('api.v1.organizaciones.store');

                Route::get('/sucursales', [SucursalController::class, 'index'])->name('api.v1.sucursales.index');
                Route::post('/sucursales', [SucursalController::class, 'store'])->name('api.v1.sucursales.store');
                Route::get('/sucursales/{sucursal}', [SucursalController::class, 'show'])->name('api.v1.sucursales.show');

                Route::get('/sucursales/{sucursal}/personal', [PersonalSucursalController::class, 'index'])->name('api.v1.sucursales.personal.index');
                Route::post('/sucursales/{sucursal}/personal', [PersonalSucursalController::class, 'store'])->name('api.v1.sucursales.personal.store');
            });
        });
    });
});
