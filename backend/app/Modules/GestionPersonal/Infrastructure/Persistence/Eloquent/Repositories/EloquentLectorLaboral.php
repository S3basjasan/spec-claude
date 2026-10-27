<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Application\Services\CatalogoDeContratos;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\SituacionLaboralDerivada;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Domain\ValueObjects\SituacionLaboral;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeHecho;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use Throwable;

/**
 * Lector laboral real: deriva el estado y la trayectoria de los vínculos del tenant para una fecha.
 * Un fallo inesperado se devuelve como `noDisponible`, nunca como excepción.
 */
final class EloquentLectorLaboral implements LectorLaboralPort
{
    public function __construct(
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly SituacionLaboralDerivada $derivada,
        private readonly CatalogoDeContratos $contratos,
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ExpedienteRepositoryInterface $expedientes,
    ) {}

    public function situacionVigente(int $tenantId, array $colaboradorIds, string $fecha): Lectura
    {
        return $this->proteger(function () use ($tenantId, $colaboradorIds, $fecha) {
            $salida = [];
            foreach ($this->vinculos->vinculosDeFichas($tenantId, $colaboradorIds) as $fichaId => $lista) {
                $situacion = $this->derivada->paraFecha($lista, $fecha);

                if ($situacion->estado === SituacionLaboral::SIN_VINCULO) {
                    continue;
                }

                $asignacion = $situacion->vigente?->asignacionEn($fecha) ?? $situacion->vigente?->asignacionPrincipal();
                $salida[$fichaId] = [
                    'estado' => $situacion->estado,
                    'posicion_id' => $asignacion?->posicionId,
                ];
            }

            return $salida;
        });
    }

    public function idsConVinculoVigente(int $tenantId, string $fecha): Lectura
    {
        return $this->proteger(fn () => $this->idsConEstado($tenantId, SituacionLaboral::ACTIVO, $fecha));
    }

    public function idsPorEstado(int $tenantId, string $estado, string $fecha): Lectura
    {
        return $this->proteger(function () use ($tenantId, $estado, $fecha) {
            if (! in_array($estado, [SituacionLaboral::ACTIVO, SituacionLaboral::DESVINCULADO, SituacionLaboral::SIN_VINCULO], true)) {
                return [];
            }

            return $this->idsConEstado($tenantId, $estado, $fecha);
        });
    }

    public function colaboradoresEnPosiciones(int $tenantId, array $posicionIds, string $fecha): Lectura
    {
        return $this->proteger(fn () => $this->vinculos->fichasEnPosiciones($tenantId, $posicionIds, $fecha));
    }

    public function trayectoria(int $tenantId, int $colaboradorId): Lectura
    {
        return $this->proteger(function () use ($tenantId, $colaboradorId) {
            $hechos = [];
            $contratos = [];
            foreach ($this->vinculos->vinculosDeFicha($tenantId, $colaboradorId) as $indice => $vinculo) {
                $contrato = $this->contratos->describir($vinculo->tipoContratoClave);
                $contratos[$vinculo->id] = $contrato;
                $hechos[] = [
                    'vinculo_id' => $vinculo->id,
                    'fecha_efectiva' => $vinculo->fechaIngreso,
                    'tipo' => ($indice === 0 ? TipoDeHecho::Ingreso : TipoDeHecho::Reingreso)->value,
                    'detalle' => 'Contrato: '.$contrato['etiqueta'],
                    'tipo_contrato' => $contrato,
                    'posicion_id' => $vinculo->asignacionPrincipal()?->posicionId,
                    'registrado_en' => $vinculo->createdAt,
                ];
            }

            // Movimientos efectivamente aplicados (feature 020): ocurren dentro del mismo vínculo.
            foreach ($this->actuaciones->hechosAplicados($tenantId, $colaboradorId) as $movimiento) {
                $hechos[] = [
                    'vinculo_id' => $movimiento['vinculo_id'],
                    'fecha_efectiva' => $movimiento['fecha_efectiva'],
                    'tipo' => TipoDeHecho::from($movimiento['tipo'])->value,
                    'detalle' => 'Movimiento aplicado: '.$movimiento['tipo'],
                    'tipo_contrato' => $contratos[$movimiento['vinculo_id']] ?? null,
                    'posicion_id' => $movimiento['posicion_id'],
                    'registrado_en' => $movimiento['aplicada_en'],
                    'actuacion_id' => $movimiento['actuacion_id'],
                    'politica_numero' => $movimiento['politica_numero'],
                ];
            }
            // Salidas efectivas (feature 021).
            $posiciones = [];
            foreach ($this->vinculos->vinculosDeFicha($tenantId, $colaboradorId) as $vinculo) {
                $posiciones[$vinculo->id] = $vinculo->asignacionPrincipal()?->posicionId;
            }
            foreach ($this->expedientes->hechosEfectivos($tenantId, $colaboradorId) as $salida) {
                $hechos[] = [
                    'vinculo_id' => $salida['vinculo_id'],
                    'fecha_efectiva' => $salida['fecha_efectiva'],
                    'tipo' => TipoDeHecho::Salida->value,
                    'detalle' => 'Salida efectiva: '.$salida['motivo'],
                    'tipo_contrato' => $contratos[$salida['vinculo_id']] ?? null,
                    'posicion_id' => $posiciones[$salida['vinculo_id']] ?? null,
                    'registrado_en' => $salida['aplicada_en'],
                    'expediente_id' => $salida['expediente_id'],
                    'politica_numero' => $salida['politica_numero'],
                ];
            }
            usort($hechos, fn (array $a, array $b) => strcmp($a['fecha_efectiva'], $b['fecha_efectiva']));

            return $hechos;
        });
    }

    public function estadosPresentes(int $tenantId, string $fecha): Lectura
    {
        return $this->proteger(function () use ($tenantId, $fecha) {
            $presentes = [];
            $conVinculos = [];
            foreach ($this->vinculos->vinculosDelTenant($tenantId) as $fichaId => $lista) {
                $conVinculos[$fichaId] = true;
                $presentes[$this->derivada->paraFecha($lista, $fecha)->estado] = true;
            }

            $hayFichasSinVinculos = ColaboradorModel::query()->where('tenant_id', $tenantId)
                ->when($conVinculos !== [], fn ($q) => $q->whereNotIn('id', array_keys($conVinculos)))
                ->exists();
            if ($hayFichasSinVinculos) {
                $presentes[SituacionLaboral::SIN_VINCULO] = true;
            }

            return array_keys($presentes);
        });
    }

    public function proximoIngreso(int $tenantId, int $colaboradorId, string $fecha): Lectura
    {
        return $this->proteger(function () use ($tenantId, $colaboradorId, $fecha) {
            $situacion = $this->derivada->paraFecha($this->vinculos->vinculosDeFicha($tenantId, $colaboradorId), $fecha);

            return $situacion->proximo === null ? null : $this->aProximo($situacion->proximo);
        });
    }

    // ------------------------------------------------------------------ Apoyo

    /**
     * @return list<int>
     */
    private function idsConEstado(int $tenantId, string $estado, string $fecha): array
    {
        $porFicha = $this->vinculos->vinculosDelTenant($tenantId);
        $coinciden = [];
        $conVinculos = [];

        foreach ($porFicha as $fichaId => $lista) {
            $conVinculos[] = $fichaId;
            if ($this->derivada->paraFecha($lista, $fecha)->estado === $estado) {
                $coinciden[] = $fichaId;
            }
        }

        if ($estado === SituacionLaboral::SIN_VINCULO) {
            $sinVinculos = ColaboradorModel::query()->where('tenant_id', $tenantId)
                ->when($conVinculos !== [], fn ($q) => $q->whereNotIn('id', $conVinculos))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $coinciden = array_merge($coinciden, $sinVinculos);
        }

        return array_values(array_map('intval', $coinciden));
    }

    /**
     * @return array{fecha_ingreso: string, tipo_contrato: array{clave: string, etiqueta: string}, posicion_id: int|null}
     */
    private function aProximo(VinculoLaboral $vinculo): array
    {
        return [
            'fecha_ingreso' => $vinculo->fechaIngreso,
            'tipo_contrato' => $this->contratos->describir($vinculo->tipoContratoClave),
            'posicion_id' => $vinculo->asignacionPrincipal()?->posicionId,
        ];
    }

    private function proteger(callable $lectura): Lectura
    {
        try {
            return Lectura::disponible($lectura());
        } catch (Throwable) {
            return Lectura::noDisponible();
        }
    }
}
