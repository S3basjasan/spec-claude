<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Services\CatalogoDeContratos;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeHecho;

/**
 * Hechos de ingreso y reingreso de una ficha, del más reciente al más antiguo. El nombre del puesto y de la unidad
 * es el vigente en Estructura (no una definición histórica).
 */
final class ConsultarTrayectoria
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly LectorEstructuraPort $estructura,
        private readonly CatalogoDeContratos $contratos,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ColaboradorNoEncontrado
     */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId): array
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $vinculos = $this->vinculos->vinculosDeFicha($contexto->tenantId, $colaboradorId);
        $posicionIds = [];
        foreach ($vinculos as $vinculo) {
            $id = $vinculo->asignacionPrincipal()?->posicionId;
            if ($id !== null) {
                $posicionIds[] = $id;
            }
        }

        $lectura = $posicionIds === [] ? null : $this->estructura->posiciones(array_values(array_unique($posicionIds)));
        $datos = $lectura !== null && $lectura->estaDisponible() ? $lectura->datos : [];
        $hoy = $contexto->hoy();

        $hechos = [];
        foreach ($vinculos as $indice => $vinculo) {
            $posicionId = $vinculo->asignacionPrincipal()?->posicionId;
            $posicion = $posicionId === null ? null : ($datos[$posicionId] ?? null);

            $hechos[] = [
                'vinculo_id' => $vinculo->id,
                'tipo' => ($indice === 0 ? TipoDeHecho::Ingreso : TipoDeHecho::Reingreso)->value,
                'fecha_efectiva' => $vinculo->fechaIngreso,
                'programado' => $vinculo->fechaIngreso > $hoy,
                'tipo_contrato' => $this->contratos->describir($vinculo->tipoContratoClave),
                'posicion' => $posicionId === null ? null : [
                    'id' => $posicionId,
                    'puesto' => $posicion['puesto'] ?? null,
                    'unidad' => $posicion['unidad'] ?? null,
                    'jefatura' => $posicion['jefatura'] ?? null,
                ],
                'registrado_en' => $vinculo->createdAt,
            ];
        }

        usort($hechos, fn (array $a, array $b) => strcmp($b['fecha_efectiva'], $a['fecha_efectiva']));

        return $hechos;
    }
}
