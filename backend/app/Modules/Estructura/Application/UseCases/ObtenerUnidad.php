<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\UnidadDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;

final class ObtenerUnidad
{
    public function __construct(
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Cualquier persona autenticada puede consultar la unidad, también si está inactiva. La auditoría (quién la creó y
     * modificó, con su nombre) se incluye solo cuando quien consulta es administrador.
     *
     * @throws UnidadNoEncontrada
     */
    public function ejecutar(int $id, bool $esAdministrador = false): UnidadDetalleDTO
    {
        $unidad = $this->unidades->obtener($id) ?? throw new UnidadNoEncontrada;

        $nombres = $esAdministrador
            ? $this->usuarios->nombres(array_values(array_filter([$unidad->creadoPor, $unidad->modificadoPor], fn ($u) => $u !== null)))
            : [];

        return UnidadDetalleDTO::conAuditoria($unidad, $esAdministrador, $nombres);
    }
}
