<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use Throwable;

/** Pesos por dimensión y umbral de preparación vigentes, con su historial de cambios (el más reciente primero). */
final class ObtenerParametrosDeAdecuacion
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $pagina = 1, int $porPagina = 10): array
    {
        $parametros = $this->adecuaciones->obtenerParametros($tenantId);
        $historial = $this->adecuaciones->historialDeParametros($tenantId, $pagina, $porPagina);

        $nombres = [];
        try {
            $nombres = $this->usuarios->nombresDe(array_values(array_unique(array_map(fn (array $h) => $h['user_id'], $historial['items']))));
        } catch (Throwable) {
            // El nombre del usuario es informativo: si no se puede leer, se muestra sin él.
        }

        return [
            'pesos' => $parametros->pesos->comoArreglo(),
            'umbral_meses' => $parametros->umbralMeses,
            'historial' => array_map(fn (array $h) => [
                'campo' => $h['campo'],
                'valor_anterior' => $h['valor_anterior'],
                'valor_nuevo' => $h['valor_nuevo'],
                'usuario' => ['id' => $h['user_id'], 'nombre' => $nombres[$h['user_id']] ?? null],
                'fecha' => $h['fecha'],
            ], $historial['items']),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $historial['total'], 'last_page' => max(1, (int) ceil($historial['total'] / $porPagina))],
        ];
    }
}
