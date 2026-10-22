<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Support\Facades\DB;

it('lista ordenado por código y devuelve solo los datos de la tarjeta', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    actuarComoAdmin();

    $respuesta = $this->getJson('/api/competencias')->assertOk();

    expect($respuesta->json('data.*.codigo'))->toBe(['COMP-0001', 'COMP-0002'])
        ->and($respuesta->json('data.*.nombre'))->toBe(['Zeta', 'Alfa'])
        ->and($respuesta->json('data.0'))->toHaveKeys(['id', 'codigo', 'nombre', 'tipo', 'estado', 'completa'])
        ->and($respuesta->json('data.0'))->not->toHaveKeys(['descripcion', 'grados', 'preguntas', 'comportamientos'])
        ->and($respuesta->json('meta.total'))->toBe(2)
        ->and($respuesta->json('meta.per_page'))->toBe(12);
});

it('muestra al administrador las activas y las inactivas, y a los demás solo las activas', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Activa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);

    actuarComoAdmin();
    expect($this->getJson('/api/competencias')->json('data.*.nombre'))->toBe(['Activa', 'Retirada']);
    expect($this->getJson('/api/competencias?estado=Inactivo')->json('data.*.nombre'))->toBe(['Retirada']);
    expect($this->getJson('/api/competencias?estado=Activo')->json('data.*.nombre'))->toBe(['Activa']);

    actuarComoUsuario();
    expect($this->getJson('/api/competencias')->json('data.*.nombre'))->toBe(['Activa']);
    expect($this->getJson('/api/competencias?estado=Inactivo')->json('data.*.nombre'))->toBe(['Activa']);
    expect($this->getJson('/api/competencias?estado=Todos')->json('data.*.nombre'))->toBe(['Activa']);
});

it('filtra por nombre sin distinguir mayúsculas ni tildes y por tipo', function () {
    CompetenciaModel::factory()->cardinal()->create(['nombre' => 'Liderazgo']);
    CompetenciaModel::factory()->especifica()->create(['nombre' => 'Análisis de datos']);
    actuarComoAdmin();

    expect($this->getJson('/api/competencias?q=lidérazgo')->json('data.*.nombre'))->toBe(['Liderazgo'])
        ->and($this->getJson('/api/competencias?q=ANALISIS')->json('data.*.nombre'))->toBe(['Análisis de datos'])
        ->and($this->getJson('/api/competencias?tipo=Especifica')->json('data.*.nombre'))->toBe(['Análisis de datos'])
        ->and($this->getJson('/api/competencias?q=lider&tipo=Especifica')->json('data'))->toBe([]);
});

it('pagina con 12 por página por defecto y acepta entre 1 y 50', function () {
    CompetenciaModel::factory()->count(14)->create();
    actuarComoAdmin();

    $primera = $this->getJson('/api/competencias')->assertOk();
    expect($primera->json('data'))->toHaveCount(12)
        ->and($primera->json('meta.total'))->toBe(14);
    expect($this->getJson('/api/competencias?page=2')->json('data'))->toHaveCount(2);

    $this->getJson('/api/competencias?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/competencias?per_page=0')->assertUnprocessable();
    expect($this->getJson('/api/competencias?per_page=50')->json('data'))->toHaveCount(14);
});

it('indica en el listado si la competencia está completa', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Completa']);
    CompetenciaModel::factory()->incompleta()->create(['nombre' => 'Heredada']);
    actuarComoAdmin();

    $respuesta = $this->getJson('/api/competencias')->assertOk();

    expect($respuesta->json('data.*.completa'))->toBe([true, false]);
});

it('devuelve el detalle con escala, grados ordenados con sus comportamientos y las preguntas', function () {
    $competencia = CompetenciaModel::factory()->conCategorias()->create(['nombre' => 'Comunicación']);
    actuarComoAdmin();

    $detalle = $this->getJson("/api/competencias/{$competencia->id}")->assertOk()->json('data');

    expect($detalle['codigo'])->toBe('COMP-0001')
        ->and($detalle['version'])->toBe(1)
        ->and($detalle['completa'])->toBeTrue()
        ->and($detalle['faltantes'])->toBe([])
        ->and($detalle['escala']['nombre'])->toBe('Categorías')
        ->and($detalle['categorias'])->toBe(['Maestría', 'Avanzado', 'Competente', 'Inicial'])
        ->and(array_column($detalle['grados'], 'orden'))->toBe([1, 2, 3, 4])
        ->and(array_column($detalle['grados'], 'etiqueta'))->toBe(['Maestría', 'Avanzado', 'Competente', 'Inicial'])
        ->and($detalle['grados'][0]['comportamientos'])->toHaveCount(4)
        ->and($detalle['preguntas'])->toHaveCount(4)
        ->and($detalle)->toHaveKey('auditoria');
});

it('permite consultar una competencia inactiva a cualquier usuario y omite la auditoría a quien no es administrador', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoUsuario();

    $detalle = $this->getJson("/api/competencias/{$inactiva->id}")->assertOk()->json('data');

    expect($detalle['estado'])->toBe('Inactivo')
        ->and($detalle)->not->toHaveKey('auditoria')
        ->and($detalle['grados'])->toHaveCount(4);
});

it('indica lo que falta en una competencia incompleta', function () {
    $incompleta = CompetenciaModel::factory()->incompleta()->create();
    actuarComoAdmin();

    $detalle = $this->getJson("/api/competencias/{$incompleta->id}")->assertOk()->json('data');

    expect($detalle['completa'])->toBeFalse()
        ->and($detalle['faltantes'])->toBe(['comportamientos', 'preguntas']);
});

it('responde 404 si no existe y 401 sin sesión', function () {
    $this->getJson('/api/competencias')->assertUnauthorized();
    $this->getJson('/api/competencias/1')->assertUnauthorized();

    actuarComoAdmin();
    $this->getJson('/api/competencias/99999')->assertNotFound();
});

it('lista y consulta sin consultas repetidas por cada competencia', function () {
    CompetenciaModel::factory()->count(10)->create();
    actuarComoAdmin();

    DB::enableQueryLog();
    $this->getJson('/api/competencias?per_page=10')->assertOk();
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Autenticación + conteo + competencias + 4 relaciones eager load: no crece con el número de competencias.
    expect($consultas)->toBeLessThan(15);
});
