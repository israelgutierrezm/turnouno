<?php

declare(strict_types=1);

arch('toda la aplicacion declara strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no quedan helpers de depuracion en el codigo')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();

arch('preset de seguridad')
    ->preset()
    ->security();
