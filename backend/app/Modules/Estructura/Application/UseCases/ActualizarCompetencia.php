<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Application\Services\ValidadorDeEscala;
use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class ActualizarCompetencia
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly ValidadorDeEscala $escalas,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Actualiza nombre, descripción, tipo, escala y los cuatro grados (y las categorías si se envían).
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     * @throws NombreDuplicado
     * @throws EscalaNoEncontrada
     * @throws CategoriasInvalidas
     */
    public function ejecutar(int $id, int $versionEsperada, DatosCompetenciaDTO $datos, ?int $actorId = null): CompetenciaDTO
    {
        $categorias = $this->escalas->categoriasValidas($datos->tipoGradoId, $datos->categorias);

        $competencia = $this->competencias->actualizar(
            $id,
            $versionEsperada,
            trim($datos->nombre),
            trim($datos->descripcion),
            TipoCompetencia::from($datos->tipo),
            $datos->tipoGradoId,
            $categorias,
            array_map('trim', array_values($datos->grados)),
            $actorId,
        );

        return CompetenciaDTO::desde($competencia, $this->auditoria->para($competencia));
    }
}
