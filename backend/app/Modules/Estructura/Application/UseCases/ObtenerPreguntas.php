<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\RegistroPreguntasDTO;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;

final class ObtenerPreguntas
{
    public function __construct(private readonly PreguntaRepositoryInterface $registros) {}

    /**
     * Cualquier persona autenticada puede consultar el registro, también el de una competencia inactiva.
     *
     * @throws PreguntasNoEncontradas
     */
    public function ejecutar(int $competenciaId): RegistroPreguntasDTO
    {
        $registro = $this->registros->obtener($competenciaId) ?? throw new PreguntasNoEncontradas;

        return RegistroPreguntasDTO::desde($registro);
    }
}
