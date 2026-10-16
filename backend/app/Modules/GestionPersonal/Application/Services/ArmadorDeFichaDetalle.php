<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\AccesoDeCuenta;
use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoLaboral;

/**
 * Compone la representación del detalle: ficha propia + bloques leídos de otras capacidades.
 */
final class ArmadorDeFichaDetalle
{
    public function __construct(
        private readonly LectorLaboralPort $laboral,
        private readonly LectorEstructuraPort $estructura,
        private readonly LectorDeCapacidadesPort $capacidades,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(Colaborador $colaborador, ContextoDeTenant $contexto, bool $esMiFicha = false, ?AccesoDeCuenta $acceso = null): array
    {
        $hoy = $contexto->hoy();

        return array_merge(
            [
                'id' => $colaborador->id,
                'codigo' => $colaborador->codigo,
                'version' => $colaborador->version,
            ],
            $colaborador->datos,
            [
                'created_at' => $colaborador->createdAt,
                'updated_at' => $colaborador->updatedAt,
                'laboral' => $this->bloqueLaboral($colaborador, $hoy),
                'trayectoria' => $this->bloqueTrayectoria($colaborador, $hoy),
                'capacidades' => [
                    'desarrollo' => $this->capacidades->instalada('desarrollo'),
                    'documentos' => $this->capacidades->instalada('documentos')
                        && (! $esMiFicha || ($acceso?->puedeConsultarDocumentos ?? false)),
                    'registrar_ingreso' => ! $esMiFicha && $this->capacidades->instalada('registrar_ingreso'),
                    'exportar' => $this->capacidades->instalada('exportar')
                        && (! $esMiFicha || ($acceso?->puedeExportar ?? false)),
                ],
                'acciones' => ['editar' => ! $esMiFicha],
            ],
        );
    }

    /**
     * @return array{disponibilidad: string, estado: string|null, puesto: string|null, unidad: string|null}
     */
    private function bloqueLaboral(Colaborador $colaborador, string $hoy): array
    {
        $situacion = $this->laboral->situacionVigente($colaborador->tenantId, [$colaborador->id], $hoy);

        if ($situacion->fallo()) {
            return ['disponibilidad' => 'no_disponible', 'estado' => null, 'puesto' => null, 'unidad' => null, 'proximo_ingreso' => null];
        }

        $vigente = $situacion->estaDisponible() ? ($situacion->datos[$colaborador->id] ?? null) : null;
        $proximo = $this->proximoIngreso($colaborador, $hoy);

        if ($vigente === null) {
            return [
                'disponibilidad' => 'disponible',
                'estado' => EstadoLaboral::SinVinculoVigente->value,
                'puesto' => null,
                'unidad' => null,
                'proximo_ingreso' => $proximo,
            ];
        }

        $bloque = [
            'disponibilidad' => 'disponible',
            'estado' => (string) $vigente['estado'],
            'puesto' => null,
            'unidad' => null,
            'proximo_ingreso' => $proximo,
        ];

        if (($vigente['posicion_id'] ?? null) !== null) {
            $posicion = $this->estructura->posiciones([(int) $vigente['posicion_id']]);
            if ($posicion->fallo()) {
                $bloque['disponibilidad'] = 'no_disponible';
            } elseif ($posicion->estaDisponible()) {
                $datos = $posicion->datos[(int) $vigente['posicion_id']] ?? null;
                $bloque['puesto'] = $datos['puesto'] ?? null;
                $bloque['unidad'] = $datos['unidad'] ?? null;
            }
        }

        return $bloque;
    }

    /**
     * Próximo ingreso programado (GP-02) con su posición, o null si no hay.
     *
     * @return array<string, mixed>|null
     */
    private function proximoIngreso(Colaborador $colaborador, string $hoy): ?array
    {
        $lectura = $this->laboral->proximoIngreso($colaborador->tenantId, $colaborador->id, $hoy);
        if (! $lectura->estaDisponible() || $lectura->datos === null) {
            return null;
        }

        $proximo = $lectura->datos;
        $posicion = ['id' => $proximo['posicion_id'] ?? null, 'puesto' => null, 'unidad' => null, 'jefatura' => null];

        if ($posicion['id'] !== null) {
            $datos = $this->estructura->posiciones([(int) $posicion['id']]);
            if ($datos->estaDisponible()) {
                $fila = $datos->datos[(int) $posicion['id']] ?? [];
                $posicion['puesto'] = $fila['puesto'] ?? null;
                $posicion['unidad'] = $fila['unidad'] ?? null;
                $posicion['jefatura'] = $fila['jefatura'] ?? null;
            }
        }

        return [
            'fecha_ingreso' => $proximo['fecha_ingreso'],
            'tipo_contrato' => $proximo['tipo_contrato'],
            'posicion' => $posicion,
            'programado' => true,
        ];
    }

    /**
     * @return array{disponibilidad: string, hechos: list<array<string, mixed>>}
     */
    private function bloqueTrayectoria(Colaborador $colaborador, string $hoy): array
    {
        $lectura = $this->laboral->trayectoria($colaborador->tenantId, $colaborador->id);

        if ($lectura->fallo()) {
            return ['disponibilidad' => 'no_disponible', 'hechos' => []];
        }

        if (! $lectura->estaDisponible()) {
            return ['disponibilidad' => 'disponible', 'hechos' => []];
        }

        $hechos = array_map(fn (array $hecho) => [
            'fecha_efectiva' => $hecho['fecha_efectiva'],
            'tipo' => $hecho['tipo'],
            'programado' => $hecho['fecha_efectiva'] > $hoy,
            'detalle' => $hecho['detalle'] ?? null,
        ], $lectura->datos);

        usort($hechos, fn (array $a, array $b) => strcmp($b['fecha_efectiva'], $a['fecha_efectiva']));

        return ['disponibilidad' => 'disponible', 'hechos' => $hechos];
    }
}
