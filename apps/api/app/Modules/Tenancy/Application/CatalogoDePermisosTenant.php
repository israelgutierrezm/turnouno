<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

/**
 * Catálogo de permisos y roles del data plane (por tenant). Los roles viven en la
 * BD del estudio: la misma persona puede tener roles distintos en dos estudios.
 * `propietario` (`['*']`) tiene todos los permisos.
 */
class CatalogoDePermisosTenant
{
    /**
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            'propietario' => ['*'],
            'admin' => [
                'estudio.gestionar', 'miembros.gestionar', 'miembros.ver',
                'documentos.gestionar', 'documentos.subir',
                'formularios.gestionar', 'formularios.responder',
                'catalogo.ver', 'catalogo.gestionar',
                'organizaciones.ver', 'organizaciones.gestionar',
                'sucursales.ver', 'sucursales.gestionar',
                'agenda.ver', 'agenda.gestionar',
                'productos.ver', 'productos.gestionar',
                'membresias.gestionar', 'creditos.gestionar', 'derechos.ver',
                'reservas.ver', 'reservas.gestionar', 'asistencia.marcar', 'checkins.registrar',
                'ordenes.ver', 'ordenes.gestionar',
                'facturacion.ver', 'usuarios.invitar', 'auditoria.ver',
            ],
            'recepcionista' => [
                'miembros.gestionar', 'miembros.ver', 'documentos.subir', 'formularios.responder',
                'catalogo.ver', 'organizaciones.ver', 'sucursales.ver', 'agenda.ver',
                'productos.ver', 'membresias.gestionar', 'creditos.gestionar', 'derechos.ver',
                'reservas.ver', 'reservas.gestionar', 'asistencia.marcar', 'checkins.registrar',
                'ordenes.ver', 'ordenes.gestionar',
            ],
            'instructor' => [
                'miembros.ver', 'documentos.subir', 'formularios.responder', 'catalogo.ver',
                'sucursales.ver', 'agenda.ver', 'derechos.ver',
                'reservas.ver', 'asistencia.marcar', 'checkins.registrar',
            ],
            'miembro' => [
                'formularios.responder',
            ],
        ];
    }

    public static function puede(string $rol, string $permiso): bool
    {
        $permisos = self::roles()[$rol] ?? [];

        return in_array('*', $permisos, true) || in_array($permiso, $permisos, true);
    }

    /**
     * Roles que el estudio puede asignar al invitar personal (nunca `propietario`).
     *
     * @return list<string>
     */
    public static function rolesAsignables(): array
    {
        return ['admin', 'recepcionista', 'instructor', 'miembro'];
    }
}
