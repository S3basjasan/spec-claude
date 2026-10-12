<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Factories\RegistroComportamientosFactory;
use Database\Factories\RegistroPreguntasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
 * Cuerpo válido para crear o actualizar una competencia.
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
        'grados' => [
            'A' => 'Impulsa la innovación en toda la organización.',
            'B' => 'Propone y lidera mejoras sistemáticas.',
            'C' => 'Propone mejoras en su trabajo.',
            'D' => 'Aplica las ideas que otros proponen.',
        ],
    ], $cambios);
}

/**
 * Cuerpo válido para crear un registro de comportamientos (uno o dos textos por grado).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosComportamientos(int $competenciaId, array $cambios = []): array
{
    return array_replace([
        'competencia_id' => $competenciaId,
        'comportamientos' => [
            'A' => ['Lidera con el ejemplo.', 'Inspira a otros.'],
            'B' => ['Coordina al equipo.'],
            'C' => ['Colabora con su equipo.', 'Cumple sus compromisos.'],
            'D' => ['Sigue las indicaciones.'],
        ],
    ], $cambios);
}

/**
 * Crea una competencia con su registro de comportamientos (cantidades por grado indicadas).
 *
 * @param  array<string, int>  $conteos
 * @param  array<string, mixed>  $competencia  Atributos de la competencia (nombre, estado…).
 */
function registroDe(array $competencia = [], array $conteos = ['A' => 2, 'B' => 2, 'C' => 2, 'D' => 2], ?int $actor = null): Model
{
    $modelo = CompetenciaModel::factory()->create($competencia);

    RegistroComportamientosFactory::new()
        ->conComportamientos($conteos)
        ->create(['competencia_id' => $modelo->id, 'created_by' => $actor]);

    return $modelo;
}

/**
 * Cuerpo válido para crear un registro de preguntas (cuatro textos distintos).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosPreguntas(int $competenciaId, array $cambios = []): array
{
    return array_replace([
        'competencia_id' => $competenciaId,
        'preguntas' => [
            'Cuéntame de una meta que lograste.',
            '¿Cómo organizas tu trabajo?',
            'Describe un error y qué aprendiste.',
            '¿Cómo trabajas bajo presión?',
        ],
    ], $cambios);
}

/**
 * Crea una competencia con su registro de cuatro preguntas.
 *
 * @param  array<string, mixed>  $competencia  Atributos de la competencia (nombre, estado…).
 */
function registroPreguntasDe(array $competencia = [], ?int $actor = null): Model
{
    $modelo = CompetenciaModel::factory()->create($competencia);

    RegistroPreguntasFactory::new()
        ->conPreguntas()
        ->create(['competencia_id' => $modelo->id, 'created_by' => $actor]);

    return $modelo;
}
