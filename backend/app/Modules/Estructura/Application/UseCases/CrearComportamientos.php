<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\RegistroComportamientosDTO;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;

final class CrearComportamientos
{
    public function __construct(private readonly ComportamientoRepositoryInterface $registros) {}

    /**
     * @param  array<string, array<int, string>>  $comportamientos  Textos por letra A–D.
     *
     * @throws CompetenciaNoDisponible
     * @throws CompetenciaYaTieneRegistro
     */
    public function ejecutar(int $competenciaId, array $comportamientos, ?int $actorId = null): RegistroComportamientosDTO
    {
        return RegistroComportamientosDTO::desde(
            $this->registros->crear($competenciaId, $this->recortar($comportamientos), $actorId),
        );
    }

    /**
     * @param  array<string, array<int, string>>  $comportamientos
     * @return array<string, array<int, string>>
     */
    private function recortar(array $comportamientos): array
    {
        return array_map(fn (array $textos) => array_map(fn (string $texto) => trim($texto), array_values($textos)), $comportamientos);
    }
}
