<?php

use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\PosicionesAsignablesEstructura;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function estructuraCaida(): void
{
    app()->bind(PosicionesAsignablesPort::class, fn () => new class implements PosicionesAsignablesPort
    {
        public function listar(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
        {
            throw new IngresoNoVerificable;
        }

        public function verificar(int $posicionId, int $tenantId): array
        {
            throw new IngresoNoVerificable;
        }

        public function describir(int $posicionId): ?array
        {
            throw new IngresoNoVerificable;
        }
    });
}

it('si no puede verificarse la posición el ingreso no se confirma (503)', function () {
    $ficha = fichaDe(['numero_identificacion' => '95']);
    $posicion = posicionAsignable();
    estructuraCaida();
    actuarComoAdminDeTenant();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))
        ->assertStatus(503)->assertJsonPath('code', 'ESTRUCTURA_NO_VERIFICABLE');

    expect(DB::table('vinculos_laborales')->count())->toBe(0)
        ->and(DB::table('asignaciones_laborales')->count())->toBe(0);
});

it('la misma operación puede repetirse cuando Estructura vuelve', function () {
    $ficha = fichaDe(['numero_identificacion' => '96']);
    $posicion = posicionAsignable();
    $clave = (string) Str::uuid();
    estructuraCaida();
    actuarComoAdminDeTenant();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertStatus(503);

    app()->bind(PosicionesAsignablesPort::class, PosicionesAsignablesEstructura::class);
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertCreated();
});

it('Estructura caída en las lecturas deja estado y fechas y marca puesto y unidad no disponibles', function () {
    $ficha = fichaDe(['numero_identificacion' => '97']);
    vinculoDe($ficha->id, hoyDelTenant(-10), null, posicionAsignable()->id);
    lectorEstructuraFalso($this, ['posiciones' => Lectura::noDisponible()]);
    actuarComoAdminDeTenant();

    $situacion = $this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->assertOk();

    expect($situacion->json('data.estado'))->toBe('Activo')
        ->and($situacion->json('data.disponibilidad'))->toBe('no_disponible')
        ->and($situacion->json('data.vinculo_vigente.fecha_ingreso'))->toBe(hoyDelTenant(-10))
        ->and($situacion->json('data.vinculo_vigente.posicion.puesto'))->toBeNull();
});
