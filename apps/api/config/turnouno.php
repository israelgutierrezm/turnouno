<?php

declare(strict_types=1);

return [

    /*
    | Driver de las bases de datos por tenant (data plane). En dev/test cada tenant
    | es un archivo SQLite (aislamiento físico simple); en producción se apunta a
    | MySQL (una base por tenant) con TENANT_DB_DRIVER=mysql.
    */
    'tenant_db_driver' => env('TENANT_DB_DRIVER', 'sqlite'),

    /*
    | Días del periodo de prueba gratuito al aprovisionar un estudio.
    */
    'dias_trial' => (int) env('TRIAL_DIAS', 14),

    /*
    | Dominio base para resolver el estudio por subdominio: `{slug}.turnouno.com`.
    | Las rutas del tenant se montan además bajo este dominio (aparte del acceso
    | por ruta `/app/{estudio}`). Ajustable por entorno (p. ej. un dominio de
    | staging o `lvh.me` para desarrollo local con subdominios).
    */
    'dominio_base' => env('APP_TENANT_DOMAIN', 'turnouno.com'),

];
