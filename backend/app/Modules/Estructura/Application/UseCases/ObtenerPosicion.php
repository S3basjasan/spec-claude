<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PosicionDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;

final class ObtenerPosicion
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Cualquier persona autenticada puede consultar la posición, también si está Suprimida. La auditoría (quién la creó
     * y modificó, con su nombre) se incluye solo cuando quien consulta es administrador.
     *
     * @throws PosicionNoEncontrada
     */
    public function ejecutar(int $id, bool $esAdministrador = false): PosicionDetalleDTO
    {
        $posicion = $this->posiciones->obtener($id) ?? throw new PosicionNoEncontrada;

        return PosicionDetalleDTO::para($posicion, $this->usuarios, $esAdministrador);
    }
}
