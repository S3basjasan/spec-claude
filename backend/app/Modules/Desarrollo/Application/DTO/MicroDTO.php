<?php

namespace App\Modules\Desarrollo\Application\DTO;

use App\Modules\Desarrollo\Domain\Entities\Microcontenido;

/**
 * Micro-contenido listo para presentar: la entidad más las competencias con su nombre, los nombres de auditoría
 * (solo Administrador) y el progreso del colaborador que lo consulta (solo colaborador).
 */
final class MicroDTO
{
    /**
     * @param  array<int, array{id: int, codigo: string, nombre: string, estado: string}>  $competencias
     * @param  array{estado: string, posicion_segundos: int|null, fecha_inicio: string, fecha_completado: string|null, ultimo_puntaje: array{correctas: int, total: int}|null}|null  $progreso
     */
    public function __construct(
        public readonly Microcontenido $micro,
        public readonly array $competencias,
        public readonly bool $esAdmin,
        public readonly ?string $creadoPor = null,
        public readonly ?string $modificadoPor = null,
        public readonly ?array $progreso = null,
    ) {}
}
