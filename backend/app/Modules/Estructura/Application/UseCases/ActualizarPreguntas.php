<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\RegistroPreguntasDTO;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;

final class ActualizarPreguntas
{
    public function __construct(private readonly PreguntaRepositoryInterface $registros) {}

    /**
     * Sin cambios reales el registro queda igual (misma versión y auditoría). Permitido con la competencia inactiva.
     *
     * @param  array<int, string>  $preguntas  Exactamente cuatro textos; el orden es la posición.
     *
     * @throws PreguntasNoEncontradas
     * @throws RegistroModificado
     */
    public function ejecutar(int $competenciaId, int $versionEsperada, array $preguntas, ?int $actorId = null): RegistroPreguntasDTO
    {
        $recortadas = array_map(fn (string $texto) => trim($texto), array_values($preguntas));

        return RegistroPreguntasDTO::desde($this->registros->actualizar($competenciaId, $versionEsperada, $recortadas, $actorId));
    }
}
