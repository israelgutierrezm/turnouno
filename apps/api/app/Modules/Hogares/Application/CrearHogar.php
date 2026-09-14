<?php

declare(strict_types=1);

namespace App\Modules\Hogares\Application;

use App\Modules\Hogares\Models\Hogar;

class CrearHogar
{
    public function ejecutar(string $nombre): Hogar
    {
        return Hogar::create(['nombre' => $nombre]);
    }
}
