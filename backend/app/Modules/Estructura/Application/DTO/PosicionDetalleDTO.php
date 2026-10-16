<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Entities\Posicion;

/**
 * Detalle de una posición: los datos del listado más versión, descripción del puesto, tipo de la unidad, subordinadas
 * (con su ocupante) y, solo para el Administrador, la auditoría (quién creó y modificó, con su nombre).
 */
final class PosicionDetalleDTO extends PosicionDTO
{
    /**
     * @param  array<string, mixed>  $puesto
     * @param  array<string, mixed>  $unidad
     * @param  array<string, mixed>|null  $supervisor
     * @param  array{id: int, nombre: string|null}|null  $ocupante
     * @param  array<int, array{id: int, codigo: string, puesto: string, estado: string, ocupante: array{id: int, nombre: string|null}|null}>  $subordinadas
     * @param  array<string, mixed>|null  $auditoria
     */
    public function __construct(
        int $id,
        string $codigo,
        string $estado,
        string $modalidad,
        array $puesto,
        array $unidad,
        ?array $supervisor,
        ?array $ocupante,
        int $subordinadasVigentes,
        public readonly int $version,
        public readonly array $subordinadas,
        public readonly ?array $auditoria,
    ) {
        parent::__construct($id, $codigo, $estado, $modalidad, $puesto, $unidad, $supervisor, $ocupante, $subordinadasVigentes);
    }

    /**
     * Detalle con los nombres del ocupante, de quien ocupa la posición supervisora y de los ocupantes de las subordinadas;
     * la auditoría se incluye solo cuando quien consulta es administrador.
     */
    public static function para(Posicion $posicion, NombresDeUsuariosInterface $usuarios, bool $esAdministrador): self
    {
        $ids = array_values(array_filter([
            $posicion->ocupanteId,
            $posicion->supervisor['ocupante_id'] ?? null,
            ...array_column($posicion->subordinadas, 'ocupante_id'),
            ...($esAdministrador ? [$posicion->creadoPor, $posicion->modificadoPor] : []),
        ], fn ($id) => $id !== null));
        $nombres = $ids === [] ? [] : $usuarios->nombres(array_values(array_unique($ids)));

        $usuario = fn (?int $id) => $id === null ? null : ['id' => $id, 'nombre' => $nombres[$id] ?? null];
        $base = self::campos($posicion, $nombres);

        return new self(
            id: $base['id'],
            codigo: $base['codigo'],
            estado: $base['estado'],
            modalidad: $base['modalidad'],
            puesto: $posicion->puesto,
            unidad: $posicion->unidad,
            supervisor: $posicion->supervisor === null ? null : [
                'id' => $posicion->supervisor['id'],
                'codigo' => $posicion->supervisor['codigo'],
                'nombre' => $posicion->supervisor['puesto'],
                'ocupante' => $usuario($posicion->supervisor['ocupante_id']),
            ],
            ocupante: $base['ocupante'],
            subordinadasVigentes: $posicion->subordinadasVigentes,
            version: $posicion->version,
            subordinadas: array_map(fn (array $s) => [
                'id' => $s['id'],
                'codigo' => $s['codigo'],
                'puesto' => $s['puesto'],
                'estado' => $s['estado'],
                'ocupante' => $usuario($s['ocupante_id']),
            ], $posicion->subordinadas),
            auditoria: $esAdministrador ? [
                'created_by' => $usuario($posicion->creadoPor),
                'created_at' => $posicion->creadoEn,
                'updated_by' => $usuario($posicion->modificadoPor),
                'updated_at' => $posicion->modificadoEn,
            ] : null,
        );
    }
}
