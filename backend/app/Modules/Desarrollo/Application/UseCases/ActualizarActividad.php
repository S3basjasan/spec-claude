<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Exceptions\ActividadNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\DepartamentoInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\LugarInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\PlanCerradoParaActividad;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;

/** Cambia solo el lugar o enlace y el departamento, mientras el plan no esté Cerrado. */
final class ActualizarActividad
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly DepartamentosInterface $departamentos,
        private readonly ReglasDeActividad $reglas,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ActividadNoEncontrada
     * @throws PlanCerradoParaActividad
     * @throws LugarInvalido
     * @throws DepartamentoInvalido
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $id, string $lugarEnlace, ?int $departamentoId): array
    {
        $actividad = $this->actividades->obtener($tenantId, $id) ?? throw new ActividadNoEncontrada;
        $accion = $this->acciones->obtener($tenantId, $actividad->accionId) ?? throw new ActividadNoEncontrada;

        if (! $this->reglas->puedeEditar($accion->planEstado)) {
            throw new PlanCerradoParaActividad;
        }
        $this->reglas->validarLugar($accion->tipo, $lugarEnlace);
        if ($departamentoId !== null && $departamentoId !== $actividad->departamentoId && ! $this->departamentos->estaActivo($departamentoId)) {
            throw new DepartamentoInvalido;
        }

        $this->actividades->actualizar($id, trim($lugarEnlace), $departamentoId, $usuarioId);

        return $this->armador->detalle($tenantId, $usuarioId, $this->actividades->obtener($tenantId, $id) ?? $actividad, $accion);
    }
}
