<?php

use App\Modules\Desarrollo\Application\Services\DesarrolloService;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    rutasFalsos(['vigentes' => [10 => 'Ana', 11 => 'Luis']]);
    $this->ruta = rutaEnBd([1, 2, 3], 'Vigente', ['nombre' => 'Ruta de datos']);
    [$this->n1, $this->n2, $this->n3] = $this->ruta['niveles'];
});

it('inscripcionesActivas devuelve la ruta, el nivel actual y el objetivo con puesto y experiencia mínima', function () {
    $inscripcion = inscripcionEnBd($this->ruta['id'], 10, $this->n1, $this->n3);

    $filas = DesarrolloFacade::inscripcionesActivas(1, 10);

    expect($filas)->toBe([[
        'inscripcion_id' => $inscripcion->id,
        'ruta_id' => $this->ruta['id'],
        'ruta_codigo' => $this->ruta['codigo'],
        'ruta_nombre' => 'Ruta de datos',
        'ruta_tipo' => 'Especialista',
        'nivel_actual' => ['id' => $this->n1, 'orden' => 1, 'puesto_id' => 1, 'nombre' => 'Nivel 1'],
        'nivel_objetivo' => ['id' => $this->n3, 'orden' => 3, 'puesto_id' => 3, 'nombre' => 'Nivel 3', 'experiencia_minima_anios' => 2.0],
    ]]);
});

it('inscripcionesActivas admite niveles sin definir y no devuelve las Finalizadas ni las Retiradas', function () {
    inscripcionEnBd($this->ruta['id'], 10, null, null);
    $otra = rutaEnBd([1, 2], 'Vigente');
    inscripcionEnBd($otra['id'], 10, estado: 'Retirada', motivo: 'Cambio');
    inscripcionEnBd($otra['id'], 10, $otra['niveles'][1], null, 'Finalizada');

    $filas = DesarrolloFacade::inscripcionesActivas(1, 10);

    expect($filas)->toHaveCount(1)->and($filas[0]['nivel_actual'])->toBeNull()->and($filas[0]['nivel_objetivo'])->toBeNull();
});

it('inscripcionesActivas no mezcla colaboradores ni tenants', function () {
    inscripcionEnBd($this->ruta['id'], 10, $this->n1, $this->n2);
    inscripcionEnBd($this->ruta['id'], 11, $this->n1, $this->n2);
    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    inscripcionEnBd($ajena['id'], 10, tenantId: 2);

    expect(DesarrolloFacade::inscripcionesActivas(1, 10))->toHaveCount(1)
        ->and(DesarrolloFacade::inscripcionesActivas(1, 99))->toBe([]);
});

it('inscripcionesActivas retira y no devuelve las inscripciones de quien ya no tiene vínculo vigente', function () {
    inscripcionEnBd($this->ruta['id'], 12, $this->n1, $this->n2); // 12 no es vigente

    expect(DesarrolloFacade::inscripcionesActivas(1, 12))->toBe([]);
    expect(RutaInscripcionModel::query()->where('colaborador_id', 12)->first())->estado->toBe('Retirada');
});

it('obtenerNivel devuelve ruta, orden, puesto y experiencia mínima, y null si no existe o es de otro tenant', function () {
    expect(DesarrolloFacade::obtenerNivel(1, $this->n2))->toBe([
        'nivel_id' => $this->n2, 'ruta_id' => $this->ruta['id'], 'ruta_nombre' => 'Ruta de datos', 'orden' => 2, 'puesto_id' => 2, 'nombre' => 'Nivel 2', 'experiencia_minima_anios' => 1.0,
    ]);
    expect(DesarrolloFacade::obtenerNivel(1, $this->n1)['experiencia_minima_anios'])->toBeNull()
        ->and(DesarrolloFacade::obtenerNivel(1, 99999))->toBeNull()
        ->and(DesarrolloFacade::obtenerNivel(2, $this->n2))->toBeNull();
});

it('la respuesta no incluye competencias ni conocimientos: el consumidor los lee de Estructura', function () {
    inscripcionEnBd($this->ruta['id'], 10, $this->n1, $this->n3);

    $json = json_encode([DesarrolloFacade::inscripcionesActivas(1, 10), DesarrolloFacade::obtenerNivel(1, $this->n3)]);

    expect($json)->not->toContain('competencias')->not->toContain('conocimientos');
});

it('los métodos que ya existían en la Facade siguen disponibles', function () {
    expect(method_exists(DesarrolloService::class, 'getActividades'))->toBeTrue()
        ->and(method_exists(DesarrolloService::class, 'getOfertaPorCompetencia'))->toBeTrue();
});
