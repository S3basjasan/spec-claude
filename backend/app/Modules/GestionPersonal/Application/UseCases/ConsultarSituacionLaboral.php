<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Services\CatalogoDeContratos;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\SituacionLaboralDerivada;

/**
 * Situación laboral de una ficha para la fecha de hoy del tenant: estado derivado, vínculo vigente y próximo ingreso.
 * Si Estructura falla, el estado y las fechas se conservan y puesto/unidad quedan sin valor (`no_disponible`).
 */
final class ConsultarSituacionLaboral
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly SituacionLaboralDerivada $derivada,
        private readonly LectorEstructuraPort $estructura,
        private readonly CatalogoDeContratos $contratos,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ColaboradorNoEncontrado
     */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId): array
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $situacion = $this->derivada->paraFecha(
            $this->vinculos->vinculosDeFicha($contexto->tenantId, $colaboradorId),
            $contexto->hoy(),
        );

        $posiciones = array_filter([
            $situacion->vigente?->asignacionEn($contexto->hoy())?->posicionId ?? $situacion->vigente?->asignacionPrincipal()?->posicionId,
            $situacion->proximo?->asignacionPrincipal()?->posicionId,
        ], fn ($id) => $id !== null);

        $lectura = $posiciones === [] ? null : $this->estructura->posiciones(array_values($posiciones));
        $disponible = $lectura === null || ! $lectura->fallo();
        $datos = $lectura !== null && $lectura->estaDisponible() ? $lectura->datos : [];

        return [
            'estado' => $situacion->estado,
            'vinculo_vigente' => $situacion->vigente === null ? null : $this->describir($situacion->vigente, $datos, $contexto->hoy()),
            'proximo_ingreso' => $situacion->proximo === null ? null : $this->describir($situacion->proximo, $datos, $contexto->hoy(), true),
            'disponibilidad' => $disponible ? 'disponible' : 'no_disponible',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $posiciones
     * @return array<string, mixed>
     */
    private function describir(VinculoLaboral $vinculo, array $posiciones, string $hoy, bool $programado = false): array
    {
        $asignacion = $vinculo->asignacionEn($hoy) ?? $vinculo->asignacionPrincipal();
        $posicion = $asignacion === null ? null : ($posiciones[$asignacion->posicionId] ?? null);

        $salida = [
            'id' => $vinculo->id,
            'fecha_ingreso' => $vinculo->fechaIngreso,
            'tipo_contrato' => $this->contratos->describir($vinculo->tipoContratoClave),
            'posicion' => $asignacion === null ? null : [
                'id' => $asignacion->posicionId,
                'puesto' => $posicion['puesto'] ?? null,
                'unidad' => $posicion['unidad'] ?? null,
                'jefatura' => $posicion['jefatura'] ?? null,
            ],
        ];

        if ($programado) {
            $salida['programado'] = true;
        }

        return $salida;
    }
}
