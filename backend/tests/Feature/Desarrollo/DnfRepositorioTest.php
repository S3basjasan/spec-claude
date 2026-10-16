<?php

use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfColaboradorModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;
use Illuminate\Support\Facades\DB;

function repositorioDnf(): DnfRepositoryInterface
{
    return app(DnfRepositoryInterface::class);
}

it('asigna códigos DNF-0001, DNF-0002 por tenant', function () {
    $a = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    $b = dnfDeEjemplo([10 => [[1, 'A', 'B']]]);
    tenantDos();
    $otro = dnfDeEjemplo([20 => [[1, 'A', 'B']]], tenantId: 2);

    expect($a->codigo)->toBe('DNF-0001')
        ->and($b->codigo)->toBe('DNF-0002')
        ->and($otro->codigo)->toBe('DNF-0001');
});

it('termina con un código único cuando el siguiente número esperado ya está ocupado', function () {
    // Dos filas con códigos 0001 y 0003: count()+1 = 3 colisiona y el reintento debe asignar 0004.
    foreach (['DNF-0001', 'DNF-0003'] as $codigo) {
        DB::table('dnf')->insert([
            'tenant_id' => 1, 'codigo' => $codigo, 'alcance' => 'Individual', 'competencias_omitidas' => 0,
            'created_by' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $nueva = dnfDeEjemplo([11 => [[1, 'A', 'C']]]);

    expect($nueva->codigo)->toBe('DNF-0004');
});

it('guarda y lee una DNF con sus resultados y detalles tal como se calcularon', function () {
    $dnf = dnfDeEjemplo([
        10 => [[1, 'A', 'C'], [2, 'B', null, 'Especifica'], [3, 'C', 'B']],
        11 => [[1, 'A', 'D']],
    ], 'Departamento', 5, omitidas: 2);

    $leida = repositorioDnf()->obtener(1, $dnf->id);

    expect($leida->codigo)->toBe($dnf->codigo)
        ->and($leida->departamentoId)->toBe(5)
        ->and($leida->competenciasOmitidas)->toBe(2)
        ->and($leida->resultados)->toHaveCount(2)
        ->and($leida->cantidadBrechas())->toBe(2)
        ->and($leida->sinEvaluacion())->toBe(1)
        ->and($leida->prioridadMasAlta())->toBe(PrioridadBrecha::Critica);

    $primero = $leida->resultados[0]->brechas[0];
    expect($primero->diferencia)->toBe(-2)
        ->and($primero->brechaPorcentaje)->toBe(50.0)
        ->and($primero->prioridad)->toBe(PrioridadBrecha::Alta)
        ->and($leida->resultados[0]->brechas[1]->estado)->toBe(EstadoBrecha::SinEvaluacion);
});

it('filtra el resultado de un colaborador y devuelve null si no pertenece a la DNF', function () {
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']], 11 => [[1, 'A', 'D']]], 'Departamento', 5);

    expect(repositorioDnf()->obtener(1, $dnf->id, 11)->resultados)->toHaveCount(1)
        ->and(repositorioDnf()->obtener(1, $dnf->id, 99))->toBeNull()
        ->and(repositorioDnf()->obtener(2, $dnf->id))->toBeNull();
});

it('rechaza actualizar y eliminar las tres tablas desde el modelo', function () {
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    $modelo = DnfModel::query()->findOrFail($dnf->id);
    $colaborador = DnfColaboradorModel::query()->firstOrFail();
    $detalle = DnfDetalleModel::query()->firstOrFail();

    expect(fn () => $modelo->update(['codigo' => 'DNF-9999']))->toThrow(LogicException::class)
        ->and(fn () => $modelo->delete())->toThrow(LogicException::class)
        ->and(fn () => $colaborador->update(['puesto_id' => 9]))->toThrow(LogicException::class)
        ->and(fn () => $colaborador->delete())->toThrow(LogicException::class)
        ->and(fn () => $detalle->update(['nivel_actual' => 'A']))->toThrow(LogicException::class)
        ->and(fn () => $detalle->delete())->toThrow(LogicException::class);
});

it('no permite borrar una DNF con resultados por la clave foránea', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    expect(fn () => DB::table('dnf')->delete())->toThrow(Exception::class);
});

it('asigna el plan una sola vez', function () {
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    $momento = new DateTimeImmutable('2026-10-15 14:00:00');

    $primera = repositorioDnf()->asignarPlan(1, $dnf->id, 31, 7, $momento);
    $segunda = repositorioDnf()->asignarPlan(1, $dnf->id, 32, 8, $momento);
    $otroTenant = repositorioDnf()->asignarPlan(2, $dnf->id, 33, 8, $momento);

    $leida = repositorioDnf()->obtener(1, $dnf->id);
    expect($primera)->toBeTrue()->and($segunda)->toBeFalse()->and($otroTenant)->toBeFalse()
        ->and($leida->planFormacionId)->toBe(31)
        ->and($leida->planGeneradoPor)->toBe(7)
        ->and($leida->planGeneradoEn?->format('Y-m-d H:i'))->toBe('2026-10-15 14:00')
        ->and($leida->tienePlan())->toBeTrue();
});

it('lista el historial con cantidad de brechas y prioridad más alta, sin N+1', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', 'B']]]);
    dnfDeEjemplo([10 => [[1, 'A', 'D']], 11 => [[1, 'B', 'C']]], 'Departamento', 5);
    dnfDeEjemplo([12 => [[1, 'A', 'A']]]);

    DB::enableQueryLog();
    $pagina = repositorioDnf()->listar(1, new FiltroDnf);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($pagina->total)->toBe(3)
        ->and($pagina->items)->toHaveCount(3)
        ->and($consultas)->toBeLessThanOrEqual(2)
        ->and($pagina->items[0]->codigo)->toBe('DNF-0003')
        ->and($pagina->items[0]->cantidadBrechas)->toBe(0)
        ->and($pagina->items[0]->prioridadMasAlta)->toBeNull()
        ->and($pagina->items[1]->cantidadBrechas)->toBe(2)
        ->and($pagina->items[1]->prioridadMasAlta)->toBe(PrioridadBrecha::Critica)
        ->and($pagina->items[1]->colaboradores)->toBe(2)
        ->and($pagina->items[2]->colaboradorId)->toBe(10)
        ->and($pagina->items[2]->prioridadMasAlta)->toBe(PrioridadBrecha::Alta);
});

it('filtra el historial por fechas, colaborador, departamento y prioridad más alta', function () {
    $antigua = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    DnfModel::query()->whereKey($antigua->id)->toBase()->update(['created_at' => '2026-01-10 10:00:00']);
    dnfDeEjemplo([10 => [[1, 'A', 'D']], 11 => [[1, 'B', 'C']]], 'Departamento', 5);
    dnfDeEjemplo([12 => [[1, 'A', 'B']]]);
    $repo = repositorioDnf();

    expect($repo->listar(1, new FiltroDnf(fechaHasta: '2026-02-01'))->total)->toBe(1)
        ->and($repo->listar(1, new FiltroDnf(fechaDesde: '2026-02-01'))->total)->toBe(2)
        ->and($repo->listar(1, new FiltroDnf(colaboradorId: 11))->total)->toBe(1)
        ->and($repo->listar(1, new FiltroDnf(colaboradorId: 10))->total)->toBe(2)
        ->and($repo->listar(1, new FiltroDnf(departamentoId: 5))->total)->toBe(1)
        ->and($repo->listar(1, new FiltroDnf(prioridad: PrioridadBrecha::Critica))->total)->toBe(1)
        ->and($repo->listar(1, new FiltroDnf(prioridad: PrioridadBrecha::Alta))->total)->toBe(1)
        ->and($repo->listar(1, new FiltroDnf(prioridad: PrioridadBrecha::Baja))->total)->toBe(0)
        ->and($repo->listar(2, new FiltroDnf)->total)->toBe(0);
});

it('pagina el historial', function () {
    foreach (range(1, 5) as $i) {
        dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    }

    $pagina = repositorioDnf()->listar(1, new FiltroDnf(pagina: 2, porPagina: 2));

    expect($pagina->total)->toBe(5)->and($pagina->items)->toHaveCount(2)->and($pagina->ultimaPagina())->toBe(3)
        ->and($pagina->items[0]->codigo)->toBe('DNF-0003');
});

it('encuentra la DNF más reciente que evaluó al colaborador, individual o masiva', function () {
    dnfDeEjemplo([10 => [[1, 'A', 'D']]]);
    $masiva = dnfDeEjemplo([10 => [[1, 'A', 'C']], 11 => [[1, 'A', 'B']]], 'Departamento', 5);

    $reciente = repositorioDnf()->masRecienteDeColaborador(1, 10);

    expect($reciente->id)->toBe($masiva->id)
        ->and($reciente->resultados)->toHaveCount(1)
        ->and($reciente->resultados[0]->colaboradorId)->toBe(10)
        ->and(repositorioDnf()->masRecienteDeColaborador(1, 99))->toBeNull()
        ->and(repositorioDnf()->masRecienteDeColaborador(2, 10))->toBeNull();
});
