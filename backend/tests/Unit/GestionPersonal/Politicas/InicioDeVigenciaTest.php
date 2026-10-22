<?php

use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\InicioEnElPasado;
use App\Modules\GestionPersonal\Domain\Services\InicioDeVigencia;

beforeEach(fn () => $this->inicio = new InicioDeVigencia);

function ahoraUtc(string $texto = '2026-03-01 12:00:00'): DateTimeImmutable
{
    return new DateTimeImmutable($texto, new DateTimeZone('UTC'));
}

it('inmediato usa el instante del servidor', function () {
    $r = $this->inicio->resolver(['modo' => 'inmediato'], ahoraUtc(), 'America/Guayaquil');

    expect($r['utc']->format('Y-m-d H:i:s'))->toBe('2026-03-01 12:00:00')->and($r['local'])->toBe('2026-03-01T07:00');
});

it('programado interpreta la hora local en la zona de la organización', function () {
    $r = $this->inicio->resolver(['modo' => 'programado', 'fecha_hora_local' => '2026-04-01T08:00'], ahoraUtc(), 'America/Guayaquil');

    expect($r['utc']->format('Y-m-d H:i'))->toBe('2026-04-01 13:00')->and($r['local'])->toBe('2026-04-01T08:00');
});

it('rechaza un inicio programado en el pasado o igual al momento actual', function () {
    expect(fn () => $this->inicio->resolver(['modo' => 'programado', 'fecha_hora_local' => '2026-03-01T06:59'], ahoraUtc(), 'America/Guayaquil'))
        ->toThrow(InicioEnElPasado::class)
        ->and(fn () => $this->inicio->resolver(['modo' => 'programado', 'fecha_hora_local' => '2026-03-01T07:00'], ahoraUtc(), 'America/Guayaquil'))
        ->toThrow(InicioEnElPasado::class);
});

it('un horario inexistente por cambio de hora pasa al primer instante válido posterior', function () {
    // 8 de marzo de 2026: en Nueva York 02:00 → 03:00; 02:30 no existe.
    $r = $this->inicio->resolver(['modo' => 'programado', 'fecha_hora_local' => '2026-03-08T02:30'], ahoraUtc('2026-03-01 00:00:00'), 'America/New_York');

    expect($r['utc']->format('Y-m-d H:i'))->toBe('2026-03-08 07:00')->and($r['local'])->toBe('2026-03-08T03:00');
});

it('un horario repetido toma la primera ocurrencia', function () {
    // 1 de noviembre de 2026: en Nueva York 02:00 → 01:00; 01:30 ocurre dos veces.
    $r = $this->inicio->resolver(['modo' => 'programado', 'fecha_hora_local' => '2026-11-01T01:30'], ahoraUtc('2026-10-01 00:00:00'), 'America/New_York');

    expect($r['utc']->format('Y-m-d H:i'))->toBe('2026-11-01 05:30');
});

it('rechaza modos y formatos inválidos', function () {
    foreach ([['modo' => 'luego'], ['modo' => 'programado'], ['modo' => 'programado', 'fecha_hora_local' => '2026-13-45T25:61'], ['modo' => 'programado', 'fecha_hora_local' => '01/04/2026 08:00'], []] as $entrada) {
        expect(fn () => $this->inicio->resolver($entrada, ahoraUtc(), 'America/Guayaquil'))->toThrow(DatosInvalidos::class);
    }
});
