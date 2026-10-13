<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Estado de un puesto en un momento dado, con código y nombre de cada competencia: es lo que compara el
 * servicio de auditoría para decidir qué cambios se registran.
 */
final class InstantaneaDePuesto
{
    /**
     * @param  array<int, FuncionPuesto>  $funciones
     * @param  array<int, RequisitoPuesto>  $requisitos
     * @param  array<int, CompetenciaDePuesto>  $competencias
     */
    public function __construct(
        public readonly ?string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $mision,
        public readonly EstadoPuesto $estado,
        public readonly array $funciones,
        public readonly array $requisitos,
        public readonly array $competencias,
    ) {}
}
