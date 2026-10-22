<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\RutasParaAdecuacionInterface;
use App\Modules\Desarrollo\Application\UseCases\ConsultarRutasParaOtrosModulos;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;

/**
 * Inscripciones Activas y niveles de las rutas de carrera (feature 019) por su caso de uso de consulta, el mismo que
 * respalda `DesarrolloFacade::inscripcionesActivas` y `DesarrolloFacade::obtenerNivel`.
 */
final class RutasParaAdecuacionDesdeRutas implements RutasParaAdecuacionInterface
{
    public function __construct(
        private readonly ConsultarRutasParaOtrosModulos $rutas,
        private readonly RutasRepositoryInterface $repositorio,
    ) {}

    public function inscripcionesActivas(int $tenantId, int $colaboradorId): array
    {
        return $this->rutas->inscripcionesActivas($tenantId, $colaboradorId);
    }

    public function nivel(int $tenantId, int $nivelId): ?array
    {
        return $this->rutas->obtenerNivel($tenantId, $nivelId);
    }

    public function rutas(int $tenantId): array
    {
        $lectura = $this->repositorio->listar($tenantId, [], 1, 100);

        return array_map(fn (array $r) => ['id' => (int) $r['id'], 'nombre' => (string) $r['nombre']], $lectura['items']);
    }
}
