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
                'facturacion.ver', 'usuarios.invitar',
            ],
            'recepcionista' => [
                'miembros.gestionar', 'miembros.ver', 'documentos.subir', 'formularios.responder',
            ],
            'instructor' => [
                'miembros.ver', 'documentos.subir', 'formularios.responder',
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
