<?php

namespace App\Modules\Desarrollo\Application\DTO;

use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;

/** Diagnóstico calculado (todavía sin guardar) de un colaborador frente a un puesto objetivo. */
final class DiagnosticoDTO
{
    /**
     * @param  list<ResultadoDeAdecuacion>  $resultados
     * @param  list<array{tipo: string, mensaje: string, cantidad: int}>  $avisos
     * @param  list<string>  $fuentesNoDisponibles
     */
    public function __construct(
        public readonly int $colaboradorId,
        public readonly ?int $puestoActualId,
        public readonly int $puestoObjetivoId,
        public readonly string $puestoObjetivoNombre,
        public readonly ?int $inscripcionId,
        public readonly ?int $nivelObjetivoId,
        public readonly ?float $experienciaRequeridaAnios,
        public readonly ?float $experienciaDeLaPersona,
        public readonly string $tipoCambio,
        public readonly array $resultados,
        public readonly array $avisos,
        public readonly array $fuentesNoDisponibles,
    ) {}
}
