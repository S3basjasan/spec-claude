<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;

function asociar(mixed $usuario, mixed $ficha, array $permisos = ['puede_consultar_ficha' => true]): void
{
    ColaboradorCuentaModel::query()->create(array_merge([
        'tenant_id' => $ficha->tenant_id, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id,
    ], $permisos));
}

it('el colaborador con asociación y permiso consulta su ficha en solo lectura (GP01-CA04)', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '1', 'nombres' => 'Propia']);
    asociar($usuario, $ficha);

    $respuesta = $this->getJson('/api/mi-ficha')->assertOk();

    expect($respuesta->json('data.id'))->toBe($ficha->id)
        ->and($respuesta->json('data.nombres'))->toBe('Propia')
        ->and($respuesta->json('data.acciones.editar'))->toBeFalse()
        ->and($respuesta->json('data.capacidades.documentos'))->toBeFalse();
});

it('no puede pedir otra ficha ni el listado ni editar la propia', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe(['numero_identificacion' => '1']);
    $ajena = fichaDe(['numero_identificacion' => '2']);
    asociar($usuario, $propia);

    $this->getJson('/api/colaboradores/'.$ajena->id)->assertForbidden();
    $this->getJson('/api/colaboradores/'.$propia->id)->assertForbidden();
    $this->getJson('/api/colaboradores')->assertForbidden();
    $this->patchJson('/api/colaboradores/'.$propia->id, ['version' => 1, 'nombres' => 'X'])->assertForbidden();
    $this->getJson('/api/mi-ficha?colaborador_id='.$ajena->id)->assertOk()->assertJsonPath('data.id', $propia->id);
});

it('informa cuenta sin ficha y falta de permiso con códigos distintos', function () {
    actuarComoUsuarioDeTenant();
    $this->getJson('/api/mi-ficha')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');

    $usuario = actuarComoUsuarioDeTenant();
    asociar($usuario, fichaDe(['numero_identificacion' => '3']), ['puede_consultar_ficha' => false, 'puede_consultar_documentos' => true]);
    $this->getJson('/api/mi-ficha')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_FICHA');
});

it('una asociación hacia una ficha de otro tenant no concede acceso', function () {
    $usuario = actuarComoUsuarioDeTenant(1);
    $ajena = fichaDe(['numero_identificacion' => '4'], 2);
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => 2, 'user_id' => $usuario->id, 'colaborador_id' => $ajena->id, 'puede_consultar_ficha' => true,
    ]);

    $this->getJson('/api/mi-ficha')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');
});

it('exige sesión', function () {
    $this->getJson('/api/mi-ficha')->assertUnauthorized();
});
