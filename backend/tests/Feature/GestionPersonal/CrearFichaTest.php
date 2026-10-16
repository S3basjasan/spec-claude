<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;

it('crea una ficha mínima con código, versión 1, estado Sin vínculo vigente y sin cuenta (GP01-CA01)', function () {
    $admin = actuarComoAdminDeTenant();
    $usuarios = UserModel::query()->count();

    $respuesta = crearFichaApi($this, datosFicha(['numero_identificacion' => '  0102030405 ', 'nombres' => ' Ana ']))
        ->assertCreated();

    $ficha = ColaboradorModel::query()->firstOrFail();

    expect($respuesta->json('data.codigo'))->toMatch('/^EMP-\d{3}$/')
        ->and($respuesta->json('data.codigo'))->toBe(sprintf('EMP-%03d', $ficha->id))
        ->and($respuesta->json('data.version'))->toBe(1)
        ->and($respuesta->json('data.nombres'))->toBe('Ana')
        ->and($respuesta->json('data.numero_identificacion'))->toBe('0102030405')
        ->and($respuesta->json('data.laboral.estado'))->toBe('SinVinculoVigente')
        ->and($respuesta->json('data.laboral.puesto'))->toBeNull()
        ->and($respuesta->json('data.trayectoria.hechos'))->toBe([])
        ->and($respuesta->json('data.capacidades'))->toBe(['desarrollo' => false, 'documentos' => false, 'registrar_ingreso' => true, 'exportar' => false])
        ->and($respuesta->json('data.acciones.editar'))->toBeTrue()
        ->and($ficha->tenant_id)->toBe(1)
        ->and($ficha->created_by)->toBe($admin->id)
        ->and($ficha->updated_by)->toBe($admin->id)
        ->and(UserModel::query()->count())->toBe($usuarios)
        ->and(ColaboradorCuentaModel::query()->count())->toBe(0);
});

it('guarda los datos complementarios y el contacto de emergencia', function () {
    actuarComoAdminDeTenant();

    $respuesta = crearFichaApi($this, datosFicha([
        'fecha_nacimiento' => '1990-04-12', 'nacionalidad' => 'Ecuatoriana', 'genero' => 'Femenino',
        'estado_civil' => 'Soltera', 'numero_hijos' => 0, 'direccion' => 'Av. Principal 123',
        'telefono' => '+593 2 555 0101', 'correo_contacto' => 'ana@example.test',
        'emergencia_nombre' => 'Rosa Núñez', 'emergencia_relacion' => 'Madre', 'emergencia_telefono' => '0999999999',
    ]))->assertCreated();

    expect($respuesta->json('data.numero_hijos'))->toBe(0)
        ->and($respuesta->json('data.emergencia_nombre'))->toBe('Rosa Núñez')
        ->and($respuesta->json('data.correo_contacto'))->toBe('ana@example.test');
});

it('el código sigue creciendo pasado el 999', function () {
    ColaboradorModel::query()->insert([
        'id' => 999, 'tenant_id' => 1, 'codigo' => 'EMP-999', 'tipo_identificacion' => 'Otro',
        'numero_identificacion' => 'X', 'identificacion_clave' => 'x', 'nombres' => 'X', 'apellidos' => 'X',
        'nombres_busqueda' => 'x', 'apellidos_busqueda' => 'x', 'version' => 1,
        'operacion_id' => (string) Str::uuid(), 'operacion_hash' => str_repeat('a', 64),
        'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    actuarComoAdminDeTenant();

    $respuesta = crearFichaApi($this, datosFicha())->assertCreated();

    expect($respuesta->json('data.codigo'))->toBe('EMP-1000');
});

it('el reintento con la misma clave y cuerpo devuelve 200 con la misma ficha sin duplicar (GP01-RN04)', function () {
    actuarComoAdminDeTenant();
    $clave = (string) Str::uuid();

    $primera = crearFichaApi($this, datosFicha(), $clave)->assertCreated();
    $segunda = crearFichaApi($this, datosFicha(), $clave)->assertOk();

    expect($segunda->json('data.id'))->toBe($primera->json('data.id'))
        ->and($segunda->json('data.codigo'))->toBe($primera->json('data.codigo'))
        ->and(ColaboradorModel::query()->count())->toBe(1);
});

it('la misma clave con otro cuerpo se rechaza', function () {
    actuarComoAdminDeTenant();
    $clave = (string) Str::uuid();

    crearFichaApi($this, datosFicha(), $clave)->assertCreated();
    crearFichaApi($this, datosFicha(['nombres' => 'Otra']), $clave)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');

    expect(ColaboradorModel::query()->count())->toBe(1);
});

it('exige una clave de operación válida', function () {
    actuarComoAdminDeTenant();

    $this->postJson('/api/colaboradores', datosFicha())
        ->assertUnprocessable()->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
    $this->postJson('/api/colaboradores', datosFicha(), ['Idempotency-Key' => 'no-es-uuid'])
        ->assertUnprocessable()->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');

    expect(ColaboradorModel::query()->count())->toBe(0);
});

it('valida obligatorios, longitudes, fecha futura, hijos, correo y teléfono por campo', function () {
    actuarComoAdminDeTenant();

    crearFichaApi($this, ['tipo_identificacion' => 'Cedula'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['numero_identificacion', 'nombres', 'apellidos']);

    crearFichaApi($this, datosFicha([
        'numero_identificacion' => str_repeat('9', 51), 'nombres' => str_repeat('a', 151),
        'fecha_nacimiento' => now()->addDay()->toDateString(), 'numero_hijos' => -1,
        'correo_contacto' => 'no-es-correo', 'telefono' => 'abc',
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'numero_identificacion', 'nombres', 'numero_hijos', 'correo_contacto', 'telefono',
    ]);

    crearFichaApi($this, datosFicha(['fecha_nacimiento' => now()->addDays(2)->toDateString()]))
        ->assertUnprocessable()->assertJsonValidationErrors(['fecha_nacimiento']);

    crearFichaApi($this, datosFicha(['emergencia_relacion' => 'Madre']))
        ->assertUnprocessable()->assertJsonValidationErrors(['emergencia_nombre', 'emergencia_telefono']);

    expect(ColaboradorModel::query()->count())->toBe(0);
});

it('acepta el mismo correo de contacto en dos fichas y el texto de solo espacios cuenta como vacío', function () {
    actuarComoAdminDeTenant();

    crearFichaApi($this, datosFicha(['correo_contacto' => 'x@example.test']))->assertCreated();
    crearFichaApi($this, datosFicha(['numero_identificacion' => '2', 'correo_contacto' => 'x@example.test']))->assertCreated();
    crearFichaApi($this, datosFicha(['numero_identificacion' => '3', 'nombres' => '   ']))
        ->assertUnprocessable()->assertJsonValidationErrors(['nombres']);
});

it('no deja una ficha creada si el guardado falla por validación del dominio', function () {
    actuarComoAdminDeTenant();

    crearFichaApi($this, datosFicha(['fecha_nacimiento' => '2999-01-01']))->assertUnprocessable();

    expect(ColaboradorModel::query()->count())->toBe(0);
});
