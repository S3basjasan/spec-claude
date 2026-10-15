<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\UnidadDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadModificada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;

final class ActualizarUnidad
{
    public function __construct(
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Reemplaza nombre y unidad padre (la reubicación mueve la rama completa). Sin cambios reales la unidad queda igual
     * (misma versión y auditoría). Permitido con la unidad inactiva. El código y el tipo no pueden cambiarse.
     *
     * @param  string|null  $codigoEnviado  Código que llegó en la petición (si llegó).
     *
     * @throws UnidadNoEncontrada
     * @throws UnidadModificada
     * @throws UnidadInvalida
     */
    public function ejecutar(int $id, int $versionEsperada, DatosUnidad $datos, ?string $codigoEnviado = null, ?int $actorId = null): UnidadDetalleDTO
    {
        $actual = $this->unidades->obtener($id) ?? throw new UnidadNoEncontrada;

        if ($codigoEnviado !== null && $codigoEnviado !== $actual->codigo) {
            throw new UnidadInvalida(['codigo' => 'El código de la unidad no se puede cambiar.']);
        }

        $actualizada = $this->unidades->actualizar(
            $id,
            $versionEsperada,
            new DatosUnidad(trim($datos->nombre), $datos->unidadPadreId),
            $actorId,
        );

        return UnidadDetalleDTO::paraAdministrador($actualizada, $this->usuarios);
    }
}
