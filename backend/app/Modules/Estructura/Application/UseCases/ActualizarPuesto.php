<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PuestoDTO;
use App\Modules\Estructura\Domain\Exceptions\CodigoDePuestoNoEditable;
use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Exceptions\PuestoModificado;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ReglasDeCompetenciasDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;

final class ActualizarPuesto
{
    public function __construct(
        private readonly PuestoRepositoryInterface $puestos,
        private readonly ReglasDeCompetenciasDePuesto $reglas,
    ) {}

    /**
     * Reemplaza el contenido de un puesto (activo o inactivo) si hay cambios reales. No cambia el código ni el estado.
     *
     * @param  string|null  $codigoEnviado  Código que llegó en la petición, si lo hubo: debe ser el del puesto.
     *
     * @throws PuestoNoEncontrado
     * @throws PuestoModificado
     * @throws CodigoDePuestoNoEditable
     * @throws CompetenciasDePuestoInvalidas
     * @throws NombreDePuestoDuplicado
     */
    public function ejecutar(int $id, int $versionEsperada, DatosPuesto $datos, ?int $actorId = null, ?string $codigoEnviado = null): PuestoDTO
    {
        $actual = $this->puestos->obtener($id) ?? throw new PuestoNoEncontrado;

        if ($codigoEnviado !== null && $codigoEnviado !== '' && $codigoEnviado !== $actual->codigo) {
            throw new CodigoDePuestoNoEditable;
        }

        // Las competencias inactivas que el puesto ya tenía solo pueden mantenerse con el mismo grado o quitarse.
        $ids = array_map(fn (AsignacionDeCompetencia $a) => $a->competenciaId, $datos->competencias);
        $this->reglas->validar($datos->competencias, $this->puestos->competenciasPorIds($ids), $actual->competencias);

        return PuestoDTO::desde($this->puestos->actualizar($id, $versionEsperada, $datos, $actorId));
    }
}
