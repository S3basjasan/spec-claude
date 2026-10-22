<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;
use Tests\Support\Desarrollo\ColaboradoresVigentesFalso;
use Tests\Support\Desarrollo\DepartamentosFalsos;
use Tests\Support\Desarrollo\NombresDeReferenciaFalsos;
use Tests\Support\Desarrollo\PuestosParaRutasFalso;
use Tests\Support\Desarrollo\RelojFijo;

/**
 * Helpers de la feature 019 (Rutas y planes de carrera). Datos sintéticos; las rutas e inscripciones de preparación se
 * escriben directamente en la base para no depender de los casos de uso que se prueban.
 */

/**
 * Perfiles sintéticos: del 1 al 3 forman una escalera con competencias que suben de grado; el 4 y el 5 son simples;
 * el 6 está inactivo.
 *
 * @return array<int, array<string, mixed>>
 */
function puestosDeRutas(): array
{
    $c = fn (int $id, string $nombre, string $grado, string $tipo = 'Cardinal') => [
        'competencia_id' => $id, 'codigo' => sprintf('COM-%03d', $id), 'nombre' => $nombre, 'tipo' => $tipo, 'grado' => $grado,
    ];

    return [
        1 => ['codigo' => 'PU-001', 'nombre' => 'Asistente de datos', 'competencias' => [$c(1, 'Comunicación', 'C'), $c(2, 'Análisis', 'D', 'Especifica')],
            'requisitos' => ['formacion' => ['Bachiller'], 'capacitacion' => [], 'experiencia' => ['Sin experiencia']]],
        2 => ['codigo' => 'PU-002', 'nombre' => 'Analista de datos', 'competencias' => [$c(1, 'Comunicación', 'B'), $c(2, 'Análisis', 'C', 'Especifica'), $c(3, 'Liderazgo', 'C')],
            'requisitos' => ['formacion' => ['Tecnólogo'], 'capacitacion' => ['Excel avanzado'], 'experiencia' => ['1 año']]],
        3 => ['codigo' => 'PU-003', 'nombre' => 'Analista senior', 'competencias' => [$c(1, 'Comunicación', 'A'), $c(2, 'Análisis', 'C', 'Especifica'), $c(3, 'Liderazgo', 'B')],
            'requisitos' => ['formacion' => ['Ingeniería'], 'capacitacion' => ['Excel avanzado', 'SQL'], 'experiencia' => ['3 años']]],
        4 => ['codigo' => 'PU-004', 'nombre' => 'Jefe de datos', 'competencias' => [$c(1, 'Comunicación', 'A')], 'requisitos' => ['formacion' => ['Maestría']]],
        5 => ['codigo' => 'PU-005', 'nombre' => 'Gerente de datos', 'competencias' => [], 'requisitos' => []],
        6 => ['codigo' => 'PU-006', 'nombre' => 'Puesto antiguo', 'activo' => false, 'competencias' => [], 'requisitos' => []],
    ];
}

/**
 * Enlaza con dobles los puertos de otros módulos que usan las rutas. Por defecto los colaboradores 10 a 13 tienen vínculo
 * vigente y los departamentos 1 y 2 están activos.
 *
 * @param  array<string, mixed>  $o  puestos, vigentes, departamentos, hoy y falla (Estructura caída)
 * @return array{puestos: PuestosParaRutasFalso, vigentes: ColaboradoresVigentesFalso, departamentos: DepartamentosFalsos}
 */
function rutasFalsos(array $o = []): array
{
    $puestos = new PuestosParaRutasFalso($o['puestos'] ?? puestosDeRutas(), $o['falla'] ?? false);
    $vigentes = new ColaboradoresVigentesFalso($o['vigentes'] ?? [10 => 'Ana Paredes', 11 => 'Luis Mora', 12 => 'Marta Ríos', 13 => 'Pablo Ayala']);
    $departamentos = new DepartamentosFalsos($o['departamentos'] ?? [1 => 'Tecnología', 2 => 'Talento Humano'], [9 => 'Unidad antigua']);

    app()->instance(PuestosParaRutasInterface::class, $puestos);
    app()->instance(ColaboradoresVigentesInterface::class, $vigentes);
    app()->instance(DepartamentosInterface::class, $departamentos);
    app()->instance(NombresDeReferenciaInterface::class, new NombresDeReferenciaFalsos);
    app()->instance(RelojInterface::class, new RelojFijo($o['hoy'] ?? '2026-06-15'));

    return ['puestos' => $puestos, 'vigentes' => $vigentes, 'departamentos' => $departamentos];
}

/**
 * Crea una ruta con sus niveles directamente en la base.
 *
 * @param  list<int>  $puestoIds  un nivel por puesto, en ese orden (el primero es el nivel 1)
 * @param  array<string, mixed>  $cambios  atributos de la ruta
 * @return array{id: int, niveles: list<int>, codigo: string}
 */
function rutaEnBd(array $puestoIds = [1, 2, 3], string $estado = 'Vigente', array $cambios = [], int $tenantId = 1): array
{
    $numero = (int) RutaModel::query()->where('tenant_id', $tenantId)->max('numero') + 1;
    $nombre = $cambios['nombre'] ?? 'Ruta '.$numero;
    $ruta = RutaModel::query()->create($cambios + [
        'tenant_id' => $tenantId,
        'numero' => $numero,
        'codigo' => sprintf('CAR-%04d', $numero),
        'nombre' => $nombre,
        'nombre_clave' => mb_strtolower($nombre),
        'tipo' => 'Especialista',
        'descripcion' => null,
        'departamento_id' => 1,
        'estado' => $estado,
        'version' => 1,
        'created_by' => 1,
        'updated_by' => 1,
    ]);

    $niveles = [];
    foreach (array_values($puestoIds) as $i => $puestoId) {
        $niveles[] = (int) RutaNivelModel::query()->create([
            'tenant_id' => $tenantId,
            'ruta_id' => $ruta->id,
            'orden' => $i + 1,
            'puesto_id' => $puestoId,
            'nombre' => 'Nivel '.($i + 1),
            'experiencia_minima_anios' => $i === 0 ? null : (float) $i,
        ])->id;
    }

    return ['id' => (int) $ruta->id, 'niveles' => $niveles, 'codigo' => (string) $ruta->codigo];
}

/** Crea una inscripción directamente en la base. */
function inscripcionEnBd(int $rutaId, int $colaboradorId, ?int $actualId = null, ?int $objetivoId = null, string $estado = 'Activa', int $tenantId = 1, ?string $motivo = null): RutaInscripcionModel
{
    return RutaInscripcionModel::query()->create([
        'tenant_id' => $tenantId,
        'ruta_id' => $rutaId,
        'colaborador_id' => $colaboradorId,
        'nivel_actual_id' => $actualId,
        'nivel_objetivo_id' => $objetivoId,
        'estado' => $estado,
        'motivo' => $motivo,
        'resuelta_at' => $estado === 'Activa' ? null : now(),
        'created_by' => 1,
    ]);
}

/**
 * Cuerpo válido para crear una ruta de tres niveles.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoRuta(array $cambios = []): array
{
    return $cambios + [
        'nombre' => 'Ruta '.uniqid(),
        'tipo' => 'Especialista',
        'descripcion' => 'Camino de crecimiento',
        'departamento_id' => 1,
        'niveles' => [
            ['puesto_id' => 1, 'nombre' => 'Asistente', 'experiencia_minima_anios' => null],
            ['puesto_id' => 2, 'nombre' => 'Analista', 'experiencia_minima_anios' => 1],
            ['puesto_id' => 3, 'nombre' => 'Senior', 'experiencia_minima_anios' => 3],
        ],
    ];
}

/**
 * Cuerpo para reemplazar una ruta existente conservando sus niveles (con id) y cambiando lo indicado.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoActualizarRuta(int $rutaId, array $cambios = []): array
{
    $ruta = RutaModel::query()->with('niveles')->findOrFail($rutaId);

    return $cambios + [
        'version' => (int) $ruta->version,
        'nombre' => $ruta->nombre,
        'tipo' => $ruta->tipo,
        'descripcion' => $ruta->descripcion,
        'departamento_id' => $ruta->departamento_id,
        'niveles' => $ruta->niveles->map(fn ($n) => [
            'id' => (int) $n->id,
            'orden' => (int) $n->orden,
            'puesto_id' => (int) $n->puesto_id,
            'nombre' => $n->nombre,
            'experiencia_minima_anios' => $n->experiencia_minima_anios,
        ])->all(),
    ];
}

/** @return list<string> acciones del historial de la ruta, de la más antigua a la más reciente */
function accionesDeHistorial(int $rutaId): array
{
    return RutaHistorialModel::query()->where('ruta_id', $rutaId)->orderBy('id')->pluck('accion')->all();
}
