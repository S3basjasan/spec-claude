<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Ya existe un plan no Cerrado del mismo tipo para el colaborador (FR-008). */
final class PlanDuplicadoPorTipo extends ErrorDePdi
{
    public function __construct(string $codigoExistente, int $idExistente)
    {
        parent::__construct(
            "El colaborador ya tiene un plan abierto de este tipo ({$codigoExistente}).",
            'plan_duplicado',
            'tipo',
            [],
            ['plan_existente' => ['id' => $idExistente, 'codigo' => $codigoExistente]],
        );
    }
}
