<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\OfertaFormativaInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCumplimiento;
use Throwable;

/**
 * «Mi adecuación»: las adecuaciones vigentes (una por puesto objetivo) del usuario autenticado. Solo lee datos propios,
 * nunca recibe un identificador de colaborador y jamás incluye puntajes de comparación, posiciones en un ranking ni datos
 * de otras personas o de quien generó la adecuación.
 */
final class ObtenerMiAdecuacion
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly ArmadorDeAdecuaciones $armador,
        private readonly CalculadorDeCumplimiento $cumplimiento,
        private readonly OfertaFormativaInterface $oferta,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $usuarioId, ?int $puestoObjetivoId): array
    {
        $fuentes = [];
        $colaboradorId = $this->colaboradorDe($tenantId, $usuarioId, $fuentes);
        $vigentes = $colaboradorId === null ? [] : $this->adecuaciones->vigentesDeColaborador($tenantId, $colaboradorId);

        $contexto = $this->armador->contexto($tenantId, [], array_map(fn (Adecuacion $a) => $a->puestoObjetivoId, $vigentes), $fuentes);
        $lista = array_map(fn (Adecuacion $a) => $this->adecuacion($a, $contexto['puestos']), $vigentes);

        $seleccionada = null;
        foreach ($vigentes as $a) {
            if ($puestoObjetivoId !== null && $a->puestoObjetivoId === $puestoObjetivoId) {
                $seleccionada = $a->id;
            }
        }

        return [
            'adecuaciones' => $lista,
            'seleccionada' => $seleccionada ?? ($vigentes[0]->id ?? null),
            'aviso' => ArmadorDeAdecuaciones::AVISO_GUIA,
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /**
     * @param  list<string>  $fuentes
     */
    private function colaboradorDe(int $tenantId, int $usuarioId, array &$fuentes): ?int
    {
        try {
            $cuenta = $this->cuentas->cuentaDe($usuarioId);
        } catch (Throwable) {
            $fuentes[] = 'personal';

            return null;
        }

        return $cuenta !== null && (int) $cuenta['tenant_id'] === $tenantId ? (int) $cuenta['colaborador_id'] : null;
    }

    /**
     * @param  array<int, string>  $puestos
     * @return array<string, mixed>
     */
    private function adecuacion(Adecuacion $a, array $puestos): array
    {
        return [
            'id' => $a->id,
            'codigo' => $a->codigo?->valor(),
            'fecha' => $a->creadoEn?->format('Y-m-d\TH:i:sP'),
            'puesto_objetivo' => ['id' => $a->puestoObjetivoId, 'nombre' => $puestos[$a->puestoObjetivoId] ?? 'No disponible'],
            'preparacion' => $a->preparacion->value,
            'preparacion_etiqueta' => $a->preparacion->etiqueta(),
            'resultados' => array_map(fn (ResultadoDeAdecuacion $r) => $this->armador->resultado($r, false), $a->resultados),
            'cumplimiento_por_dimension' => $this->armador->redondear($this->cumplimiento->porDimension($a->resultados)),
            'cumplimiento_por_dimension_actual' => $this->armador->redondear($this->cumplimiento->porDimensionFrenteAlActual($a->resultados)),
            'brechas' => array_map(fn (ResultadoDeAdecuacion $r) => $this->brecha($r), $a->brechas()),
        ];
    }

    /** @return array<string, mixed> */
    private function brecha(ResultadoDeAdecuacion $r): array
    {
        $conCurso = $r->dimension->llevaGrado() && $r->elementoId !== null && $this->oferta->tieneOferta($r->elementoId);

        return [
            'dimension' => $r->dimension->value,
            'elemento_id' => $r->elementoId,
            'nombre' => $r->nombreElemento,
            'diferencia' => $r->diferencia,
            'tiempo_cierre_meses' => $r->tiempoCierreMeses,
            'curso' => $conCurso ? ['disponible' => true, 'ruta' => '/formacion?competencia='.$r->elementoId] : null,
        ];
    }
}
