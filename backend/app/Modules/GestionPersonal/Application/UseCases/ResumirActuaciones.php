<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;

/** Conteos para que Talento Humano vea de un vistazo pendientes, programados e incidencias. */
final class ResumirActuaciones
{
    public function __construct(private readonly ActuacionRepositoryInterface $actuaciones) {}

    /** @return array{pendientes: int, en_revision: int, programados: int, aplicados: int, incidencias: int} */
    public function ejecutar(ContextoDeTenant $contexto): array
    {
        return $this->actuaciones->resumen($contexto->tenantId);
    }
}
