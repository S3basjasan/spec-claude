<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class MiAprendizajeDTO
{
    /**
     * @param  array{completados: int, en_progreso: int, horas_acumuladas: float, certificados: int}  $resumen
     * @param  array<int, MicroDTO>  $asignados
     * @param  array<int, MicroDTO>  $completados
     * @param  array<int, string>  $motivos  micro_id => brecha|iniciado
     * @param  array<int, string>  $avisos
     */
    public function __construct(
        public readonly array $resumen,
        public readonly array $asignados,
        public readonly array $completados,
        public readonly array $motivos,
        public readonly array $avisos,
    ) {}
}
