<?php

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaCursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\TipoFuncion;
use App\Modules\Estructura\Domain\ValueObjects\TipoRequisito;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Desarrollo\BrechasActivasFalsas;
use Tests\Support\Desarrollo\NivelesDeColaboradorFalso;
use Tests\TestCase;

require_once __DIR__.'/GestionPersonalHelpers.php';
require_once __DIR__.'/MovimientosHelpers.php';
require_once __DIR__.'/OffboardingHelpers.php';
require_once __DIR__.'/CompensacionesHelpers.php';
require_once __DIR__.'/Support/Desarrollo/DnfHelpers.php';
require_once __DIR__.'/Support/Desarrollo/PlanHelpers.php';
require_once __DIR__.'/Support/Desarrollo/MicroHelpers.php';
require_once __DIR__.'/Support/Desarrollo/ActividadHelpers.php';
require_once __DIR__.'/Support/Desarrollo/ReportesHelpers.php';
require_once __DIR__.'/Support/Desarrollo/RutasHelpers.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function actuarComoAdmin(): void
{
    Sanctum::actingAs(
        UserModel::factory()->admin()->create(),
        ['admin'],
    );
}

function actuarComoUsuario(): void
{
    Sanctum::actingAs(
        UserModel::factory()->create(),
        ['user'],
    );
}

/**
 * Id de una escala del catálogo (`Letras`, `Números` o `Categorías`).
 */
function escalaId(string $nombre = 'Letras'): int
{
    return (int) TipoGradoModel::query()->where('nombre', $nombre)->value('id');
}

/**
 * Cuerpo válido para crear una competencia completa: escala Letras, 4 grados, 16 comportamientos y 4 preguntas.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosCompetencia(array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Innovación',
        'descripcion' => 'Generar ideas nuevas y llevarlas a la práctica.',
        'tipo' => 'Cardinal',
        'tipo_grado_id' => escalaId('Letras'),
        'grados' => [
            'Impulsa la innovación en toda la organización.',
            'Propone y lidera mejoras sistemáticas.',
            'Propone mejoras en su trabajo.',
            'Aplica las ideas que otros proponen.',
        ],
        'comportamientos' => [
            ['A1', 'A2', 'A3', 'A4'],
            ['B1', 'B2', 'B3', 'B4'],
            ['C1', 'C2', 'C3', 'C4'],
            ['D1', 'D2', 'D3', 'D4'],
        ],
        'preguntas' => [
            'Cuéntame de una idea que llevaste a la práctica.',
            '¿Cómo fomentas ideas nuevas en tu equipo?',
            'Describe una mejora que propusiste.',
            '¿Cómo reaccionas ante una idea que no funciona?',
        ],
    ], $cambios);
}

/**
 * Cuerpo válido para crear o actualizar un puesto: una competencia, dos funciones y dos requisitos.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosPuesto(int $competenciaId, array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Analista de datos',
        'descripcion' => 'Convierte datos en reportes para decidir mejor.',
        'mision' => 'Transformar los datos de la organización en información confiable.',
        'funciones' => [
            ['descripcion' => 'Recopilar y consolidar datos.', 'tipo' => 'Esencial'],
            ['descripcion' => 'Documentar indicadores.', 'tipo' => 'Complementaria'],
        ],
        'requisitos' => [
            ['tipo' => 'Formacion', 'descripcion' => 'Título de tercer nivel afín.'],
            ['tipo' => 'Experiencia', 'descripcion' => '2 años en análisis de datos.'],
        ],
        'competencias' => [
            ['competencia_id' => $competenciaId, 'grado_requerido' => 'B'],
        ],
    ], $cambios);
}

/**
 * Crea un puesto válido con las listas mínimas y, opcionalmente, posiciones de cada estado.
 *
 * @param  array<string, mixed>  $atributos  Atributos del puesto (nombre, estado…).
 */
function puestoDe(array $atributos = [], int $ocupadas = 0, int $vacantes = 0, int $suprimidas = 0): PuestoModel
{
    return PuestoModel::factory()->conPosiciones($ocupadas, $vacantes, $suprimidas)->create($atributos);
}

/**
 * Crea posiciones de un puesto con el estado indicado.
 */
function posicionesDe(PuestoModel|int $puesto, string $estado, int $cantidad = 1): void
{
    $id = $puesto instanceof PuestoModel ? $puesto->id : $puesto;

    for ($i = 0; $i < $cantidad; $i++) {
        PosicionModel::query()->create(['puesto_id' => $id, 'estado' => $estado]);
    }
}

/**
 * Contenido de un puesto como value object de dominio (para los casos de uso, sin HTTP).
 */
function datosDePuesto(string $nombre = 'Analista', int $competenciaId = 1, string $grado = 'B'): DatosPuesto
{
    return new DatosPuesto(
        "  $nombre  ",
        'Descripción',
        'Misión',
        [new FuncionPuesto(1, 'Función esencial', TipoFuncion::Esencial)],
        [new RequisitoPuesto(TipoRequisito::Formacion, 'Título'), new RequisitoPuesto(TipoRequisito::Experiencia, '2 años')],
        [new AsignacionDeCompetencia($competenciaId, $grado)],
    );
}

/**
 * Cuerpo de un PUT que repite exactamente el contenido actual del puesto (más los cambios indicados).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoDelPuesto(PuestoModel $puesto, array $cambios = []): array
{
    $puesto = $puesto->fresh(['funciones', 'requisitos', 'competenciasAsignadas']);

    return array_replace([
        'nombre' => $puesto->nombre,
        'descripcion' => $puesto->descripcion,
        'mision' => $puesto->mision,
        'funciones' => $puesto->funciones->map(fn ($f) => ['descripcion' => $f->descripcion, 'tipo' => $f->tipo])->all(),
        'requisitos' => $puesto->requisitos->map(fn ($r) => ['tipo' => $r->tipo, 'descripcion' => $r->descripcion])->all(),
        'competencias' => $puesto->competenciasAsignadas->map(fn ($c) => ['competencia_id' => $c->competencia_id, 'grado_requerido' => $c->grado_requerido])->all(),
        'version' => $puesto->version,
    ], $cambios);
}

/**
 * Configura la vista formativa de una competencia directamente en base de datos (área, cursos y micro-contenidos).
 *
 * @param  array<int, int>  $cursoIds
 * @param  array<int, int>  $microIds
 */
function configurarFormativa(int $competenciaId, ?int $areaId = null, array $cursoIds = [], array $microIds = [], int $version = 1, ?int $actor = null): void
{
    CompetenciaFormativaModel::query()->create([
        'competencia_id' => $competenciaId,
        'area_formativa_id' => $areaId,
        'version' => $version,
        'updated_by' => $actor,
    ]);

    foreach ($cursoIds as $id) {
        CompetenciaCursoModel::query()->create(['competencia_id' => $competenciaId, 'curso_id' => $id, 'created_by' => $actor]);
    }
    foreach ($microIds as $id) {
        CompetenciaMicrocontenidoModel::query()->create(['competencia_id' => $competenciaId, 'microcontenido_id' => $id, 'created_by' => $actor]);
    }
}

/**
 * Cuerpo de un PUT del catálogo formativo (por defecto, sin área ni recursos y versión 0).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoFormativo(array $cambios = []): array
{
    return array_replace([
        'area_formativa_id' => null,
        'curso_ids' => [],
        'microcontenido_ids' => [],
        'version' => 0,
    ], $cambios);
}

/**
 * Sustituye en el contenedor el puerto de niveles por uno con los datos dados.
 *
 * @param  array<int, string>  $niveles  competencia_id => nivel
 */
function conNiveles(array $niveles, bool $falla = false): NivelesDeColaboradorFalso
{
    $falso = new NivelesDeColaboradorFalso($niveles, $falla);
    app()->instance(NivelesDeColaboradorInterface::class, $falso);

    return $falso;
}

/**
 * Sustituye en el contenedor el puerto de brechas por uno con los datos dados (o que falla).
 *
 * @param  array<int, array{competencia_id: int, nivel_requerido: string}>  $brechas
 */
function conBrechas(array $brechas, bool $falla = false): BrechasActivasFalsas
{
    $falso = new BrechasActivasFalsas($brechas, $falla);
    app()->instance(BrechasActivasInterface::class, $falso);

    return $falso;
}

function actuarComoColaborador(): UserModel
{
    $usuario = UserModel::factory()->create();
    Sanctum::actingAs($usuario, ['user']);

    return $usuario;
}

/**
 * Tipo del catalogo de tipos de unidad (lo crea si no existe en la base de pruebas).
 */
function tipoDeUnidad(string $nombre = 'Departamento'): TipoUnidadModel
{
    return TipoUnidadModel::query()->firstOrCreate(['nombre' => $nombre]);
}

/**
 * Crea una unidad valida. Por defecto cuelga de la raiz activa (la crea si no existe).
 *
 * @param  array<string, mixed>  $atributos  Atributos de la unidad (nombre, estado, unidad_padre_id...).
 */
function unidadDe(array $atributos = []): UnidadModel
{
    return UnidadModel::factory()->create($atributos);
}

/**
 * Crea posiciones asignadas a una unidad con el estado indicado (necesitan un puesto).
 */
function posicionesDeUnidad(UnidadModel|int $unidad, string $estado, int $cantidad = 1): void
{
    $id = $unidad instanceof UnidadModel ? $unidad->id : $unidad;
    $puesto = PuestoModel::query()->first() ?? puestoDe();

    for ($i = 0; $i < $cantidad; $i++) {
        PosicionModel::query()->create(['puesto_id' => $puesto->id, 'unidad_organizacional_id' => $id, 'estado' => $estado]);
    }
}

/**
 * Cuerpo valido para crear o actualizar una unidad (por defecto, bajo la unidad padre indicada).
 * El tipo de unidad no se envía: lo calcula el sistema a partir de la unidad padre (`GuardarUnidadRequest` lo prohíbe).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosUnidad(?int $unidadPadreId, array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Reclutamiento',
        'unidad_padre_id' => $unidadPadreId,
    ], $cambios);
}

/**
 * Cuerpo de un PUT con el contenido actual de la unidad (más los cambios indicados).
 * El tipo de unidad no se envía: lo calcula el sistema a partir de la unidad padre (`GuardarUnidadRequest` lo prohíbe).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoDeUnidad(UnidadModel $unidad, array $cambios = []): array
{
    $unidad = $unidad->fresh();

    return array_replace([
        'nombre' => $unidad->nombre,
        'unidad_padre_id' => $unidad->unidad_padre_id,
        'version' => $unidad->version,
    ], $cambios);
}

function repositorioDeUnidades(): UnidadRepositoryInterface
{
    return app(UnidadRepositoryInterface::class);
}

/**
 * Arma el árbol de ejemplo: raíz → (Talento humano → Reclutamiento) y (Tecnología → Desarrollo).
 *
 * @return array<string, UnidadModel>
 */
function arbolDeEjemplo(): array
{
    $raiz = UnidadModel::factory()->raiz()->create(['nombre' => 'Gerencia general']);
    $talento = UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Talento humano']);
    $tecnologia = UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Tecnología']);
    $reclutamiento = UnidadModel::factory()->conPadre($talento)->create(['nombre' => 'Reclutamiento']);
    $desarrollo = UnidadModel::factory()->conPadre($tecnologia)->create(['nombre' => 'Desarrollo']);

    return compact('raiz', 'talento', 'tecnologia', 'reclutamiento', 'desarrollo');
}
