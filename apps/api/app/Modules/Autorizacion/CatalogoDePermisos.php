<?php

declare(strict_types=1);

namespace App\Modules\Autorizacion;

/**
 * Catálogo canónico de permisos y el mapa de roles por defecto
 * (ver docs/AUTHORIZATION.md). Los permisos son globales; los roles se
 * aprovisionan por tenant (team = tenant, ADR-0006).
 */
class CatalogoDePermisos
{
    /**
     * @return list<string>
     */
    public static function permisos(): array
    {
        return [
            'miembros.ver', 'miembros.crear', 'miembros.editar',
            'reservas.ver', 'reservas.crear', 'reservas.cancelar',
            'asistencia.ver', 'asistencia.registrar', 'asistencia.anular',
            'pagos.ver', 'pagos.crear', 'pagos.reembolsar', 'pagos.configurar',
            'reportes.exportar', 'personal.gestionar', 'roles.gestionar',
            'organizaciones.ver', 'organizaciones.gestionar',
            'sucursales.ver', 'sucursales.gestionar',
            'catalogo.ver', 'catalogo.gestionar',
            'recursos.ver', 'recursos.gestionar',
            'productos.ver', 'productos.gestionar',
            'membresias.ver', 'membresias.gestionar',
            'agenda.ver', 'agenda.gestionar',
        ];
    }

    /**
     * Roles por defecto de un tenant. El valor `['*']` concede todos los permisos.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            'propietario' => ['*'],
            'gerente-sucursal' => [
                'miembros.ver', 'miembros.crear', 'miembros.editar',
                'reservas.ver', 'reservas.crear', 'reservas.cancelar',
                'asistencia.ver', 'asistencia.registrar', 'pagos.ver', 'pagos.crear',
                'organizaciones.ver', 'sucursales.ver', 'personal.gestionar',
                'catalogo.ver', 'catalogo.gestionar', 'recursos.ver', 'recursos.gestionar',
                'productos.ver', 'membresias.ver', 'membresias.gestionar',
                'agenda.ver', 'agenda.gestionar',
            ],
            'recepcionista' => [
                'miembros.ver', 'reservas.ver', 'reservas.crear',
                'asistencia.ver', 'asistencia.registrar', 'sucursales.ver',
                'catalogo.ver', 'recursos.ver',
                'productos.ver', 'membresias.ver', 'membresias.gestionar',
                'agenda.ver', 'pagos.ver', 'pagos.crear',
            ],
            'miembro' => ['reservas.ver', 'reservas.crear', 'reservas.cancelar'],
        ];
    }
}
