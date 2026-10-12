<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\RegistroComportamientosDTO;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;

final class ActualizarComportamientos
{
    public function __construct(private readonly ComportamientoRepositoryInterface $registros) {}

    /**
     * @param  array<string, array<int, string>>  $comportamientos  Textos por letra A–D.
     *
     * @throws RegistroNoEncontrado
     * @throws RegistroModificado
     */
    public function ejecutar(int $competenciaId, int $versionEsperada, array $comportamientos, ?int $actorId = null): RegistroComportamientosDTO
    {
        $recortados = array_map(fn (array $textos) => array_map(fn (string $texto) => trim($texto), array_values($textos)), $comportamientos);

        return RegistroComportamientosDTO::desde(
            $this->registros->actualizar($competenciaId, $versionEsperada, $recortados, $actorId),
        );
    }
}
