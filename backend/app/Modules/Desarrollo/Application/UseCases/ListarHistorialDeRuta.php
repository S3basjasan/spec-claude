<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use Throwable;

/**
 * Historial de la ruta, de sus niveles y de sus inscripciones, del más reciente al más antiguo. Es de solo lectura. Un
 * usuario nulo es el sistema (por ejemplo, el retiro automático por baja del colaborador).
 */
final class ListarHistorialDeRuta
{
    public const SISTEMA = 'Sistema';

    public const NO_DISPONIBLE = 'No disponible';

    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly NombresDeReferenciaInterface $referencias,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     *
     * @throws RutaNoEncontrada
     */
    public function ejecutar(int $tenantId, int $rutaId, int $pagina, int $porPagina): array
    {
        $this->rutas->obtener($tenantId, $rutaId) ?? throw new RutaNoEncontrada;

        $resultado = $this->rutas->historial($tenantId, $rutaId, $pagina, $porPagina);
        $items = $resultado['items'];

        $nombresDeUsuario = $this->nombresDeUsuarios(array_values(array_unique(array_filter(array_column($items, 'user_id')))));
        $colaboradorIds = [];
        foreach ($items as $h) {
            $id = ($h['valor_nuevo']['colaborador_id'] ?? null) ?? ($h['valor_anterior']['colaborador_id'] ?? null);
            if ($id !== null) {
                $colaboradorIds[(int) $id] = true;
            }
        }
        $nombresDeColaborador = $this->nombresDeColaboradores($tenantId, array_keys($colaboradorIds));

        return [
            'data' => array_map(function (array $h) use ($nombresDeUsuario, $nombresDeColaborador) {
                $colaboradorId = ($h['valor_nuevo']['colaborador_id'] ?? null) ?? ($h['valor_anterior']['colaborador_id'] ?? null);

                return [
                    'id' => $h['id'],
                    'tipo' => $h['tipo'],
                    'accion' => $h['accion'],
                    'inscripcion_id' => $h['inscripcion_id'],
                    'colaborador' => $colaboradorId === null ? null : ($nombresDeColaborador[(int) $colaboradorId] ?? self::NO_DISPONIBLE),
                    'valor_anterior' => $h['valor_anterior'],
                    'valor_nuevo' => $h['valor_nuevo'],
                    'motivo' => $h['motivo'],
                    'usuario' => $h['user_id'] === null ? self::SISTEMA : ($nombresDeUsuario[$h['user_id']] ?? self::NO_DISPONIBLE),
                    'created_at' => $h['created_at'],
                ];
            }, $items),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresDeUsuarios(array $ids): array
    {
        try {
            return $ids === [] ? [] : $this->usuarios->nombresDe($ids);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresDeColaboradores(int $tenantId, array $ids): array
    {
        try {
            return $ids === [] ? [] : $this->referencias->colaboradores($tenantId, $ids);
        } catch (Throwable) {
            return [];
        }
    }
}
