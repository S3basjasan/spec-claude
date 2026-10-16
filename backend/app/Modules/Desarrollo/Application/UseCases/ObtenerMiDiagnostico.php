<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\BrechaDTO;
use App\Modules\Desarrollo\Application\DTO\MiDiagnosticoDTO;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDnf;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brechas de la DNF más reciente del colaborador autenticado. El usuario sale siempre del token (nunca de la petición):
 * un colaborador solo ve lo propio. Sin ficha asociada o sin DNF devuelve null.
 */
final class ObtenerMiDiagnostico
{
    private const MAXIMO_PRIORITARIAS = 3;

    public function __construct(
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly DnfRepositoryInterface $repositorio,
        private readonly CatalogoFormativoRepositoryInterface $catalogo,
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly ArmadorDeDnf $armador,
    ) {}

    public function ejecutar(int $usuarioId): ?MiDiagnosticoDTO
    {
        try {
            $cuenta = $this->cuentas->cuentaDe($usuarioId);
        } catch (Throwable $e) {
            Log::warning('No se pudo resolver la ficha del usuario para la DNF.', ['excepcion' => $e::class]);

            return null;
        }
        if ($cuenta === null) {
            return null;
        }

        $dnf = $this->repositorio->masRecienteDeColaborador($cuenta['tenant_id'], $cuenta['colaborador_id']);
        $resultado = $dnf?->resultados[0] ?? null;
        if ($dnf === null || $resultado === null) {
            return null;
        }

        $brechas = $this->armador->armarBrechas($resultado->brechas);
        $conBrecha = $this->ordenar(array_values(array_filter($brechas, fn (BrechaDTO $b) => $b->estado === EstadoBrecha::ConBrecha->value)));
        $cumplen = array_values(array_map(
            fn (BrechaDTO $b) => ['competencia_id' => $b->competenciaId, 'nombre' => $b->nombre, 'nivel_actual' => $b->nivelActual, 'estado' => $b->estado],
            array_filter($brechas, fn (BrechaDTO $b) => in_array($b->estado, [EstadoBrecha::Cumple->value, EstadoBrecha::Supera->value], true)),
        ));
        $sinEvaluacion = count(array_filter($brechas, fn (BrechaDTO $b) => $b->estado === EstadoBrecha::SinEvaluacion->value));

        $puesto = $this->puesto($resultado->puestoId);

        return new MiDiagnosticoDTO(
            ['id' => (int) $dnf->id, 'codigo' => (string) $dnf->codigo, 'fecha' => $dnf->creadoEn?->format(DATE_ATOM) ?? ''],
            $puesto,
            ['con_brecha' => count($conBrecha), 'cumplen' => count($cumplen), 'sin_evaluacion' => $sinEvaluacion],
            array_slice($conBrecha, 0, self::MAXIMO_PRIORITARIAS),
            $conBrecha,
            $cumplen,
            $this->tieneOferta(array_map(fn (BrechaDTO $b) => $b->competenciaId, $conBrecha)),
        );
    }

    /**
     * Prioridad (mayor primero), luego mayor diferencia en grados y luego nombre de competencia.
     *
     * @param  list<BrechaDTO>  $brechas
     * @return list<BrechaDTO>
     */
    private function ordenar(array $brechas): array
    {
        $orden = fn (BrechaDTO $b) => $b->prioridad === null ? 0 : PrioridadBrecha::from($b->prioridad)->orden();
        usort($brechas, fn (BrechaDTO $a, BrechaDTO $b) => [$orden($b), $a->diferencia ?? 0, $a->nombre] <=> [$orden($a), $b->diferencia ?? 0, $b->nombre]);

        return $brechas;
    }

    /**
     * @return array{codigo: string, nombre: string}|null
     */
    private function puesto(int $puestoId): ?array
    {
        try {
            return $this->nombres->puestos([$puestoId])[$puestoId] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<int>  $competenciaIds
     * @return array<int, bool>
     */
    private function tieneOferta(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        $configuraciones = $this->catalogo->configuracionesDe($competenciaIds);
        $oferta = [];
        foreach ($competenciaIds as $id) {
            $oferta[$id] = isset($configuraciones[$id]) && $configuraciones[$id]->recursosDisponibles() >= 1;
        }

        return $oferta;
    }
}
