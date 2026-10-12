<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\RegistroPreguntasDTO;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTienePreguntas;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;

final class CrearPreguntas
{
    public function __construct(private readonly PreguntaRepositoryInterface $registros) {}

    /**
     * @param  array<int, string>  $preguntas  Exactamente cuatro textos; el orden es la posición.
     *
     * @throws CompetenciaNoDisponible
     * @throws CompetenciaYaTienePreguntas
     */
    public function ejecutar(int $competenciaId, array $preguntas, ?int $actorId = null): RegistroPreguntasDTO
    {
        $recortadas = array_map(fn (string $texto) => trim($texto), array_values($preguntas));

        return RegistroPreguntasDTO::desde($this->registros->crear($competenciaId, $recortadas, $actorId));
    }
}
