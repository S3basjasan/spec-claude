<?php

use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Services\ReglasDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso as D;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;

function pdiPeriodoDePrueba(): PeriodoDePdi
{
    return new PeriodoDePdi(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-12-31'));
}

function pdiAccionDePrueba(TipoDeAccionPdi $tipo = TipoDeAccionPdi::Curso, string $desde = '2026-07-15', string $hasta = '2026-09-30', ?int $vinculo = null, string $descripcion = 'x'): AccionDePdi
{
    return new AccionDePdi(null, 1, $tipo, $descripcion, null, new DateTimeImmutable($desde), new DateTimeImmutable($hasta), $vinculo, EstadoDeAccionPdi::Pendiente);
}

function pdiCompromisoDePrueba(D $dimension = D::Competencia, ?int $elemento = 1, ?string $descripcion = null, ?string $actual = 'C', ?string $objetivo = 'B', string $desde = '2026-07-01', string $hasta = '2026-11-30', array $acciones = []): CompromisoDePdi
{
    return new CompromisoDePdi(null, 1, $dimension, $dimension->llevaElemento() ? $dimension->value : null, $elemento, $descripcion, $actual, $objetivo, null, new DateTimeImmutable($desde), new DateTimeImmutable($hasta), null, $acciones);
}

function pdiErroresDeCompromiso(array $compromisos, array $hitos = [], array $dimensiones = [D::Competencia, D::Experiencia, D::Meta]): array
{
    try {
        (new ReglasDeCompromiso)->validar(pdiPeriodoDePrueba(), $compromisos, $hitos, $dimensiones);
    } catch (DatosInvalidosDePdi $e) {
        return $e->errores();
    }

    return [];
}

it('acepta un compromiso de competencia completo', function () {
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(acciones: [pdiAccionDePrueba()])], [new HitoDePdi(null, new DateTimeImmutable('2026-09-01'), 'Revisión', EstadoDeHito::Pendiente, null)]))->toBe([]);
});

it('exige elemento en competencias y descripción en experiencia y meta', function () {
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(elemento: null)]))->toHaveKey('compromisos.0.elemento_id')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(D::Experiencia, null, null, null, null)]))->toHaveKey('compromisos.0.descripcion')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(D::Meta, null, '  ', null, null)]))->toHaveKey('compromisos.0.descripcion')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(D::Meta, null, 'Liderar un proyecto', null, null)]))->toBe([]);
});

it('los niveles son A–D, van en pareja y son distintos', function () {
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(actual: 'C', objetivo: 'C')]))->toHaveKey('compromisos.0.nivel_objetivo')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(actual: 'C', objetivo: null)]))->toHaveKey('compromisos.0.nivel_objetivo')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(actual: 'Z', objetivo: 'B')]))->toHaveKey('compromisos.0.nivel_actual')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(actual: null, objetivo: null)]))->toBe([])
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(D::Meta, null, 'Meta', 'C', 'B')]))->toHaveKey('compromisos.0.nivel_actual');
});

it('las fechas de compromisos, acciones e hitos caen dentro del periodo y en orden', function () {
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(desde: '2026-06-01')]))->toHaveKey('compromisos.0.fecha_inicio')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(desde: '2026-10-01', hasta: '2026-09-01')]))->toHaveKey('compromisos.0.fecha_fin')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(acciones: [pdiAccionDePrueba(hasta: '2027-02-01')])]))->toHaveKey('compromisos.0.acciones.0.fecha_inicio')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(acciones: [pdiAccionDePrueba(desde: '2026-09-01', hasta: '2026-08-01')])]))->toHaveKey('compromisos.0.acciones.0.fecha_fin')
        ->and(pdiErroresDeCompromiso([], [new HitoDePdi(null, new DateTimeImmutable('2027-01-01'), 'x', EstadoDeHito::Pendiente, null)]))->toHaveKey('hitos.0.fecha');
});

it('no repite el mismo elemento en dos compromisos del plan', function () {
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(elemento: 1), pdiCompromisoDePrueba(elemento: 1)]))->toHaveKey('compromisos.1.elemento_id')
        ->and(pdiErroresDeCompromiso([pdiCompromisoDePrueba(elemento: 1), pdiCompromisoDePrueba(elemento: 2)]))->toBe([]);
});

it('el vínculo solo se permite en Curso, Mentoring y Codesarrollo', function () {
    foreach ([TipoDeAccionPdi::Curso, TipoDeAccionPdi::Mentoring, TipoDeAccionPdi::Codesarrollo] as $tipo) {
        expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(acciones: [pdiAccionDePrueba($tipo, vinculo: 5)])]))->toBe([]);
    }
    foreach ([TipoDeAccionPdi::Coaching, TipoDeAccionPdi::Autodesarrollo, TipoDeAccionPdi::NuevaResponsabilidad, TipoDeAccionPdi::Otra] as $tipo) {
        expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(acciones: [pdiAccionDePrueba($tipo, vinculo: 5)])]))->toHaveKey('compromisos.0.acciones.0.plan_accion_id');
    }
});

it('rechaza la dimensión Conocimiento cuando no está disponible', function () {
    $errores = pdiErroresDeCompromiso([pdiCompromisoDePrueba(D::Conocimiento, 4)]);

    expect($errores)->toHaveKey('compromisos.0.dimension')
        ->and($errores['compromisos.0.dimension'][0])->toContain('Conocimiento');
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(D::Conocimiento, 4)], [], [D::Competencia, D::Conocimiento, D::Experiencia, D::Meta]))->toBe([]);
});

it('exige descripción en las acciones y en los hitos', function () {
    expect(pdiErroresDeCompromiso([pdiCompromisoDePrueba(acciones: [pdiAccionDePrueba(descripcion: ' ')])]))->toHaveKey('compromisos.0.acciones.0.descripcion')
        ->and(pdiErroresDeCompromiso([], [new HitoDePdi(null, new DateTimeImmutable('2026-09-01'), ' ', EstadoDeHito::Pendiente, null)]))->toHaveKey('hitos.0.descripcion');
});
