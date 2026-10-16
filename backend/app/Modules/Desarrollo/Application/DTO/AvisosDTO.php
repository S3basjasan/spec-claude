<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** Avisos informativos de una generación: omisiones, competencias sin evaluar y colaboradores excluidos. */
final class AvisosDTO
{
    /**
     * @param  list<array{colaborador_id: int, nombre: string, motivo: string}>  $excluidos
     */
    public function __construct(
        public readonly int $competenciasOmitidas,
        public readonly int $sinEvaluacion,
        public readonly array $excluidos,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'competencias_omitidas' => $this->competenciasOmitidas,
            'sin_evaluacion' => $this->sinEvaluacion,
            'excluidos' => $this->excluidos,
        ];
    }
}
