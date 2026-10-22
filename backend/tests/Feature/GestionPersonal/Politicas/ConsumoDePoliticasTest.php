<?php

use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Domain\Entities\PoliticaDePersonal;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoAdmiteSolicitudes;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoConfigurada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentPoliticaRepository;
use App\Modules\GestionPersonal\Presentation\Facades\PoliticasFacade;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Consumidor de prueba: guarda la referencia a la versión y una copia de las condiciones, como exige el contrato.
    Schema::create('solicitudes_prueba', function ($tabla) {
        $tabla->id();
        $tabla->string('tipo');
        $tabla->foreignId('politica_version_id')->constrained('politica_versiones')->restrictOnDelete();
        $tabla->text('condiciones');
        $tabla->char('condiciones_hash', 64);
        $tabla->text('tareas')->nullable();
    });
});

afterEach(fn () => Carbon::setTestNow());

function presentarSolicitud(string $tipo, ?DateTimeImmutable $instante = null): int
{
    return DB::transaction(function () use ($tipo, $instante) {
        $p = PoliticasFacade::seleccionarParaPresentar(1, $tipo, $instante ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $tareas = $tipo === 'Offboarding' ? json_encode($p['condiciones']['parametros']['tareas']) : null;

        return DB::table('solicitudes_prueba')->insertGetId([
            'tipo' => $tipo, 'politica_version_id' => $p['version_id'],
            'condiciones' => json_encode($p['condiciones']), 'condiciones_hash' => $p['hash'], 'tareas' => $tareas,
        ]);
    });
}

function numeroConservado(int $solicitudId): int
{
    return (int) json_decode((string) DB::table('solicitudes_prueba')->where('id', $solicitudId)->value('condiciones'), true)['numero'];
}

it('una solicitud presentada antes de un cambio conserva su versión aunque se resuelva después (PO-CA28, PO-CA29)', function () {
    actuarComoAdminDeTenant();
    $v1 = publicarApi($this, 'Permisos', borradorDe('Permisos')->id)->assertCreated()->json('data.id');
    $antes = presentarSolicitud('Permisos');

    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1])->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$nuevo}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => 9] + parametrosValidos('Permisos')])->assertOk();
    publicarApi($this, 'Permisos', $nuevo, [], null, 2)->assertCreated();
    $despues = presentarSolicitud('Permisos');

    expect(numeroConservado($antes))->toBe(1)->and(numeroConservado($despues))->toBe(2)
        ->and(json_decode((string) DB::table('solicitudes_prueba')->where('id', $antes)->value('condiciones'), true)['parametros']['anticipacion_dias'])->toBe(2)
        ->and((int) DB::table('solicitudes_prueba')->where('id', $antes)->value('politica_version_id'))->toBe($v1);
});

it('la copia conservada coincide con el hash sellado de la versión y no cambia al publicar otras', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos');
    $id = presentarSolicitud('Permisos');

    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('now', new DateTimeZone('UTC'))]);

    $fila = DB::table('solicitudes_prueba')->where('id', $id)->first();
    expect($fila->condiciones_hash)->toBe(DB::table('politica_versiones')->where('id', $v->id)->value('condiciones_hash'))
        ->and(hash('sha256', (string) DB::table('politica_versiones')->where('id', $v->id)->value('condiciones')))->toBe($fila->condiciones_hash);
});

it('con una programada aún no iniciada se usa la vigente (PO-CA27)', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-10 days', new DateTimeZone('UTC'))]);
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+10 days', new DateTimeZone('UTC'))]);

    expect(numeroConservado(presentarSolicitud('Permisos')))->toBe(1);

    expect(numeroConservado(presentarSolicitud('Permisos', new DateTimeImmutable('+10 days +1 second', new DateTimeZone('UTC')))))->toBe(2);
});

it('sin política, sin admitir solicitudes o con fallo de lectura no se confirma la presentación (PO-CA30, PO-CA31, PO-CA32)', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Licencias', ['parametros' => ['disponible' => false] + parametrosValidos('Licencias')]);
    politicaPublicada('Ausencias', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    cabeceraDePolitica('Vacaciones');

    expect(fn () => presentarSolicitud('Permisos'))->toThrow(PoliticaNoConfigurada::class)
        ->and(fn () => presentarSolicitud('Vacaciones'))->toThrow(PoliticaNoConfigurada::class)
        ->and(fn () => presentarSolicitud('Ausencias'))->toThrow(PoliticaNoConfigurada::class)
        ->and(fn () => presentarSolicitud('Licencias'))->toThrow(PoliticaNoAdmiteSolicitudes::class);

    app()->bind(PoliticaRepositoryInterface::class, fn () => new class extends EloquentPoliticaRepository
    {
        public function cabecera(int $tenantId, TipoDePolitica $tipo): ?PoliticaDePersonal
        {
            throw new RuntimeException('caída');
        }
    });
    app()->forgetInstance('politicas-facade');
    PoliticasFacade::clearResolvedInstance('politicas-facade');

    expect(fn () => presentarSolicitud('Permisos'))->toThrow(PoliticaNoDisponible::class)
        ->and(DB::table('solicitudes_prueba')->count())->toBe(0);
});

it('deshabilitar nuevas solicitudes no cancela las existentes (PO-CA30)', function () {
    actuarComoAdminDeTenant();
    $v1 = publicarApi($this, 'Permisos', borradorDe('Permisos')->id)->assertCreated()->json('data.id');
    $existente = presentarSolicitud('Permisos');

    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1])->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$nuevo}"), ['version' => 1, 'parametros' => ['disponible' => false] + parametrosValidos('Permisos')])->assertOk();
    publicarApi($this, 'Permisos', $nuevo, [], null, 2)->assertCreated();

    expect(fn () => presentarSolicitud('Permisos'))->toThrow(PoliticaNoAdmiteSolicitudes::class)
        ->and(DB::table('solicitudes_prueba')->where('id', $existente)->exists())->toBeTrue()
        ->and(numeroConservado($existente))->toBe(1);
});

it('el orden confirmado decide la versión: presentar antes de publicar usa la anterior y después la nueva', function () {
    actuarComoAdminDeTenant();
    $v1 = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-1 day', new DateTimeZone('UTC'))]);

    $primero = presentarSolicitud('Permisos');
    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1->id])->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$nuevo}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => 4] + parametrosValidos('Permisos')])->assertOk();
    publicarApi($this, 'Permisos', $nuevo, [], null, 2)->assertCreated();
    $segundo = presentarSolicitud('Permisos', new DateTimeImmutable('+1 minute', new DateTimeZone('UTC')));

    expect(numeroConservado($primero))->toBe(1)->and(numeroConservado($segundo))->toBe(2);
});

it('seleccionar al presentar toma el bloqueo de la cabecera dentro de la transacción del consumidor', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos');
    $bloqueos = 0;
    DB::listen(function ($consulta) use (&$bloqueos) {
        if (str_contains($consulta->sql, 'update "politicas_personal" set "version" = version')) {
            $bloqueos++;
        }
    });

    presentarSolicitud('Permisos');

    expect($bloqueos)->toBe(1);
});

it('la fachada exige la transacción del consumidor y rechaza tipos desconocidos', function () {
    politicaPublicada('Permisos');
    app()->bind(TransaccionPort::class, fn () => new class implements TransaccionPort
    {
        public function enTransaccion(callable $accion): mixed
        {
            return $accion();
        }

        public function enCurso(): bool
        {
            return false;
        }
    });
    app()->forgetInstance('politicas-facade');
    PoliticasFacade::clearResolvedInstance('politicas-facade');
    $ahora = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    expect(fn () => PoliticasFacade::seleccionarParaPresentar(1, 'Permisos', $ahora))->toThrow(LogicException::class)
        ->and(fn () => PoliticasFacade::aplicablePara(1, 'Inventada', $ahora))->toThrow(InvalidArgumentException::class);
});

it('aplicablePara informa las tres situaciones sin reservar versión', function () {
    politicaPublicada('Permisos');
    politicaPublicada('Licencias', ['parametros' => ['disponible' => false] + parametrosValidos('Licencias')]);
    $ahora = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $vigente = PoliticasFacade::aplicablePara(1, 'Permisos', $ahora);
    $noAdmite = PoliticasFacade::aplicablePara(1, 'Licencias', $ahora);
    $sinConfigurar = PoliticasFacade::aplicablePara(1, 'Vacaciones', $ahora);

    expect($vigente['situacion'])->toBe('Vigente')->and($vigente['politica']['numero'])->toBe(1)->and($vigente['politica']['hash'])->toHaveLength(64)
        ->and($noAdmite['situacion'])->toBe('NoAdmiteSolicitudes')->and($noAdmite['politica']['parametros']['disponible'])->toBeFalse()
        ->and($sinConfigurar)->toBe(['situacion' => 'SinConfigurar', 'politica' => null])
        ->and(DB::table('solicitudes_prueba')->count())->toBe(0);
});

it('una versión referenciada por una solicitud no puede eliminarse', function () {
    $v = politicaPublicada('Permisos');
    presentarSolicitud('Permisos');

    expect(fn () => DB::table('politica_versiones')->where('id', $v->id)->delete())->toThrow(QueryException::class);
});
