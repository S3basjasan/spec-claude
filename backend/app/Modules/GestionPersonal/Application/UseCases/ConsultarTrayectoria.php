<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Services\CatalogoDeContratos;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
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
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ExpedienteRepositoryInterface $expedientes,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ColaboradorNoEncontrado
     */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId): array
    {
        return $this->conDisponibilidad($contexto, $colaboradorId)['hechos'];
    }

    /**
     * Los mismos hechos, señalando si Estructura respondió: cuando falla, los nombres de puesto y unidad quedan sin
     * valor y `disponibilidad` es `no_disponible` (la exportación no lo toma por una sección vacía).
     *
     * @return array{hechos: list<array<string, mixed>>, disponibilidad: 'disponible'|'no_disponible'}
     *
     * @throws ColaboradorNoEncontrado
     */
    public function conDisponibilidad(ContextoDeTenant $contexto, int $colaboradorId): array
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

        $movimientos = $this->actuaciones->hechosAplicados($contexto->tenantId, $colaboradorId);
        foreach ($movimientos as $movimiento) {
            $posicionIds[] = $movimiento['posicion_id'];
        }

        $lectura = $posicionIds === [] ? null : $this->estructura->posiciones(array_values(array_unique($posicionIds)));
        $datos = $lectura !== null && $lectura->estaDisponible() ? $lectura->datos : [];
        $disponibilidad = $lectura !== null && $lectura->fallo() ? 'no_disponible' : 'disponible';
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

        // Movimientos efectivamente aplicados (feature 020): ocurren dentro del mismo vínculo y conservan la referencia
        // a la solicitud y a la versión de política utilizada.
        $contratosPorVinculo = [];
        foreach ($vinculos as $vinculo) {
            $contratosPorVinculo[$vinculo->id] = $this->contratos->describir($vinculo->tipoContratoClave);
        }
        foreach ($movimientos as $movimiento) {
            $posicion = $datos[$movimiento['posicion_id']] ?? null;
            $hechos[] = [
                'vinculo_id' => $movimiento['vinculo_id'],
                'tipo' => TipoDeHecho::from($movimiento['tipo'])->value,
                'fecha_efectiva' => $movimiento['fecha_efectiva'],
                'programado' => false,
                'tipo_contrato' => $contratosPorVinculo[$movimiento['vinculo_id']] ?? null,
                'posicion' => [
                    'id' => $movimiento['posicion_id'],
                    'puesto' => $posicion['puesto'] ?? null,
                    'unidad' => $posicion['unidad'] ?? null,
                    'jefatura' => $posicion['jefatura'] ?? null,
                ],
                'registrado_en' => $movimiento['aplicada_en'],
                'actuacion_id' => $movimiento['actuacion_id'],
                'politica_numero' => $movimiento['politica_numero'],
            ];
        }

        // Salidas efectivas (feature 021): el vínculo terminó en la fecha efectiva con el motivo administrativo registrado.
        $principalPorVinculo = [];
        foreach ($vinculos as $vinculo) {
            $principalPorVinculo[$vinculo->id] = $vinculo->asignacionPrincipal()?->posicionId;
        }
        foreach ($this->expedientes->hechosEfectivos($contexto->tenantId, $colaboradorId) as $salida) {
            $posicionId = $principalPorVinculo[$salida['vinculo_id']] ?? null;
            $posicion = $posicionId === null ? null : ($datos[$posicionId] ?? null);
            $hechos[] = [
                'vinculo_id' => $salida['vinculo_id'],
                'tipo' => TipoDeHecho::Salida->value,
                'fecha_efectiva' => $salida['fecha_efectiva'],
                'programado' => false,
                'tipo_contrato' => $contratosPorVinculo[$salida['vinculo_id']] ?? null,
                'posicion' => $posicionId === null ? null : [
                    'id' => $posicionId,
                    'puesto' => $posicion['puesto'] ?? null,
                    'unidad' => $posicion['unidad'] ?? null,
                    'jefatura' => $posicion['jefatura'] ?? null,
                ],
                'registrado_en' => $salida['aplicada_en'],
                'expediente_id' => $salida['expediente_id'],
                'motivo' => $salida['motivo'],
                'politica_numero' => $salida['politica_numero'],
            ];
        }

        usort($hechos, fn (array $a, array $b) => strcmp($b['fecha_efectiva'], $a['fecha_efectiva']));

        return ['hechos' => $hechos, 'disponibilidad' => $disponibilidad];
    }
}
