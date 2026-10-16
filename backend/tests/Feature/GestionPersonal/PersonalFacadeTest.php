<?php

use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\IdentificacionDuplicada;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Presentation\Facades\SharedFacade;
use Illuminate\Support\Str;

it('registra por la fachada con las mismas reglas e idempotencia', function () {
    $admin = actuarComoAdminDeTenant();
    $clave = (string) Str::uuid();

    $primera = PersonalFacade::registrarFicha(1, $admin->id, $clave, datosFicha());
    $segunda = PersonalFacade::registrarFicha(1, $admin->id, $clave, datosFicha());

    expect($primera['creado'])->toBeTrue()
        ->and($segunda['creado'])->toBeFalse()
        ->and($segunda['colaborador_id'])->toBe($primera['colaborador_id'])
        ->and(fn () => PersonalFacade::registrarFicha(1, $admin->id, (string) Str::uuid(), datosFicha()))->toThrow(IdentificacionDuplicada::class);
});

it('consulta por id e identificación solo dentro del tenant', function () {
    $ficha = fichaDe(['numero_identificacion' => ' AB-1 ']);

    expect(PersonalFacade::getColaborador(1, $ficha->id)['codigo'])->toBe($ficha->codigo)
        ->and(PersonalFacade::getColaborador(2, $ficha->id))->toBeNull()
        ->and(PersonalFacade::getColaboradoresPorIds(1, [$ficha->id, 9999]))->toHaveCount(1)
        ->and(PersonalFacade::buscarPorIdentificacion(1, 'Cedula', 'ab-1')['id'])->toBe($ficha->id)
        ->and(PersonalFacade::buscarPorIdentificacion(2, 'Cedula', 'ab-1'))->toBeNull();
});

it('rechaza registrar con un actor de otro tenant', function () {
    $admin = actuarComoAdminDeTenant(2);

    expect(fn () => PersonalFacade::registrarFicha(1, $admin->id, (string) Str::uuid(), datosFicha()))
        ->toThrow(ColaboradorNoEncontrado::class);
});

it('Shared entrega el contexto de tenant de la cuenta', function () {
    $admin = actuarComoAdminDeTenant(2);

    expect(SharedFacade::getContextoDeTenant($admin->id))->toBe([
        'tenant_id' => 2, 'nombre' => 'Cordillera Andina S.A.', 'zona_horaria' => 'America/Guayaquil',
    ])->and(SharedFacade::getContextoDeTenant(999999))->toBeNull();
});
