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
use App\Modules\Membresias\Http\Controllers\TopUpController;
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
use App\Modules\Portal\Http\Controllers\AgendaController as PortalAgendaController;
use App\Modules\Portal\Http\Controllers\CompraController as PortalCompraController;
use App\Modules\Portal\Http\Controllers\PasarelaPublicaController;
use App\Modules\Portal\Http\Controllers\PerfilController as PortalPerfilController;
use App\Modules\Recursos\Http\Controllers\InstalacionController;
use App\Modules\Recursos\Http\Controllers\RecursoController;
use App\Modules\Reservas\Http\Controllers\ReservaController;
use App\Modules\Tenancy\Http\Controllers\AgendaTenantController;
use App\Modules\Tenancy\Http\Controllers\AuthTenantController;
use App\Modules\Tenancy\Http\Controllers\CatalogoTenantController;
use App\Modules\Tenancy\Http\Controllers\CreditosTenantController;
use App\Modules\Tenancy\Http\Controllers\DirectorioController;
use App\Modules\Tenancy\Http\Controllers\DocumentosController;
use App\Modules\Tenancy\Http\Controllers\FacturacionController;
use App\Modules\Tenancy\Http\Controllers\FormulariosController;
use App\Modules\Tenancy\Http\Controllers\MembresiasTenantController;
use App\Modules\Tenancy\Http\Controllers\MiembrosTenantController;
use App\Modules\Tenancy\Http\Controllers\OnboardingController;
use App\Modules\Tenancy\Http\Controllers\OrganizacionesTenantController;
use App\Modules\Tenancy\Http\Controllers\RegistroEstudioController;
use App\Modules\Tenancy\Http\Controllers\RespuestasFormularioController;
use App\Modules\Tenancy\Http\Controllers\TiposDocumentoController;
use App\Modules\Tenancy\Http\Controllers\UsuariosTenantController;
use Illuminate\Support\Facades\Route;

/*
| API v1. El prefijo "api" lo aplica bootstrap/app.php withRouting(), por lo que
| estas rutas resuelven bajo /api/v1/*. Rutas técnicas (auth, me, health) en
| inglés; recursos de dominio en español.
*/
Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Autenticación (sin sesión previa). Con rate limit por identidad+IP (SEC-03).
    Route::post('/auth/token', [TokenController::class, 'store'])->middleware('throttle:login')->name('api.v1.auth.token');
    Route::post('/auth/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('api.v1.auth.login');

    // Webhook de pagos: público (sin sesión ni tenant); idempotente. La firma del
    // proveedor debe verificarse antes de producción.
    Route::post('/webhooks/pagos/{proveedor}', WebhookPagoController::class)->name('api.v1.webhooks.pagos');
    Route::post('/webhooks/stripe', WebhookStripeController::class)->name('api.v1.webhooks.stripe');
    Route::post('/webhooks/openpay', WebhookOpenPayController::class)->name('api.v1.webhooks.openpay');
    Route::post('/webhooks/mercadopago/{tenant}', WebhookMercadoPagoController::class)->name('api.v1.webhooks.mercadopago');

    /*
    | Control plane (SaaS multi-tenant por BD). Alta pública de estudios y
    | directorio (sin tenant), y acceso tenant-local resuelto por slug: la
    | identidad vive en la BD de cada estudio (no hay login global ni selector
    | de tenant tras el login). Ver docs/CONTROL_PLANE.md.
    */
    Route::post('/registro', [RegistroEstudioController::class, 'store'])->middleware('throttle:login')->name('api.v1.registro');
    Route::get('/registro/slug', [RegistroEstudioController::class, 'disponibilidad'])->middleware('throttle:60,1')->name('api.v1.registro.slug');
    Route::get('/directorio', [DirectorioController::class, 'index'])->middleware('throttle:60,1')->name('api.v1.directorio');

    Route::prefix('app/{estudio}')->middleware('estudio.resolver')->group(function (): void {
        Route::post('/login', [AuthTenantController::class, 'store'])->middleware('throttle:login')->name('api.v1.app.login');
        Route::post('/activar', [AuthTenantController::class, 'activar'])->middleware('throttle:login')->name('api.v1.app.activar');

        Route::middleware('estudio.auth')->group(function (): void {
            Route::get('/yo', [AuthTenantController::class, 'yo'])->name('api.v1.app.yo');
            Route::post('/logout', [AuthTenantController::class, 'destroy'])->name('api.v1.app.logout');

            // Invitación de personal (crea usuario tenant-local con rol + activación).
            Route::post('/usuarios/invitar', [UsuariosTenantController::class, 'invitar'])->middleware('puede:usuarios.invitar')->name('api.v1.app.usuarios.invitar');

            // Operación tenant-local: alta de alumnos (data plane del estudio).
            Route::get('/miembros', [MiembrosTenantController::class, 'index'])->middleware('puede:miembros.ver')->name('api.v1.app.miembros.index');
            Route::post('/miembros', [MiembrosTenantController::class, 'store'])->middleware('puede:miembros.gestionar')->name('api.v1.app.miembros.store');

            // Facturación SaaS del estudio (control plane; separada de pagos de alumnos).
            Route::get('/facturacion', [FacturacionController::class, 'show'])->middleware('puede:facturacion.ver')->name('api.v1.app.facturacion');

            // Onboarding (guardar y continuar) y publicación en el directorio.
            Route::get('/onboarding', [OnboardingController::class, 'show'])->middleware('puede:estudio.gestionar')->name('api.v1.app.onboarding.show');
            Route::put('/onboarding', [OnboardingController::class, 'guardar'])->middleware('puede:estudio.gestionar')->name('api.v1.app.onboarding.guardar');
            Route::put('/publicacion', [OnboardingController::class, 'publicacion'])->middleware('puede:estudio.gestionar')->name('api.v1.app.publicacion');

            // Documentos: el admin define tipos requeridos; se cargan por persona y
            // el staff los valida (tenant-local, aislado).
            Route::get('/tipos-documento', [TiposDocumentoController::class, 'index'])->middleware('puede:miembros.ver')->name('api.v1.app.tipos-documento.index');
            Route::post('/tipos-documento', [TiposDocumentoController::class, 'store'])->middleware('puede:documentos.gestionar')->name('api.v1.app.tipos-documento.store');
            Route::put('/tipos-documento/{tipo}', [TiposDocumentoController::class, 'update'])->middleware('puede:documentos.gestionar')->name('api.v1.app.tipos-documento.update');
            Route::get('/documentos', [DocumentosController::class, 'index'])->middleware('puede:miembros.ver')->name('api.v1.app.documentos.index');
            Route::post('/documentos', [DocumentosController::class, 'subir'])->middleware('puede:documentos.subir')->name('api.v1.app.documentos.subir');
            Route::get('/documentos/{documento}', [DocumentosController::class, 'ver'])->middleware('puede:miembros.ver')->name('api.v1.app.documentos.ver');
            Route::post('/documentos/{documento}/validar', [DocumentosController::class, 'validar'])->middleware('puede:documentos.gestionar')->name('api.v1.app.documentos.validar');

            // Formularios dinámicos: el admin define formularios/campos; miembros e
            // instructores responden (validación dinámica). Tenant-local.
            Route::get('/formularios', [FormulariosController::class, 'index'])->middleware('puede:formularios.responder')->name('api.v1.app.formularios.index');
            Route::post('/formularios', [FormulariosController::class, 'store'])->middleware('puede:formularios.gestionar')->name('api.v1.app.formularios.store');
            Route::post('/formularios/{formulario}/campos', [FormulariosController::class, 'agregarCampo'])->middleware('puede:formularios.gestionar')->name('api.v1.app.formularios.campos');
            Route::get('/formularios/{formulario}/respuestas', [RespuestasFormularioController::class, 'index'])->middleware('puede:formularios.gestionar')->name('api.v1.app.formularios.respuestas.index');
            Route::post('/formularios/{formulario}/respuestas', [RespuestasFormularioController::class, 'store'])->middleware('puede:formularios.responder')->name('api.v1.app.formularios.respuestas.store');

            // Catálogo del estudio (data plane del tenant): Programa → Actividad →
            // Nivel/Oferta. Primer módulo operativo migrado a la BD del tenant.
            Route::get('/programas', [CatalogoTenantController::class, 'programas'])->middleware('puede:catalogo.ver')->name('api.v1.app.programas.index');
            Route::post('/programas', [CatalogoTenantController::class, 'crearPrograma'])->middleware('puede:catalogo.gestionar')->name('api.v1.app.programas.store');
            Route::post('/programas/{programa}/actividades', [CatalogoTenantController::class, 'crearActividad'])->middleware('puede:catalogo.gestionar')->name('api.v1.app.actividades.store');
            Route::post('/actividades/{actividad}/niveles', [CatalogoTenantController::class, 'crearNivel'])->middleware('puede:catalogo.gestionar')->name('api.v1.app.niveles.store');
            Route::post('/actividades/{actividad}/ofertas', [CatalogoTenantController::class, 'crearOferta'])->middleware('puede:catalogo.gestionar')->name('api.v1.app.ofertas.store');
            Route::get('/ofertas', [CatalogoTenantController::class, 'ofertas'])->middleware('puede:catalogo.ver')->name('api.v1.app.ofertas.index');

            // Estructura del estudio (data plane del tenant): Organización → Sucursal.
            Route::get('/organizaciones', [OrganizacionesTenantController::class, 'organizaciones'])->middleware('puede:organizaciones.ver')->name('api.v1.app.organizaciones.index');
            Route::post('/organizaciones', [OrganizacionesTenantController::class, 'crearOrganizacion'])->middleware('puede:organizaciones.gestionar')->name('api.v1.app.organizaciones.store');
            Route::post('/organizaciones/{organizacion}/sucursales', [OrganizacionesTenantController::class, 'crearSucursal'])->middleware('puede:sucursales.gestionar')->name('api.v1.app.sucursales.store');
            Route::get('/sucursales', [OrganizacionesTenantController::class, 'sucursales'])->middleware('puede:sucursales.ver')->name('api.v1.app.sucursales.index');

            // Agenda (data plane del tenant): materializa una Oferta en una Sucursal
            // a una hora concreta. La hora local (zona de la sucursal) se guarda en UTC
            // con snapshot de zona.
            Route::get('/sesiones', [AgendaTenantController::class, 'sesiones'])->middleware('puede:agenda.ver')->name('api.v1.app.sesiones.index');
            Route::post('/sesiones', [AgendaTenantController::class, 'crearSesion'])->middleware('puede:agenda.gestionar')->name('api.v1.app.sesiones.store');
            Route::post('/sesiones/{sesion}/cancelar', [AgendaTenantController::class, 'cancelar'])->middleware('puede:agenda.gestionar')->name('api.v1.app.sesiones.cancelar');

            // Membresias (data plane del tenant): producto comercial → acuerdo →
            // derecho (entitlement) + ledger de creditos. El saldo se deriva del
            // ledger. La venta y las mutaciones del ledger son concurrency-safe.
            Route::get('/productos', [MembresiasTenantController::class, 'productos'])->middleware('puede:productos.ver')->name('api.v1.app.productos.index');
            Route::post('/productos', [MembresiasTenantController::class, 'crearProducto'])->middleware('puede:productos.gestionar')->name('api.v1.app.productos.store');
            Route::post('/acuerdos', [MembresiasTenantController::class, 'vender'])->middleware('puede:membresias.gestionar')->name('api.v1.app.acuerdos.store');
            Route::get('/miembros/{persona}/derechos', [MembresiasTenantController::class, 'derechos'])->middleware('puede:derechos.ver')->name('api.v1.app.miembros.derechos.index');
            Route::post('/derechos/{derecho}/topups', [MembresiasTenantController::class, 'topUp'])->middleware('puede:membresias.gestionar')->name('api.v1.app.derechos.topups.store');

            // Creditos (data plane del tenant): consumo directo y retenciones (holds)
            // con confirmar/liberar/perder. Concurrencia protegida (lockForUpdate).
            Route::post('/derechos/{derecho}/consumos', [CreditosTenantController::class, 'consumir'])->middleware('puede:creditos.gestionar')->name('api.v1.app.derechos.consumos.store');
            Route::post('/derechos/{derecho}/retenciones', [CreditosTenantController::class, 'retener'])->middleware('puede:creditos.gestionar')->name('api.v1.app.derechos.retenciones.store');
            Route::post('/retenciones/{retencion}/confirmar', [CreditosTenantController::class, 'confirmar'])->middleware('puede:creditos.gestionar')->name('api.v1.app.retenciones.confirmar');
            Route::post('/retenciones/{retencion}/liberar', [CreditosTenantController::class, 'liberar'])->middleware('puede:creditos.gestionar')->name('api.v1.app.retenciones.liberar');
            Route::post('/retenciones/{retencion}/perder', [CreditosTenantController::class, 'perder'])->middleware('puede:creditos.gestionar')->name('api.v1.app.retenciones.perder');
        });
    });

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
                Route::get('/actividades', [ActividadController::class, 'index'])->name('api.v1.actividades.index');
                Route::get('/actividades/{actividad}', [ActividadController::class, 'show'])->name('api.v1.actividades.show');
                Route::post('/actividades/{actividad}/niveles', [NivelController::class, 'store'])->name('api.v1.actividades.niveles.store');
                Route::get('/ofertas', [OfertaController::class, 'index'])->name('api.v1.ofertas.index');
                Route::post('/actividades/{actividad}/ofertas', [OfertaController::class, 'store'])->name('api.v1.actividades.ofertas.store');

                // Membresías: producto comercial → acuerdo → derecho (+ ledger).
                Route::get('/productos', [ProductoComercialController::class, 'index'])->name('api.v1.productos.index');
                Route::post('/productos', [ProductoComercialController::class, 'store'])->name('api.v1.productos.store');
                Route::post('/personas/{persona}/acuerdos', [AcuerdoController::class, 'store'])->name('api.v1.personas.acuerdos.store');
                Route::get('/personas/{persona}/derechos', [DerechoController::class, 'index'])->name('api.v1.personas.derechos.index');
                Route::post('/derechos/{derecho}/topups', [TopUpController::class, 'store'])->name('api.v1.derechos.topups.store');

                // Órdenes y pagos: orden pendiente → cobro (pasarela) → fulfillment (derechos).
                Route::get('/ordenes', [OrdenController::class, 'index'])->name('api.v1.ordenes.index');
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

                // Portal del miembro (self-service): opera sobre la persona del usuario.
                Route::get('/mi/perfil', [PortalPerfilController::class, 'show'])->name('api.v1.mi.perfil');
                Route::get('/mi/agenda', [PortalAgendaController::class, 'index'])->name('api.v1.mi.agenda');
                Route::post('/mi/reservas', [PortalAgendaController::class, 'reservar'])->name('api.v1.mi.reservas.store');
                Route::post('/mi/reservas/{reserva}/cancelar', [PortalAgendaController::class, 'cancelar'])->name('api.v1.mi.reservas.cancelar');
                Route::get('/mi/productos', [PortalCompraController::class, 'productos'])->name('api.v1.mi.productos');
                Route::get('/mi/ordenes', [PortalCompraController::class, 'ordenes'])->name('api.v1.mi.ordenes.index');
                Route::post('/mi/ordenes', [PortalCompraController::class, 'crearOrden'])->name('api.v1.mi.ordenes.store');
                Route::post('/mi/ordenes/{orden}/pagos', [PortalCompraController::class, 'pagar'])->name('api.v1.mi.ordenes.pagos.store');
                Route::get('/mi/pasarelas', [PasarelaPublicaController::class, 'index'])->name('api.v1.mi.pasarelas');
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
