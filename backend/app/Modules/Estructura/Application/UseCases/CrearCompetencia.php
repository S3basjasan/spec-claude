<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Application\Services\ValidadorDeEscala;
use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class CrearCompetencia
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly ValidadorDeEscala $escalas,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Crea la competencia completa (datos, escala, grados, comportamientos y preguntas) en una sola operación.
     *
     * @throws NombreDuplicado
     * @throws EscalaNoEncontrada
     * @throws CategoriasInvalidas
     */
    public function ejecutar(DatosCompetenciaDTO $datos, ?int $actorId = null): CompetenciaDTO
    {
        $categorias = $this->escalas->categoriasValidas($datos->tipoGradoId, $datos->categorias);

        $competencia = $this->competencias->crear(
            trim($datos->nombre),
            trim($datos->descripcion),
            TipoCompetencia::from($datos->tipo),
            $datos->tipoGradoId,
            $categorias,
            array_map('trim', array_values($datos->grados)),
            array_map(fn (array $textos) => array_map('trim', array_values($textos)), array_values($datos->comportamientos ?? [])),
            array_map('trim', array_values($datos->preguntas ?? [])),
            $actorId,
        );

        return CompetenciaDTO::desde($competencia, $this->auditoria->para($competencia));
    }
}
