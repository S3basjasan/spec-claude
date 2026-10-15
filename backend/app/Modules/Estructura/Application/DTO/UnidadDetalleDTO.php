<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Entities\Unidad;

/**
 * Detalle de una unidad: los datos del listado más versión, fechas, dependientes y, solo para el Administrador,
 * la auditoría (quién creó y modificó, con su nombre).
 */
final class UnidadDetalleDTO extends UnidadDTO
{
    /**
     * @param  array{id: int, nombre: string, nivel: int}  $tipo
     * @param  array{id: int, codigo: string, nombre: string, estado: string|null}|null  $unidadPadre
     * @param  array<int, array{id: int, codigo: string, nombre: string, estado: string}>  $dependientes
     * @param  array{created_by: array{id: int, nombre: string|null}|null, created_at: string|null, updated_by: array{id: int, nombre: string|null}|null, updated_at: string|null}|null  $auditoria
     */
    public function __construct(
        int $id,
        string $codigo,
        string $nombre,
        array $tipo,
        ?array $unidadPadre,
        int $nivelArbol,
        string $estado,
        bool $tieneDependientes,
        int $dependientesActivas,
        int $posicionesVigentes,
        bool $admiteHijas,
        bool $sigueCadena,
        public readonly int $version,
        public readonly ?string $fechaCreacion,
        public readonly ?string $fechaSupresion,
        public readonly array $dependientes,
        public readonly ?array $auditoria,
    ) {
        parent::__construct(
            $id, $codigo, $nombre, $tipo, $unidadPadre, $nivelArbol, $estado,
            $tieneDependientes, $dependientesActivas, $posicionesVigentes, $admiteHijas, $sigueCadena,
        );
    }

    /**
     * Detalle con la auditoría (nombres incluidos) para las operaciones de administrador.
     */
    public static function paraAdministrador(Unidad $unidad, NombresDeUsuariosInterface $usuarios): self
    {
        $ids = array_values(array_filter([$unidad->creadoPor, $unidad->modificadoPor], fn ($id) => $id !== null));

        return self::conAuditoria($unidad, true, $usuarios->nombres($ids));
    }

    /**
     * @param  array<int, string>  $nombres  id de usuario → nombre (vacío si no se incluye la auditoría)
     */
    public static function conAuditoria(Unidad $unidad, bool $incluirAuditoria, array $nombres = []): self
    {
        $usuario = fn (?int $id) => $id === null ? null : ['id' => $id, 'nombre' => $nombres[$id] ?? null];

        return new self(
            ...self::campos($unidad),
            version: $unidad->version,
            fechaCreacion: $unidad->fechaCreacion,
            fechaSupresion: $unidad->fechaSupresion,
            dependientes: array_map(fn ($d) => [
                'id' => $d->id, 'codigo' => $d->codigo, 'nombre' => $d->nombre, 'estado' => $d->estado?->value ?? '',
            ], $unidad->dependientes),
            auditoria: $incluirAuditoria ? [
                'created_by' => $usuario($unidad->creadoPor),
                'created_at' => $unidad->creadoEn,
                'updated_by' => $usuario($unidad->modificadoPor),
                'updated_at' => $unidad->modificadoEn,
            ] : null,
        );
    }
}
