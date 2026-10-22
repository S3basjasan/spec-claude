<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeRutas;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use Throwable;

/**
 * «Mi ruta de carrera»: las inscripciones Activas del usuario autenticado con la escalera de cada ruta, lo que se exige en
 * cada nivel y las rutas Vigentes disponibles. Solo lee datos propios; nunca recibe un identificador de colaborador.
 */
final class ObtenerMiRuta
{
    public const AVISO = 'Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.';

    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly ConciliarBajasDeInscripciones $conciliar,
        private readonly ArmadorDeRutas $armador,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $usuarioId): array
    {
        $fuentes = [];
        $cuenta = $this->cuenta($usuarioId, $fuentes);
        $inscripciones = [];

        if ($cuenta !== null && $cuenta['tenant_id'] === $tenantId) {
            $this->conciliar->ejecutar($tenantId, $usuarioId, null, $cuenta['colaborador_id']);
            /** @var array<int, Ruta|null> $cargadas */
            $cargadas = [];
            foreach ($this->rutas->activasDeColaborador($tenantId, $cuenta['colaborador_id']) as $i) {
                $ruta = $cargadas[$i->rutaId] ??= $this->rutas->obtener($tenantId, $i->rutaId);
                if ($ruta !== null) {
                    $inscripciones[] = $this->inscripcion($i, $ruta, $fuentes);
                }
            }
        }

        return [
            'inscripciones' => $inscripciones,
            'rutas_disponibles' => $this->rutas->vigentes($tenantId),
            'aviso' => self::AVISO,
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /**
     * @param  list<string>  $fuentes
     * @return array<string, mixed>
     */
    private function inscripcion(InscripcionARuta $i, Ruta $ruta, array &$fuentes): array
    {
        return [
            'inscripcion_id' => $i->id,
            'ruta' => ['id' => $ruta->id, 'codigo' => $ruta->codigo(), 'nombre' => $ruta->nombre, 'tipo' => $ruta->tipo->value],
            'niveles' => $this->armador->nivelesParaColaborador($ruta, $fuentes),
            'nivel_actual_id' => $i->nivelActualId,
            'nivel_objetivo_id' => $i->nivelObjetivoId,
            'siguiente_nivel_id' => $this->siguiente($i, $ruta)?->id,
            // «Ver mi adecuación» (feature 020) se ofrece solo si la inscripción tiene un nivel objetivo.
            'puede_ver_adecuacion' => $i->nivelObjetivoId !== null,
        ];
    }

    private function siguiente(InscripcionARuta $i, Ruta $ruta): ?NivelDeRuta
    {
        $ordenActual = $i->nivelActualId === null ? 0 : ($ruta->nivelPorId($i->nivelActualId)?->orden ?? 0);

        foreach ($ruta->niveles as $nivel) {
            if ($nivel->orden === $ordenActual + 1) {
                return $nivel;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fuentes
     * @return array{tenant_id: int, colaborador_id: int}|null
     */
    private function cuenta(int $usuarioId, array &$fuentes): ?array
    {
        try {
            $cuenta = $this->cuentas->cuentaDe($usuarioId);
        } catch (Throwable) {
            $fuentes[] = 'personal';

            return null;
        }

        return $cuenta === null ? null : ['tenant_id' => (int) $cuenta['tenant_id'], 'colaborador_id' => (int) $cuenta['colaborador_id']];
    }
}
