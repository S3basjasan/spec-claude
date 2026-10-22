<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeActividadesInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeDnfInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeMicrolearningInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDePlanesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosDeReportes;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reúne con una sola lectura por fuente los datos del tenant que consumen los reportes. Si una fuente de otro módulo
 * falla, no propaga el error: la registra en `noDisponibles` y deja vacía esa parte (FR-043). Los datos propios
 * (encuestas e impactos) sí propagan el error, porque no hay degradación posible.
 */
final class RecolectorDeReportes
{
    public function __construct(
        private readonly FuenteDePlanesInterface $planes,
        private readonly FuenteDeActividadesInterface $actividades,
        private readonly FuenteDeMicrolearningInterface $microlearning,
        private readonly FuenteDeDnfInterface $dnf,
        private readonly DepartamentoDeColaboradorInterface $departamentos,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly RelojInterface $reloj,
    ) {}

    public function recolectar(int $tenantId, int $usuarioId): DatosDeReportes
    {
        $hoy = $this->reloj->hoy($usuarioId);
        $caidas = [];
        $leer = function (string $fuente, Closure $lectura, mixed $vacio) use (&$caidas): mixed {
            try {
                return $lectura();
            } catch (Throwable $e) {
                Log::warning('Fuente de reportes no disponible: '.$fuente, ['error' => $e->getMessage()]);
                $caidas[$fuente] = true;

                return $vacio;
            }
        };

        $acciones = [];
        foreach ($leer(DatosDeReportes::FUENTE_PLAN, fn () => $this->planes->acciones($tenantId, $hoy), []) as $accion) {
            $acciones[$accion['accion_id']] = $accion;
        }
        $actividades = $leer(DatosDeReportes::FUENTE_ACTIVIDADES, fn () => $this->actividades->actividades($tenantId, $hoy), []);
        $asistencias = $leer(DatosDeReportes::FUENTE_ACTIVIDADES, fn () => $this->actividades->asistencias($tenantId, $hoy), []);
        $completaciones = $leer(DatosDeReportes::FUENTE_MICROLEARNING, fn () => $this->microlearning->completaciones($tenantId), []);
        $evaluaciones = $leer(DatosDeReportes::FUENTE_DNF, fn () => $this->dnf->evaluaciones($tenantId), []);

        $encuestas = array_map(
            fn ($e) => ['actividad_id' => $e->actividadId, 'colaborador_id' => $e->colaboradorId, 'calificacion' => $e->calificacion],
            $this->reportes->encuestasDe($tenantId),
        );
        $beneficios = [];
        foreach ($this->reportes->impactosDe($tenantId) as $accionId => $impacto) {
            $beneficios[$accionId] = $impacto->beneficioEstimado;
        }

        $colaboradores = [];
        foreach ($acciones as $a) {
            array_push($colaboradores, ...$a['participante_ids']);
        }
        foreach ([...$asistencias, ...$completaciones, ...$evaluaciones] as $fila) {
            $colaboradores[] = $fila['colaborador_id'];
        }
        $departamentoDe = $leer(
            DatosDeReportes::FUENTE_PERSONAL,
            fn () => $this->departamentos->actuales($tenantId, array_values(array_unique($colaboradores)), $hoy->format('Y-m-d')),
            [],
        );

        return new DatosDeReportes(
            $hoy, $acciones, $actividades, $asistencias, $completaciones, $encuestas, $beneficios, $evaluaciones, $departamentoDe,
            array_keys($caidas),
        );
    }
}
