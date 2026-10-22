<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeRutas;
use App\Modules\Desarrollo\Application\Services\PreparadorDeRuta;
use App\Modules\Desarrollo\Domain\Exceptions\ConflictoDeVersionRuta;
use App\Modules\Desarrollo\Domain\Exceptions\NivelEnUso;
use App\Modules\Desarrollo\Domain\Exceptions\RutaInvalida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoPermitida;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;

/** Reemplaza los datos y los niveles de una ruta conservando los ids de los niveles y registra cada cambio en el historial. */
final class ActualizarRuta
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly PreparadorDeRuta $preparador,
        private readonly ArmadorDeRutas $armador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  incluye `version`
     * @return array<string, mixed>
     *
     * @throws RutaNoEncontrada
     * @throws ConflictoDeVersionRuta
     * @throws RutaInvalida
     * @throws NivelEnUso
     * @throws RutaNoPermitida
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $rutaId, array $datos): array
    {
        $actual = $this->rutas->obtener($tenantId, $rutaId) ?? throw new RutaNoEncontrada;
        $version = (int) ($datos['version'] ?? 0);
        if ($version !== $actual->version) {
            throw new ConflictoDeVersionRuta;
        }

        ['ruta' => $ruta, 'eventos' => $eventos] = $this->preparador->actualizar($actual, $usuarioId, $datos);

        return $this->armador->detalle($this->rutas->guardar($ruta, $version, $eventos));
    }
}
