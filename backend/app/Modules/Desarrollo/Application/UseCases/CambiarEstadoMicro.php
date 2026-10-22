<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ValidadorDeCompetenciasMicro;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeMicrocontenido;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;

/** Publica o archiva un micro-contenido aplicando las transiciones y los requisitos de publicación. */
final class CambiarEstadoMicro
{
    public function __construct(
        private readonly MicrocontenidoRepositoryInterface $repositorio,
        private readonly ValidadorDeCompetenciasMicro $competencias,
        private readonly ReglasDeMicrocontenido $reglas,
    ) {}

    public function ejecutar(int $id, EstadoMicro $nuevo, int $actorId): void
    {
        $micro = $this->repositorio->obtener($id) ?? throw new MicroNoEncontrado;

        $this->reglas->validarTransicion($micro->estado, $nuevo);

        if ($nuevo === EstadoMicro::Publicado) {
            $this->reglas->validarPublicable(
                $micro->titulo,
                $micro->duracionMinutos,
                $micro->tipo,
                $micro->url,
                count($micro->preguntas),
                $this->competencias->contarActivas($micro->competenciaIds),
            );
        }

        $this->repositorio->cambiarEstado($id, $nuevo, $actorId);
    }
}
