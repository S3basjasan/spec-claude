<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\DTO\MicroDTO;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Entities\Microcontenido;
use App\Modules\Desarrollo\Domain\Entities\ProgresoMicro;

/** Convierte entidades en DTO de presentación resolviendo competencias y nombres con una consulta por lote. */
final class ArmadorDeMicro
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly NombresDeUsuarioInterface $nombres,
    ) {}

    /**
     * @param  array<int, Microcontenido>  $entidades
     * @param  array<int, array<string, mixed>>  $progresos  microcontenido_id => progreso (ver `progresoComoArreglo`)
     * @return array<int, MicroDTO>
     */
    public function armar(array $entidades, bool $esAdmin, bool $conAuditoria = false, array $progresos = []): array
    {
        $competenciaIds = [];
        $usuarioIds = [];
        foreach ($entidades as $e) {
            array_push($competenciaIds, ...$e->competenciaIds);
            if ($conAuditoria) {
                $usuarioIds[] = $e->creadoPor;
                $usuarioIds[] = $e->modificadoPor;
            }
        }

        $porId = [];
        if ($competenciaIds !== []) {
            foreach ($this->competencias->porIds(array_values(array_unique($competenciaIds))) as $c) {
                $porId[(int) $c['id']] = $c;
            }
        }
        $nombres = $conAuditoria ? $this->nombres->nombresDe(array_values(array_filter($usuarioIds, fn ($id) => $id !== null))) : [];

        return array_map(function (Microcontenido $e) use ($porId, $nombres, $esAdmin, $progresos) {
            $competencias = [];
            foreach ($e->competenciaIds as $id) {
                if (isset($porId[$id])) {
                    $c = $porId[$id];
                    $competencias[] = ['id' => (int) $c['id'], 'codigo' => (string) $c['codigo'], 'nombre' => (string) $c['nombre'], 'estado' => (string) $c['estado']];
                }
            }

            return new MicroDTO(
                $e,
                $competencias,
                $esAdmin,
                $e->creadoPor === null ? null : ($nombres[$e->creadoPor] ?? null),
                $e->modificadoPor === null ? null : ($nombres[$e->modificadoPor] ?? null),
                $progresos[$e->id] ?? null,
            );
        }, $entidades);
    }

    /**
     * @param  array{correctas: int, total: int}|null  $ultimoPuntaje
     * @return array{estado: string, posicion_segundos: int|null, fecha_inicio: string, fecha_completado: string|null, ultimo_puntaje: array{correctas: int, total: int}|null}
     */
    public function progresoComoArreglo(ProgresoMicro $p, ?array $ultimoPuntaje): array
    {
        return [
            'estado' => $p->estado->value,
            'posicion_segundos' => $p->posicionSegundos,
            'fecha_inicio' => $p->fechaInicio,
            'fecha_completado' => $p->fechaCompletado,
            'ultimo_puntaje' => $ultimoPuntaje,
        ];
    }
}
