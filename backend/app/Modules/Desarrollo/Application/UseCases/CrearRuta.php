<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeRutas;
use App\Modules\Desarrollo\Application\Services\PreparadorDeRuta;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaRuta;
use App\Modules\Desarrollo\Domain\Exceptions\RutaInvalida;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;

/** Crea una ruta en Borrador con sus niveles y registra el alta en el historial. */
final class CrearRuta
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly PreparadorDeRuta $preparador,
        private readonly ArmadorDeRutas $armador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws RutaInvalida
     * @throws FuenteNoDisponibleParaRuta
     */
    public function ejecutar(int $tenantId, int $usuarioId, array $datos): array
    {
        ['ruta' => $ruta, 'eventos' => $eventos] = $this->preparador->crear($tenantId, $usuarioId, $datos);

        return $this->armador->detalle($this->rutas->crear($ruta, $eventos));
    }
}
