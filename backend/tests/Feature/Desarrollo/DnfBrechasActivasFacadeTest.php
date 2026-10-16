<?php

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Infrastructure\Adapters\BrechasActivasDesdeDnf;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;

it('brechasActivas devuelve las «Con brecha» de la DNF más reciente del colaborador', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'D']]]);
    dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', 'B'], [3, 'B', null], [4, 'B', 'D']]]);

    $brechas = DesarrolloFacade::brechasActivas(1, 10);

    expect($brechas)->toBe([
        ['competencia_id' => 1, 'nivel_requerido' => 'A', 'nivel_actual' => 'C', 'diferencia' => -2, 'prioridad' => 'Alta'],
        ['competencia_id' => 4, 'nivel_requerido' => 'B', 'nivel_actual' => 'D', 'diferencia' => -2, 'prioridad' => 'Alta'],
    ]);
});

it('brechasActivas devuelve [] si no hay DNF, si es de otro tenant o si el colaborador no fue evaluado', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    expect(DesarrolloFacade::brechasActivas(1, 99))->toBe([])
        ->and(DesarrolloFacade::brechasActivas(2, 10))->toBe([]);
});

it('devuelve solo arreglos, nunca modelos ni entidades', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $brechas = DesarrolloFacade::brechasActivas(1, 10);

    expect($brechas[0])->toBeArray()->and(array_keys($brechas[0]))->toBe(['competencia_id', 'nivel_requerido', 'nivel_actual', 'diferencia', 'prioridad']);
});

it('el puerto del catálogo formativo usa ahora la DNF (adaptador definitivo)', function () {
    expect(app(BrechasActivasInterface::class))->toBeInstanceOf(BrechasActivasDesdeDnf::class);
});

it('el catálogo formativo recomienda las competencias con brecha de la DNF del colaborador', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Pensamiento analítico']);
    $curso = CursoModel::factory()->create();
    configurarFormativa($competencia->id, null, [$curso->id]);
    $ficha = fichaDe([], 1);
    $usuario = actuarComoUsuarioDeTenant(1);
    ColaboradorCuentaModel::query()->create(['tenant_id' => 1, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id, 'puede_consultar_ficha' => true]);
    dnfDeEjemplo([(int) $ficha->id => [[$competencia->id, 'A', 'C']]]);

    $datos = $this->getJson('/api/catalogo-formativo/recomendados')->assertOk()->json('data');

    expect($datos)->toHaveCount(1)
        ->and($datos[0]['competencia_id'])->toBe($competencia->id)
        ->and($datos[0]['nivel_requerido'])->toBe('A');
});

it('un usuario sin ficha asociada no tiene brechas activas en el catálogo', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    $usuario = actuarComoUsuarioDeTenant(1);

    expect(app(BrechasActivasInterface::class)->brechasDe($usuario->id))->toBe([]);
});
