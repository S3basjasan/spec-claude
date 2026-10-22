<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeRutas;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;

/** Detalle de una ruta con niveles, requisitos, diferencial y cantera. Concilia antes las bajas para que la cantera sea fiel. */
final class ObtenerRuta
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly ArmadorDeRutas $armador,
        private readonly ConciliarBajasDeInscripciones $conciliar,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RutaNoEncontrada
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $rutaId, bool $soloVigentes = false): array
    {
        $ruta = $this->rutas->obtener($tenantId, $rutaId) ?? throw new RutaNoEncontrada;
        if ($soloVigentes && $ruta->estado !== EstadoDeRuta::Vigente) {
            throw new RutaNoEncontrada;
        }

        $this->conciliar->ejecutar($tenantId, $usuarioId, $rutaId);

        return $this->armador->detalle($ruta);
    }
}
