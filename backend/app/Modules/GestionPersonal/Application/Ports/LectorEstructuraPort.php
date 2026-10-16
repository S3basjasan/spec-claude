<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/** Lectura de puestos, posiciones y unidades (Estructura Organizacional). */
interface LectorEstructuraPort
{
    /**
     * @param  list<int>  $posicionIds
     * @return Lectura Datos: [posicion_id => {puesto: string, unidad_id: int|null, unidad: string|null}]
     */
    public function posiciones(array $posicionIds): Lectura;

    /** @return Lectura Datos: list<int> ids de posición cuyo puesto contiene el texto */
    public function posicionesPorPuesto(string $texto): Lectura;

    /** @return Lectura Datos: list<int> ids de posición de la unidad */
    public function posicionesPorUnidad(int $unidadId): Lectura;

    /** @return Lectura Datos: list<array{id: int, nombre: string}> */
    public function unidades(): Lectura;
}
