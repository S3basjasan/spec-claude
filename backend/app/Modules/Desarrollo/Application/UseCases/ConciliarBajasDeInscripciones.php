<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\EventosDeInscripcion;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Retira las inscripciones Activas de quien ya no tiene vínculo laboral vigente (FR-021a), con el motivo «Baja del
 * colaborador» y el sistema como autor. Es idempotente y segura ante fallos: si Personal no responde, no retira a nadie.
 */
final class ConciliarBajasDeInscripciones
{
    public const MOTIVO = 'Baja del colaborador';

    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly RelojInterface $reloj,
        private readonly EventosDeInscripcion $eventos,
    ) {}

    /**
     * @param  int|null  $rutaId  limita la conciliación a una ruta; nulo = todo el tenant
     * @param  int|null  $colaboradorId  limita la conciliación a las inscripciones de un colaborador
     * @return int cantidad de inscripciones retiradas
     */
    public function ejecutar(int $tenantId, ?int $usuarioId = null, ?int $rutaId = null, ?int $colaboradorId = null): int
    {
        $activas = match (true) {
            $colaboradorId !== null => $this->rutas->activasDeColaborador($tenantId, $colaboradorId),
            $rutaId !== null => $this->rutas->activasDeRuta($rutaId),
            default => $this->rutas->activasDeColaboradores($tenantId, $this->rutas->colaboradoresConActivas($tenantId)),
        };
        if ($activas === []) {
            return 0;
        }

        $ids = array_values(array_unique(array_map(fn (InscripcionARuta $i) => $i->colaboradorId, $activas)));
        try {
            $fecha = $this->reloj->hoy($usuarioId ?? 0)->format('Y-m-d');
            $vigentes = $this->colaboradores->vigentesPorIds($tenantId, $ids, $fecha);
        } catch (Throwable) {
            return 0;
        }

        /** @var array<int, Ruta|null> $rutasCargadas */
        $rutasCargadas = [];
        $retiradas = 0;
        foreach ($activas as $inscripcion) {
            if (in_array($inscripcion->colaboradorId, $vigentes, true)) {
                continue;
            }
            $ruta = $rutasCargadas[$inscripcion->rutaId] ??= $this->rutas->obtener($tenantId, $inscripcion->rutaId);
            if ($ruta === null) {
                continue;
            }
            $retirada = $inscripcion->retirada(self::MOTIVO, null, new DateTimeImmutable);
            $this->rutas->guardarInscripcion($retirada, $this->eventos->cambio('retirada', $inscripcion, $retirada, $ruta, null, self::MOTIVO));
            $retiradas++;
        }

        return $retiradas;
    }
}
