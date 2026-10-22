<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Ports\RutasParaAdecuacionInterface;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCumplimiento;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Datos del paso de Selección: colaboradores vigentes por texto y, para un colaborador, sus inscripciones Activas con
 * niveles, los puestos activos para el puesto directo y sus últimas adecuaciones.
 */
final class ObtenerOpcionesDeAdecuacion
{
    public const POR_PAGINA = 200;

    public function __construct(
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly DepartamentosInterface $departamentos,
        private readonly RutasParaAdecuacionInterface $rutas,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, ?string $texto, ?int $colaboradorId): array
    {
        $fuentes = [];

        if ($colaboradorId === null) {
            return [
                'colaboradores' => $this->listaDeColaboradores($tenantId, $texto, $fuentes),
                'puestos' => $this->puestosActivos($fuentes),
                'departamentos' => $this->departamentosActivos($fuentes),
                'rutas' => $this->rutasDelTenant($tenantId, $fuentes),
                'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
            ];
        }

        $inscripciones = [];
        try {
            $inscripciones = array_map(fn (array $i) => [
                'inscripcion_id' => (int) $i['inscripcion_id'],
                'ruta_id' => (int) $i['ruta_id'],
                'ruta_nombre' => (string) $i['ruta_nombre'],
                'nivel_actual' => $i['nivel_actual'] ?? null,
                'nivel_objetivo' => $i['nivel_objetivo'] ?? null,
            ], $this->rutas->inscripcionesActivas($tenantId, $colaboradorId));
        } catch (Throwable) {
            $fuentes[] = 'rutas';
        }

        $puestos = $this->puestosActivos($fuentes);

        $ultimas = $this->adecuaciones->ultimasDeColaborador($tenantId, $colaboradorId, 5);
        $nombresDePuestos = $this->nombresDePuestos($ultimas, $fuentes);

        return [
            'inscripciones' => $inscripciones,
            'puestos' => $puestos,
            'historial' => array_map(fn (Adecuacion $a) => [
                'id' => $a->id,
                'codigo' => $a->codigo?->valor(),
                'fecha' => $a->creadoEn?->format('Y-m-d'),
                'puesto_objetivo' => $nombresDePuestos[$a->puestoObjetivoId] ?? 'No disponible',
                'preparacion' => $a->preparacion->value,
                'preparacion_etiqueta' => $a->preparacion->etiqueta(),
                'cumplimiento_por_dimension' => $this->cumplimientos($a),
            ], $ultimas),
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /**
     * @param  list<string>  $fuentes
     * @return list<array{id: int, codigo: string, nombre: string}>
     */
    private function puestosActivos(array &$fuentes): array
    {
        try {
            return $this->puestos->activos();
        } catch (Throwable) {
            $fuentes[] = 'estructura';

            return [];
        }
    }

    /**
     * @param  list<string>  $fuentes
     * @return list<array{id: int, nombre: string}>
     */
    private function departamentosActivos(array &$fuentes): array
    {
        try {
            return $this->departamentos->activos();
        } catch (Throwable) {
            $fuentes[] = 'estructura';

            return [];
        }
    }

    /**
     * @param  list<string>  $fuentes
     * @return list<array{id: int, nombre: string}>
     */
    private function rutasDelTenant(int $tenantId, array &$fuentes): array
    {
        try {
            return $this->rutas->rutas($tenantId);
        } catch (Throwable) {
            $fuentes[] = 'rutas';

            return [];
        }
    }

    /**
     * @param  list<string>  $fuentes
     * @return list<array{id: int, codigo: string, nombre: string, departamento: string|null}>
     */
    private function listaDeColaboradores(int $tenantId, ?string $texto, array &$fuentes): array
    {
        $hoy = CarbonImmutable::today()->toDateString();

        try {
            $pagina = $this->colaboradores->buscar($tenantId, $texto, 1, self::POR_PAGINA, $hoy);
        } catch (Throwable) {
            $fuentes[] = 'personal';

            return [];
        }

        $ids = array_map(fn (array $c) => (int) $c['id'], $pagina['items']);
        $nombresDeUnidades = [];
        $unidades = [];
        try {
            $unidades = $ids === [] ? [] : $this->departamentoDe->actuales($tenantId, $ids, $hoy);
            $nombresDeUnidades = $this->departamentos->porIds(array_values(array_unique(array_filter($unidades))));
        } catch (Throwable) {
            $fuentes[] = 'personal';
        }

        return array_map(fn (array $c) => [
            'id' => (int) $c['id'],
            'codigo' => (string) $c['codigo'],
            'nombre' => (string) $c['nombre'],
            'departamento' => ($unidades[(int) $c['id']] ?? null) === null ? null : ($nombresDeUnidades[$unidades[(int) $c['id']]] ?? null),
        ], $pagina['items']);
    }

    /**
     * @param  list<Adecuacion>  $adecuaciones
     * @param  list<string>  $fuentes
     * @return array<int, string>
     */
    private function nombresDePuestos(array $adecuaciones, array &$fuentes): array
    {
        $ids = array_values(array_unique(array_map(fn (Adecuacion $a) => $a->puestoObjetivoId, $adecuaciones)));
        if ($ids === []) {
            return [];
        }

        try {
            $salida = [];
            foreach ($this->nombres->puestos($ids) as $id => $p) {
                $salida[(int) $id] = (string) $p['nombre'];
            }

            return $salida;
        } catch (Throwable) {
            $fuentes[] = 'estructura';

            return [];
        }
    }

    /** @return array<string, float|null> */
    private function cumplimientos(Adecuacion $a): array
    {
        $calculador = new CalculadorDeCumplimiento;

        return array_map(fn (?float $v) => $v === null ? null : round($v, 4), $calculador->porDimension($a->resultados));
    }
}
