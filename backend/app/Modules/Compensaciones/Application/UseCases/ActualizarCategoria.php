<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\DTOs\DatosCategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Services\ArmadorDeCategoriaDTO;
use App\Modules\Compensaciones\Application\Services\AuditoriaDeCategoria;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaModificadaPorOtro;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\Compensaciones\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\Exceptions\NombreDeCategoriaRepetido;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;

final class ActualizarCategoria
{
    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly CompetenciasPort $competencias,
        private readonly AuditoriaDeCategoria $auditoria,
        private readonly ArmadorDeCategoriaDTO $armador,
    ) {}

    /**
     * Reemplaza los datos y la lista completa de indicadores. El estado se conserva. Un guardado sin cambios
     * reales se acepta sin alterar la fecha, el autor ni el historial.
     *
     * @throws CategoriaNoEncontrada
     * @throws CategoriaModificadaPorOtro
     * @throws DatosDeCategoriaInvalidos
     * @throws CompetenciaNoDisponible
     * @throws NombreDeCategoriaRepetido
     */
    public function ejecutar(int $id, string $versionLeida, DatosCategoriaDTO $datos, int $actorId): CategoriaDTO
    {
        $resultado = $this->categorias->enTransaccion(function () use ($id, $versionLeida, $datos, $actorId) {
            $actual = $this->categorias->porId($id) ?? throw new CategoriaNoEncontrada;

            if (! $actual->coincideVersion($versionLeida)) {
                throw new CategoriaModificadaPorOtro;
            }

            $propuesta = $datos->comoPropuesta($actual->estado);

            if ($propuesta->competenciaId !== $actual->competenciaId) {
                $this->exigirCompetenciaActiva($propuesta->competenciaId);
            }

            if ($this->categorias->existeNombre($propuesta->nombre->normalizado(), $actual->id)) {
                throw new NombreDeCategoriaRepetido;
            }

            if ($actual->diferenciasCon($propuesta) === []) {
                return $actual;
            }

            $guardada = $this->categorias->actualizarSiVigente($actual, $propuesta, $actorId)
                ?? throw new CategoriaModificadaPorOtro;

            $this->auditoria->alModificar($actual, $guardada, $actorId);

            return $guardada;
        });

        return $this->armador->uno($resultado);
    }

    private function exigirCompetenciaActiva(int $competenciaId): void
    {
        $competencia = $this->competencias->porId($competenciaId) ?? throw CompetenciaNoDisponible::inexistente();

        if ($competencia['estado'] !== 'Activo') {
            throw CompetenciaNoDisponible::inactiva();
        }
    }
}
