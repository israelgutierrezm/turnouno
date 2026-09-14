<?php

declare(strict_types=1);

use App\Modules\Hogares\Http\Controllers\HogarController;
use App\Modules\Identity\Http\Controllers\Auth\SessionController;
use App\Modules\Identity\Http\Controllers\Auth\TokenController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\UsuarioController;
use App\Modules\Organizaciones\Http\Controllers\OrganizacionController;
use App\Modules\Organizaciones\Http\Controllers\PersonalSucursalController;
use App\Modules\Organizaciones\Http\Controllers\SucursalController;
use App\Modules\Personas\Http\Controllers\DependientePersonaController;
use App\Modules\Personas\Http\Controllers\PerfilPersonaController;
use App\Modules\Personas\Http\Controllers\PersonaController;
use App\Modules\Platform\Http\Controllers\HealthController;
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
