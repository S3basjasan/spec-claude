<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;

/** Historial de adecuaciones (la más reciente primero): código, fecha, colaborador, puesto objetivo, preparación y brechas. */
final class ListarAdecuaciones
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly ArmadorDeAdecuaciones $armador,
    ) {}

    /**
     * @param  array{fecha_desde: string|null, fecha_hasta: string|null, colaborador_id: int|null, puesto_objetivo_id: int|null, preparacion: string|null}  $filtros
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, array $filtros, int $pagina, int $porPagina): array
    {
        $lectura = $this->adecuaciones->listar($tenantId, array_filter($filtros, fn ($v) => $v !== null), $pagina, $porPagina);

        $fuentes = [];
        $contexto = $this->armador->contexto(
            $tenantId,
            array_map(fn (array $i) => $i['colaborador_id'], $lectura['items']),
            array_map(fn (array $i) => $i['puesto_objetivo_id'], $lectura['items']),
            $fuentes,
        );

        return [
            'data' => array_map(fn (array $i) => [
                'id' => $i['id'],
                'codigo' => $i['codigo'],
                'fecha' => $i['creado_en'],
                'colaborador' => ['id' => $i['colaborador_id'], 'nombre' => $contexto['colaboradores'][$i['colaborador_id']] ?? 'No disponible'],
                'puesto_objetivo' => ['id' => $i['puesto_objetivo_id'], 'nombre' => $contexto['puestos'][$i['puesto_objetivo_id']] ?? 'No disponible'],
                'preparacion' => $i['preparacion'],
                'preparacion_etiqueta' => Preparacion::from($i['preparacion'])->etiqueta(),
                'brechas' => $i['brechas'],
                'es_vigente' => $i['es_vigente'],
            ], $lectura['items']),
            'meta' => [
                'page' => $pagina,
                'per_page' => $porPagina,
                'total' => $lectura['total'],
                'last_page' => max(1, (int) ceil($lectura['total'] / $porPagina)),
            ],
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }
}
