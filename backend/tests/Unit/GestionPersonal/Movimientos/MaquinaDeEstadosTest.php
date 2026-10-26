<?php

use App\Modules\GestionPersonal\Domain\Services\MaquinaDeEstados;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion as E;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion as T;

beforeEach(fn () => $this->maquina = new MaquinaDeEstados);

it('una novedad solicitada se revisa, resuelve o retira solo mientras está presentada o en revisión', function () {
    expect($this->maquina->destinosDesde(T::Permiso, E::Presentada))->toBe([E::EnRevision, E::Aprobada, E::Rechazada, E::Retirada])
        ->and($this->maquina->destinosDesde(T::Licencia, E::EnRevision))->toBe([E::Aprobada, E::Rechazada, E::Retirada])
        ->and($this->maquina->puede(T::Vacaciones, E::Aprobada, E::Retirada))->toBeFalse()
        ->and($this->maquina->puede(T::Permiso, E::Retirada, E::Aprobada))->toBeFalse();
});

it('los estados terminales no tienen salida', function () {
    foreach ([E::Rechazada, E::Retirada, E::Aplicada, E::Revisada, E::Justificada] as $terminal) {
        expect($terminal->esTerminal())->toBeTrue();
        foreach (T::cases() as $tipo) {
            expect($this->maquina->destinosDesde($tipo, $terminal))->toBe([]);
        }
    }
});

it('una ausencia solo pasa de informada a revisada o justificada y nunca a aprobada', function () {
    expect($this->maquina->destinosDesde(T::Ausencia, E::Informada))->toBe([E::Revisada, E::Justificada])
        ->and($this->maquina->puede(T::Ausencia, E::Informada, E::Aprobada))->toBeFalse()
        ->and($this->maquina->puede(T::Ausencia, E::Informada, E::EnRevision))->toBeFalse()
        ->and($this->maquina->inicial(T::Ausencia))->toBe(E::Informada)
        ->and($this->maquina->inicial(T::Permiso))->toBe(E::Presentada);
});

it('un movimiento se aprueba o programa, se aplica o queda con incidencia, y la incidencia admite reintento', function () {
    expect($this->maquina->puede(T::Ascenso, E::Presentada, E::Programada))->toBeTrue()
        ->and($this->maquina->puede(T::Traslado, E::Programada, E::Aplicada))->toBeTrue()
        ->and($this->maquina->puede(T::Ascenso, E::Programada, E::IncidenciaDeAplicacion))->toBeTrue()
        ->and($this->maquina->puede(T::Ascenso, E::IncidenciaDeAplicacion, E::Aplicada))->toBeTrue()
        ->and($this->maquina->puede(T::Ascenso, E::IncidenciaDeAplicacion, E::IncidenciaDeAplicacion))->toBeTrue()
        ->and($this->maquina->puede(T::Ascenso, E::Presentada, E::Aplicada))->toBeFalse()
        ->and($this->maquina->puede(T::Ascenso, E::Programada, E::Rechazada))->toBeFalse()
        ->and($this->maquina->puede(T::Ascenso, E::Presentada, E::Retirada))->toBeFalse();
});

it('los tipos se mapean a la política del mismo nombre y a su categoría', function () {
    expect(T::Ascenso->politica()->value)->toBe('Ascensos')
        ->and(T::Ausencia->politica()->aceptaAnticipacion())->toBeFalse()
        ->and(T::Permiso->categoria())->toBe('Novedad')
        ->and(T::Traslado->categoria())->toBe('Movimiento')
        ->and(T::valores())->toBe(['Ascenso', 'Traslado', 'Permiso', 'Licencia', 'Vacaciones', 'Ausencia'])
        ->and(E::Presentada->esResoluble())->toBeTrue()
        ->and(E::IncidenciaDeAplicacion->esPendienteDeAplicar())->toBeTrue();
});
