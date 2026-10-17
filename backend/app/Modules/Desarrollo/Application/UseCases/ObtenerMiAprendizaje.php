<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\MiAprendizajeDTO;
use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Application\Services\ArmadorDeMicro;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeAsignacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResumen;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use Throwable;

/** «Mi aprendizaje»: resumen, contenidos asignados y completados del colaborador autenticado. */
final class ObtenerMiAprendizaje
{
    public function __construct(
        private readonly AccesoDeColaborador $acceso,
        private readonly MicrocontenidoRepositoryInterface $contenidos,
        private readonly ProgresoMicroRepositoryInterface $progresos,
        private readonly BrechasActivasInterface $brechas,
        private readonly CalculadorDeAsignacion $asignacion,
        private readonly CalculadorDeResumen $resumen,
        private readonly ArmadorDeMicro $armador,
    ) {}

    public function ejecutar(int $usuarioId): MiAprendizajeDTO
    {
        $cuenta = $this->acceso->cuenta($usuarioId);
        $colaboradorId = $cuenta['colaborador_id'];

        $avisos = [];
        $competenciaIds = [];
        try {
            $competenciaIds = array_values(array_unique(array_map(fn (array $b) => $b['competencia_id'], $this->brechas->brechasDe($usuarioId))));
        } catch (Throwable) {
            // Degradación parcial: sin brechas se siguen mostrando los contenidos ya iniciados.
            $avisos[] = 'brechas_no_disponibles';
        }

        $progresos = $this->progresos->progresosDe($colaboradorId);
        $enProgresoIds = [];
        $completadosIds = [];
        $porMicro = [];
        foreach ($progresos as $p) {
            $porMicro[$p['progreso']->microcontenidoId] = $this->armador->progresoComoArreglo($p['progreso'], $p['ultimo_puntaje']);
            if ($p['progreso']->estaCompletado()) {
                $completadosIds[] = $p['progreso']->microcontenidoId;
            } else {
                $enProgresoIds[] = $p['progreso']->microcontenidoId;
            }
        }

        $publicadosPorBrecha = [];
        foreach ($competenciaIds as $competenciaId) {
            foreach ($this->contenidos->publicadosDeCompetencia($competenciaId) as $micro) {
                $publicadosPorBrecha[] = $micro->id;
            }
        }

        // Los iniciados solo se asignan si el contenido sigue Publicado.
        $candidatos = $this->contenidos->obtenerVarios(array_values(array_unique([...$publicadosPorBrecha, ...$enProgresoIds, ...$completadosIds])));
        $iniciadosPublicados = array_values(array_filter($enProgresoIds, fn (int $id) => isset($candidatos[$id]) && $candidatos[$id]->estado === EstadoMicro::Publicado));

        $motivos = $this->asignacion->asignados($publicadosPorBrecha, $iniciadosPublicados, $completadosIds);

        $asignados = array_values(array_filter(array_map(fn (int $id) => $candidatos[$id] ?? null, array_keys($motivos))));
        $completados = array_values(array_filter(array_map(fn (int $id) => $candidatos[$id] ?? null, $completadosIds)));

        $r = $this->progresos->resumenDe($cuenta['tenant_id'], $colaboradorId);

        return new MiAprendizajeDTO(
            $this->resumen->resumir($r['completados'], $r['en_progreso'], $r['minutos']),
            $this->armador->armar($asignados, false, false, $porMicro),
            $this->armador->armar($completados, false, false, $porMicro),
            $motivos,
            $avisos,
        );
    }
}
