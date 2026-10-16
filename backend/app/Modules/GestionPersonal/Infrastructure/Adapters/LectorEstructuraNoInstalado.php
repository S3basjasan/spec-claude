<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/** Estructura aún no publica posiciones ni unidades para Personal: toda lectura es «no instalado». */
final class LectorEstructuraNoInstalado implements LectorEstructuraPort
{
    public function posiciones(array $posicionIds): Lectura
    {
        return Lectura::noInstalado();
    }

    public function posicionesPorPuesto(string $texto): Lectura
    {
        return Lectura::noInstalado();
    }

    public function posicionesPorUnidad(int $unidadId): Lectura
    {
        return Lectura::noInstalado();
    }

    public function unidades(): Lectura
    {
        return Lectura::noInstalado();
    }
}
