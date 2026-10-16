<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class DetalleFormativoDTO
{
    /**
     * @param  array{id: int, codigo: string, nombre: string, descripcion: string, tipo: string, estado: string, grados: array<string, string>}  $competencia
     * @param  array<int, RecursoDTO>  $cursos
     * @param  array<int, RecursoDTO>  $microcontenidos
     * @param  array{usuario: string, fecha: string}|null  $ultimaConfiguracion
     */
    public function __construct(
        public readonly array $competencia,
        public readonly ?AreaDTO $area,
        public readonly array $cursos,
        public readonly array $microcontenidos,
        public readonly bool $esAdmin,
        public readonly int $version,
        public readonly ?array $ultimaConfiguracion,
        public readonly ?string $nivelActual,
        public readonly ?string $nivelRequerido,
    ) {}
}
