<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Services\AccesoAPdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use Throwable;

/** Historial de solo lectura de un plan, del más reciente al más antiguo; el colaborador solo ve el de sus planes visibles. */
final class ListarHistorialDePdi
{
    public function __construct(
        private readonly AccesoAPdi $acceso,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function ejecutar(int $tenantId, int $usuarioId, int $id, bool $esAdministrador, int $pagina, int $porPagina): array
    {
        $this->acceso->cargar($tenantId, $usuarioId, $id, $esAdministrador);
        $lectura = $this->repositorio->historial($tenantId, $id, $pagina, $porPagina);

        $nombres = [];
        try {
            $nombres = $this->usuarios->nombresDe(array_values(array_unique(array_map(fn (array $h) => $h['usuario_id'], $lectura['items']))));
        } catch (Throwable) {
        }

        return [
            'data' => array_map(fn (array $h) => [
                'id' => $h['id'],
                'fecha' => $h['fecha'],
                'usuario' => $nombres[$h['usuario_id']] ?? 'No disponible',
                'tipo' => $h['tipo'],
                'tipo_etiqueta' => TipoDeHistorialPdi::from($h['tipo'])->etiqueta(),
                'referencia_id' => $h['referencia_id'],
                'valor_anterior' => $h['valor_anterior'],
                'valor_nuevo' => $h['valor_nuevo'],
                'motivo' => $h['motivo'],
            ], $lectura['items']),
            'meta' => [
                'page' => $pagina,
                'per_page' => $porPagina,
                'total' => $lectura['total'],
                'last_page' => max(1, (int) ceil($lectura['total'] / $porPagina)),
            ],
        ];
    }
}
