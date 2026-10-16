<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use Throwable;

/** Lee posiciones, puestos y unidades por el contrato público de Estructura. Un fallo real es `noDisponible`. */
final class EstructuraFacadeLector implements LectorEstructuraPort
{
    public function posiciones(array $posicionIds): Lectura
    {
        return $this->proteger(function () use ($posicionIds) {
            $salida = [];
            foreach (EstructuraFacade::getPosicionesPorIds($posicionIds) as $id => $posicion) {
                $salida[(int) $id] = [
                    'puesto' => $posicion['puesto'],
                    'unidad_id' => $posicion['unidad_id'],
                    'unidad' => $posicion['unidad'],
                    'jefatura' => $posicion['jefatura'],
                ];
            }

            return $salida;
        });
    }

    public function posicionesPorPuesto(string $texto): Lectura
    {
        return $this->proteger(fn () => array_map('intval', EstructuraFacade::getPosicionesPorPuesto($texto)));
    }

    public function posicionesPorUnidad(int $unidadId): Lectura
    {
        return $this->proteger(fn () => array_map('intval', EstructuraFacade::getPosicionesPorUnidad($unidadId)));
    }

    public function unidades(): Lectura
    {
        return $this->proteger(fn () => array_map(
            fn (array $unidad) => ['id' => (int) $unidad['id'], 'nombre' => (string) $unidad['nombre']],
            EstructuraFacade::getUnidadesActivas(),
        ));
    }

    private function proteger(callable $lectura): Lectura
    {
        try {
            return Lectura::disponible($lectura());
        } catch (Throwable) {
            return Lectura::noDisponible();
        }
    }
}
