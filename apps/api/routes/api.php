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
use App\Modules\Tenancy\Http\Controllers\AsistenciaTenantController;
use App\Modules\Tenancy\Http\Controllers\AuthTenantController;
use App\Modules\Tenancy\Http\Controllers\CatalogoTenantController;
use App\Modules\Tenancy\Http\Controllers\CheckinsTenantController;
use App\Modules\Tenancy\Http\Controllers\CreditosTenantController;
use App\Modules\Tenancy\Http\Controllers\DirectorioController;
use App\Modules\Tenancy\Http\Controllers\DocumentosController;
use App\Modules\Tenancy\Http\Controllers\FacturacionController;
use App\Modules\Tenancy\Http\Controllers\FormulariosController;
use App\Modules\Tenancy\Http\Controllers\IntegracionesTenantController;
use App\Modules\Tenancy\Http\Controllers\MarcaEstudioController;
use App\Modules\Tenancy\Http\Controllers\MembresiasTenantController;
use App\Modules\Tenancy\Http\Controllers\MiembrosTenantController;
use App\Modules\Tenancy\Http\Controllers\MiTenantController;
use App\Modules\Tenancy\Http\Controllers\OnboardingController;
use App\Modules\Tenancy\Http\Controllers\OrdenesTenantController;
use App\Modules\Tenancy\Http\Controllers\OrganizacionesTenantController;
use App\Modules\Tenancy\Http\Controllers\PasarelasTenantController;
use App\Modules\Tenancy\Http\Controllers\RegistroEstudioController;
use App\Modules\Tenancy\Http\Controllers\ReservasTenantController;
use App\Modules\Tenancy\Http\Controllers\RespuestasFormularioController;
use App\Modules\Tenancy\Http\Controllers\TiposDocumentoController;
use App\Modules\Tenancy\Http\Controllers\UsuariosTenantController;
use App\Modules\Tenancy\Http\Controllers\WebhookTenantController;
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
    // Webhook publico de pasarela por estudio (data plane): resuelve el estudio por
    // slug y confirma el pago pendiente -> fulfillment. Sin sesion; idempotente.
    Route::post('/webhooks/tenant/{estudio}/{proveedor}', WebhookTenantController::class)
        ->middleware('estudio.resolver')->name('api.v1.webhooks.tenant');

    Route::post('/registro', [RegistroEstudioController::class, 'store'])->middleware('throttle:login')->name('api.v1.registro');
    Route::get('/registro/slug', [RegistroEstudioController::class, 'disponibilidad'])->middleware('throttle:60,1')->name('api.v1.registro.slug');
    Route::get('/directorio', [DirectorioController::class, 'index'])->middleware('throttle:60,1')->name('api.v1.directorio');

    /*
    | Rutas tenant-local. Se montan de dos formas equivalentes: por RUTA
    | (/app/{estudio}/...) y por SUBDOMINIO ({slug}.turnouno.com/...). En ambos
    | casos `estudio.resolver` lee el param `{estudio}` (de la ruta o del dominio)
    | y activa la conexión del data plane. Ver docs/CONTROL_PLANE.md.
    */
    $rutasTenant = function (): void {
        Route::post('/login', [AuthTenantController::class, 'store'])->middleware('throttle:login')->name('login');
        Route::post('/auth/google', [AuthTenantController::class, 'google'])->middleware('throttle:login')->name('auth.google');
        Route::post('/activar', [AuthTenantController::class, 'activar'])->middleware('throttle:login')->name('activar');

        // Marca pública (branding): nombre + logo del estudio para la pantalla de
        // acceso (sin auth). Con throttle para mitigar sondeo de slugs.
        Route::get('/marca', [MarcaEstudioController::class, 'mostrar'])->middleware('throttle:60,1')->name('marca');

        Route::middleware(['estudio.auth', 'throttle:tenant'])->group(function (): void {
            Route::get('/yo', [AuthTenantController::class, 'yo'])->name('yo');
            Route::post('/logout', [AuthTenantController::class, 'destroy'])->name('logout');

            // Autoservicio del miembro: opera solo sobre su propia persona (sin
            // permisos de staff). Resuelve la persona del usuario autenticado.
            Route::get('/mi/perfil', [MiTenantController::class, 'perfil'])->name('mi.perfil');
            Route::get('/mi/agenda', [MiTenantController::class, 'agenda'])->name('mi.agenda');
            Route::post('/mi/reservas', [MiTenantController::class, 'reservar'])->name('mi.reservas.store');
            Route::post('/mi/reservas/{reserva}/cancelar', [MiTenantController::class, 'cancelar'])->name('mi.reservas.cancelar');

            // Invitación de personal (crea usuario tenant-local con rol + activación).
            Route::post('/usuarios/invitar', [UsuariosTenantController::class, 'invitar'])->middleware('puede:usuarios.invitar')->name('usuarios.invitar');
            Route::get('/instructores', [UsuariosTenantController::class, 'instructores'])->middleware('puede:agenda.gestionar')->name('instructores.index');

            // Operación tenant-local: alta de alumnos (data plane del estudio).
            Route::get('/miembros', [MiembrosTenantController::class, 'index'])->middleware('puede:miembros.ver')->name('miembros.index');
            Route::post('/miembros', [MiembrosTenantController::class, 'store'])->middleware('puede:miembros.gestionar')->name('miembros.store');

            // Facturación SaaS del estudio (control plane; separada de pagos de alumnos).
            Route::get('/facturacion', [FacturacionController::class, 'show'])->middleware('puede:facturacion.ver')->name('facturacion');

            // Onboarding (guardar y continuar) y publicación en el directorio.
            Route::get('/onboarding', [OnboardingController::class, 'show'])->middleware('puede:estudio.gestionar')->name('onboarding.show');
            Route::put('/onboarding', [OnboardingController::class, 'guardar'])->middleware('puede:estudio.gestionar')->name('onboarding.guardar');
            Route::put('/publicacion', [OnboardingController::class, 'publicacion'])->middleware('puede:estudio.gestionar')->name('publicacion');

            // Logo del estudio (branding): lo gestiona el administrador.
            Route::post('/marca/logo', [MarcaEstudioController::class, 'subirLogo'])->middleware('puede:estudio.gestionar')->name('marca.logo.store');
            Route::delete('/marca/logo', [MarcaEstudioController::class, 'eliminarLogo'])->middleware('puede:estudio.gestionar')->name('marca.logo.destroy');

            // Documentos: el admin define tipos requeridos; se cargan por persona y
            // el staff los valida (tenant-local, aislado).
            Route::get('/tipos-documento', [TiposDocumentoController::class, 'index'])->middleware('puede:miembros.ver')->name('tipos-documento.index');
            Route::post('/tipos-documento', [TiposDocumentoController::class, 'store'])->middleware('puede:documentos.gestionar')->name('tipos-documento.store');
            Route::put('/tipos-documento/{tipo}', [TiposDocumentoController::class, 'update'])->middleware('puede:documentos.gestionar')->name('tipos-documento.update');
            Route::get('/documentos', [DocumentosController::class, 'index'])->middleware('puede:miembros.ver')->name('documentos.index');
            Route::post('/documentos', [DocumentosController::class, 'subir'])->middleware('puede:documentos.subir')->name('documentos.subir');
            Route::get('/documentos/{documento}', [DocumentosController::class, 'ver'])->middleware('puede:miembros.ver')->name('documentos.ver');
            Route::post('/documentos/{documento}/validar', [DocumentosController::class, 'validar'])->middleware('puede:documentos.gestionar')->name('documentos.validar');

            // Formularios dinámicos: el admin define formularios/campos; miembros e
            // instructores responden (validación dinámica). Tenant-local.
            Route::get('/formularios', [FormulariosController::class, 'index'])->middleware('puede:formularios.responder')->name('formularios.index');
            Route::post('/formularios', [FormulariosController::class, 'store'])->middleware('puede:formularios.gestionar')->name('formularios.store');
            Route::post('/formularios/{formulario}/campos', [FormulariosController::class, 'agregarCampo'])->middleware('puede:formularios.gestionar')->name('formularios.campos');
            Route::get('/formularios/{formulario}/respuestas', [RespuestasFormularioController::class, 'index'])->middleware('puede:formularios.gestionar')->name('formularios.respuestas.index');
            Route::post('/formularios/{formulario}/respuestas', [RespuestasFormularioController::class, 'store'])->middleware('puede:formularios.responder')->name('formularios.respuestas.store');

            // Catálogo del estudio (data plane del tenant): Programa → Actividad →
            // Nivel/Oferta. Primer módulo operativo migrado a la BD del tenant.
            Route::get('/programas', [CatalogoTenantController::class, 'programas'])->middleware('puede:catalogo.ver')->name('programas.index');
            Route::post('/programas', [CatalogoTenantController::class, 'crearPrograma'])->middleware('puede:catalogo.gestionar')->name('programas.store');
            Route::post('/programas/{programa}/actividades', [CatalogoTenantController::class, 'crearActividad'])->middleware('puede:catalogo.gestionar')->name('actividades.store');
            Route::post('/actividades/{actividad}/niveles', [CatalogoTenantController::class, 'crearNivel'])->middleware('puede:catalogo.gestionar')->name('niveles.store');
            Route::post('/actividades/{actividad}/ofertas', [CatalogoTenantController::class, 'crearOferta'])->middleware('puede:catalogo.gestionar')->name('ofertas.store');
            Route::get('/ofertas', [CatalogoTenantController::class, 'ofertas'])->middleware('puede:catalogo.ver')->name('ofertas.index');

            // Estructura del estudio (data plane del tenant): Organización → Sucursal.
            Route::get('/organizaciones', [OrganizacionesTenantController::class, 'organizaciones'])->middleware('puede:organizaciones.ver')->name('organizaciones.index');
            Route::post('/organizaciones', [OrganizacionesTenantController::class, 'crearOrganizacion'])->middleware('puede:organizaciones.gestionar')->name('organizaciones.store');
            Route::post('/organizaciones/{organizacion}/sucursales', [OrganizacionesTenantController::class, 'crearSucursal'])->middleware('puede:sucursales.gestionar')->name('sucursales.store');
            Route::get('/sucursales', [OrganizacionesTenantController::class, 'sucursales'])->middleware('puede:sucursales.ver')->name('sucursales.index');

            // Agenda (data plane del tenant): materializa una Oferta en una Sucursal
            // a una hora concreta. La hora local (zona de la sucursal) se guarda en UTC
            // con snapshot de zona.
            Route::get('/sesiones', [AgendaTenantController::class, 'sesiones'])->middleware('puede:agenda.ver')->name('sesiones.index');
            Route::post('/sesiones', [AgendaTenantController::class, 'crearSesion'])->middleware('puede:agenda.gestionar')->name('sesiones.store');
            Route::post('/sesiones/{sesion}/cancelar', [AgendaTenantController::class, 'cancelar'])->middleware('puede:agenda.gestionar')->name('sesiones.cancelar');
            Route::put('/sesiones/{sesion}/instructor', [AgendaTenantController::class, 'asignarInstructor'])->middleware('puede:agenda.gestionar')->name('sesiones.instructor');

            // Membresias (data plane del tenant): producto comercial → acuerdo →
            // derecho (entitlement) + ledger de creditos. El saldo se deriva del
            // ledger. La venta y las mutaciones del ledger son concurrency-safe.
            Route::get('/productos', [MembresiasTenantController::class, 'productos'])->middleware('puede:productos.ver')->name('productos.index');
            Route::post('/productos', [MembresiasTenantController::class, 'crearProducto'])->middleware('puede:productos.gestionar')->name('productos.store');
            Route::post('/acuerdos', [MembresiasTenantController::class, 'vender'])->middleware('puede:membresias.gestionar')->name('acuerdos.store');
            Route::get('/miembros/{persona}/derechos', [MembresiasTenantController::class, 'derechos'])->middleware('puede:derechos.ver')->name('miembros.derechos.index');
            Route::post('/derechos/{derecho}/topups', [MembresiasTenantController::class, 'topUp'])->middleware('puede:membresias.gestionar')->name('derechos.topups.store');

            // Creditos (data plane del tenant): consumo directo y retenciones (holds)
            // con confirmar/liberar/perder. Concurrencia protegida (lockForUpdate).
            Route::post('/derechos/{derecho}/consumos', [CreditosTenantController::class, 'consumir'])->middleware('puede:creditos.gestionar')->name('derechos.consumos.store');
            Route::post('/derechos/{derecho}/retenciones', [CreditosTenantController::class, 'retener'])->middleware('puede:creditos.gestionar')->name('derechos.retenciones.store');
            Route::post('/retenciones/{retencion}/confirmar', [CreditosTenantController::class, 'confirmar'])->middleware('puede:creditos.gestionar')->name('retenciones.confirmar');
            Route::post('/retenciones/{retencion}/liberar', [CreditosTenantController::class, 'liberar'])->middleware('puede:creditos.gestionar')->name('retenciones.liberar');
            Route::post('/retenciones/{retencion}/perder', [CreditosTenantController::class, 'perder'])->middleware('puede:creditos.gestionar')->name('retenciones.perder');

            // Reservas (booking) del tenant: motor transaccional sobre una sesion,
            // con lista de espera y politica de cancelacion por hold. La asistencia
            // liquida la retencion (presente consume, ausente pierde).
            Route::get('/sesiones/{sesion}/reservas', [ReservasTenantController::class, 'index'])->middleware('puede:reservas.ver')->name('sesiones.reservas.index');
            Route::post('/sesiones/{sesion}/reservas/preview', [ReservasTenantController::class, 'preview'])->middleware('puede:reservas.ver')->name('sesiones.reservas.preview');
            Route::post('/sesiones/{sesion}/reservas', [ReservasTenantController::class, 'reservar'])->middleware('puede:reservas.gestionar')->name('sesiones.reservas.store');
            Route::post('/reservas/{reserva}/cancelar', [ReservasTenantController::class, 'cancelar'])->middleware('puede:reservas.gestionar')->name('reservas.cancelar');
            Route::post('/reservas/{reserva}/asistencia', [AsistenciaTenantController::class, 'marcar'])->middleware('puede:asistencia.marcar')->name('reservas.asistencia.store');

            // Ordenes (comercio del tenant): orden pendiente (precio congelado) →
            // liquidacion manual/ventanilla → fulfillment (concesion de derechos). El
            // cobro con pasarela real es un modulo posterior (cuando haya llaves).
            Route::get('/ordenes', [OrdenesTenantController::class, 'index'])->middleware('puede:ordenes.ver')->name('ordenes.index');
            Route::post('/ordenes', [OrdenesTenantController::class, 'crear'])->middleware('puede:ordenes.gestionar')->name('ordenes.store');
            Route::get('/ordenes/{orden}', [OrdenesTenantController::class, 'show'])->middleware('puede:ordenes.ver')->name('ordenes.show');
            Route::post('/ordenes/{orden}/liquidar', [OrdenesTenantController::class, 'liquidar'])->middleware('puede:ordenes.gestionar')->name('ordenes.liquidar');
            // Cobro en linea con la pasarela del estudio (asincrono -> pendiente +
            // checkout; el webhook confirma). Listo para activarse al cargar llaves.
            Route::post('/ordenes/{orden}/cobrar', [OrdenesTenantController::class, 'cobrar'])->middleware('puede:ordenes.gestionar')->name('ordenes.cobrar');

            // Pasarelas de pago del estudio: el propietario conecta sus llaves
            // (cifradas, nunca expuestas). El cobro en linea real corre cuando el
            // estudio carga sus llaves. Solo propietario (pagos.configurar).
            Route::get('/pasarelas', [PasarelasTenantController::class, 'index'])->middleware('puede:pagos.configurar')->name('pasarelas.index');
            Route::put('/pasarelas/{proveedor}', [PasarelasTenantController::class, 'upsert'])->middleware('puede:pagos.configurar')->name('pasarelas.upsert');

            // Integraciones de bienestar (Wellhub / TotalPass): el propietario conecta
            // llaves (cifradas); el staff valida check-ins de esos usuarios en clases
            // (sin consumir creditos del estudio).
            Route::get('/integraciones', [IntegracionesTenantController::class, 'index'])->middleware('puede:integraciones.configurar')->name('integraciones.index');
            Route::put('/integraciones/{proveedor}', [IntegracionesTenantController::class, 'upsert'])->middleware('puede:integraciones.configurar')->name('integraciones.upsert');
            Route::post('/checkins', [CheckinsTenantController::class, 'registrar'])->middleware('puede:checkins.registrar')->name('checkins.store');
            Route::get('/sesiones/{sesion}/checkins', [CheckinsTenantController::class, 'index'])->middleware('puede:checkins.registrar')->name('sesiones.checkins.index');
        });
    };

    // Acceso por ruta: /api/v1/app/{estudio}/...
    Route::prefix('app/{estudio}')->middleware('estudio.resolver')->name('api.v1.app.')->group($rutasTenant);

    // Acceso por subdominio: {slug}.turnouno.com/api/v1/... (mismo comportamiento).
    Route::domain('{estudio}.'.config('turnouno.dominio_base'))
        ->middleware('estudio.resolver')
        ->name('api.v1.sub.')
        ->group($rutasTenant);

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
