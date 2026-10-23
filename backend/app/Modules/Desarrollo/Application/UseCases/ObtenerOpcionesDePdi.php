<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Ports\RutasParaAdecuacionInterface;
use App\Modules\Desarrollo\Application\Services\ValidadorDePdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use Throwable;

/**
 * Datos de los selectores del asistente: colaboradores vigentes por texto, puestos, departamentos, administradores y las
 * dimensiones disponibles; para un colaborador, el puesto objetivo sugerido por su inscripción Activa y sus planes abiertos.
 */
final class ObtenerOpcionesDePdi
{
    public const POR_PAGINA = 200;

    public function __construct(
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly DepartamentosInterface $departamentos,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly AprobadoresInterface $aprobadores,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly RutasParaAdecuacionInterface $rutas,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ValidadorDePdi $validador,
        private readonly RelojInterface $reloj,
        private readonly CompetenciasInterface $competencias,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $usuarioId, ?string $texto, ?int $colaboradorId): array
    {
        $dimensiones = array_map(fn ($d) => $d->value, $this->validador->dimensionesDisponibles());

        if ($colaboradorId !== null) {
            return $this->deColaborador($tenantId, $colaboradorId) + ['dimensiones_disponibles' => $dimensiones];
        }

        $hoy = $this->reloj->hoy($usuarioId)->format('Y-m-d');

        return [
            'colaboradores' => $this->colaboradoresConDepartamento($tenantId, $texto, $hoy),
            'puestos' => $this->seguro(fn () => $this->puestos->activos(), []),
            'departamentos' => $this->seguro(fn () => $this->departamentos->activos(), []),
            'responsables' => $this->responsables($tenantId),
            'competencias' => $this->competenciasActivas(),
            'dimensiones_disponibles' => $dimensiones,
        ];
    }

    /** @return list<array{id: int, codigo: string, nombre: string}> competencias activas, para elegir el elemento de un compromiso */
    private function competenciasActivas(): array
    {
        $todas = $this->seguro(fn () => $this->competencias->todas(), []);

        return array_values(array_map(
            fn (array $c) => ['id' => (int) $c['id'], 'codigo' => (string) $c['codigo'], 'nombre' => (string) $c['nombre']],
            array_filter($todas, fn (array $c) => $this->competencias->estaActiva((int) $c['id'])),
        ));
    }

    /** @return array<string, mixed> */
    private function deColaborador(int $tenantId, int $colaboradorId): array
    {
        $sugerido = null;
        $inscripciones = $this->seguro(fn () => $this->rutas->inscripcionesActivas($tenantId, $colaboradorId), []);
        foreach ($inscripciones as $i) {
            $objetivo = $i['nivel_objetivo'] ?? null;
            if ($objetivo !== null) {
                $sugerido = ['id' => (int) $objetivo['puesto_id'], 'nombre' => (string) ($objetivo['nombre'] ?? '')];
                break;
            }
        }

        $abiertos = [];
        foreach (TipoDePdi::cases() as $tipo) {
            $abiertos[$tipo->value] = $this->repositorio->planAbierto($tenantId, $colaboradorId, $tipo);
        }

        return ['puesto_objetivo_sugerido' => $sugerido, 'plan_abierto' => $abiertos];
    }

    /** @return list<array{id: int, codigo: string, nombre: string, departamento: string|null}> */
    private function colaboradoresConDepartamento(int $tenantId, ?string $texto, string $hoy): array
    {
        $pagina = $this->seguro(fn () => $this->colaboradores->buscar($tenantId, $texto, 1, self::POR_PAGINA, $hoy), ['items' => [], 'total' => 0]);
        $items = $pagina['items'];
        $ids = array_map(fn (array $c) => (int) $c['id'], $items);
        $unidades = $ids === [] ? [] : $this->seguro(fn () => $this->departamentoDe->actuales($tenantId, $ids, $hoy), []);
        $nombres = $this->seguro(fn () => $this->departamentos->porIds(array_values(array_unique(array_filter($unidades)))), []);

        return array_map(fn (array $c) => [
            'id' => (int) $c['id'],
            'codigo' => (string) $c['codigo'],
            'nombre' => (string) $c['nombre'],
            'departamento' => isset($unidades[$c['id']]) ? ($nombres[$unidades[$c['id']]] ?? null) : null,
        ], $items);
    }

    /** @return list<array{id: int, nombre: string}> */
    private function responsables(int $tenantId): array
    {
        $administradores = $this->seguro(fn () => $this->aprobadores->administradores($tenantId), []);
        $nombres = $this->seguro(fn () => $this->usuarios->nombresDe(array_map(fn (array $a) => (int) $a['id'], $administradores)), []);

        return array_map(fn (array $a) => ['id' => (int) $a['id'], 'nombre' => (string) ($nombres[(int) $a['id']] ?? $a['nombre'] ?? 'Administrador')], $administradores);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fuente
     * @param  T  $porDefecto
     * @return T
     */
    private function seguro(callable $fuente, mixed $porDefecto): mixed
    {
        try {
            return $fuente();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
