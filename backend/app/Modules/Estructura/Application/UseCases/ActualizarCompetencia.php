<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class ActualizarCompetencia
{
    public function __construct(private readonly CompetenciaRepositoryInterface $competencias) {}

    /**
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     * @throws NombreDuplicado
     */
    public function ejecutar(int $id, int $versionEsperada, DatosCompetenciaDTO $datos, ?int $actorId = null): CompetenciaDTO
    {
        $competencia = $this->competencias->actualizar(
            $id,
            $versionEsperada,
            trim($datos->nombre),
            trim($datos->descripcion),
            TipoCompetencia::from($datos->tipo),
            $datos->grados,
            $actorId,
        );

        return CompetenciaDTO::desde($competencia);
    }
}
