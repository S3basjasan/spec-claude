<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\DesarrolloDTO;
use App\Modules\GestionPersonal\Domain\Entities\ExperienciaAnterior;
use App\Modules\GestionPersonal\Domain\Entities\FormacionAcademica;
use App\Modules\GestionPersonal\Domain\Entities\Movilidad;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;

/**
 * Compone la representación `desarrollo` y las filas individuales de las respuestas.
 */
final class ArmadorDeDesarrollo
{
    public function __construct(private readonly DesarrolloRepositoryInterface $desarrollo) {}

    public function armar(int $colaboradorId, ContextoDeTenant $contexto, bool $editable): DesarrolloDTO
    {
        $tenantId = $contexto->tenantId;
        $movilidad = $this->desarrollo->movilidad($tenantId, $colaboradorId);

        return new DesarrolloDTO([
            'colaborador_id' => $colaboradorId,
            'hoy' => $contexto->hoy(),
            'formaciones' => array_map($this->formacion(...), $this->desarrollo->formaciones($tenantId, $colaboradorId)),
            'experiencias' => array_map($this->experiencia(...), $this->desarrollo->experiencias($tenantId, $colaboradorId)),
            'movilidad' => $this->movilidad($movilidad ?? new Movilidad($colaboradorId, 'NoInformada', null, 1, null, null)),
            'acciones' => ['editar' => $editable],
        ]);
    }

    /** @return array<string, mixed> */
    public function formacion(FormacionAcademica $f): array
    {
        return [
            'id' => $f->id,
            'nivel' => $f->nivel,
            'titulo_programa' => $f->tituloPrograma,
            'institucion' => $f->institucion,
            'estado' => $f->estado,
            'fecha_inicio' => $f->fechaInicio,
            'fecha_finalizacion' => $f->fechaFinalizacion,
            'observaciones' => $f->observaciones,
            'version' => $f->version,
            'created_at' => $f->createdAt,
            'updated_at' => $f->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function experiencia(ExperienciaAnterior $e): array
    {
        return [
            'id' => $e->id,
            'organizacion' => $e->organizacion,
            'cargo' => $e->cargo,
            'fecha_inicio' => $e->fechaInicio,
            'fecha_finalizacion' => $e->fechaFinalizacion,
            'funciones' => $e->funciones,
            'observaciones' => $e->observaciones,
            'version' => $e->version,
            'created_at' => $e->createdAt,
            'updated_at' => $e->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function movilidad(Movilidad $m): array
    {
        return [
            'estado' => $m->estado,
            'observaciones' => $m->observaciones,
            'actualizada_at' => $m->actualizadaAt,
            'version' => $m->version,
        ];
    }
}
