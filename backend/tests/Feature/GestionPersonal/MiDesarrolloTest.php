<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;

function asociarDesarrollo(mixed $usuario, mixed $ficha, array $permisos = ['puede_consultar_ficha' => true]): void
{
    ColaboradorCuentaModel::query()->create(array_merge([
        'tenant_id' => $ficha->tenant_id, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id,
    ], $permisos));
}

it('el colaborador consulta solo lo suyo en solo lectura (GP03-CA06)', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    $ajena = fichaDe();
    asociarDesarrollo($usuario, $propia);
    FormacionAcademicaModel::factory()->create(['colaborador_id' => $propia->id, 'titulo_programa' => 'Mía']);
    FormacionAcademicaModel::factory()->create(['colaborador_id' => $ajena->id, 'titulo_programa' => 'Ajena']);

    $respuesta = $this->getJson('/api/mi-ficha/desarrollo')->assertOk();

    expect($respuesta->json('data.colaborador_id'))->toBe($propia->id)
        ->and(array_column($respuesta->json('data.formaciones'), 'titulo_programa'))->toBe(['Mía'])
        ->and($respuesta->json('data.acciones.editar'))->toBeFalse();
});

it('informa sin permiso y sin ficha con códigos distintos, sin requerir permiso documental', function () {
    actuarComoUsuarioDeTenant();
    $this->getJson('/api/mi-ficha/desarrollo')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');

    $usuario = actuarComoUsuarioDeTenant();
    asociarDesarrollo($usuario, fichaDe(), ['puede_consultar_ficha' => false, 'puede_consultar_documentos' => true]);
    $this->getJson('/api/mi-ficha/desarrollo')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_FICHA');

    $otro = actuarComoUsuarioDeTenant();
    asociarDesarrollo($otro, fichaDe(), ['puede_consultar_ficha' => true, 'puede_consultar_documentos' => false, 'puede_exportar' => false]);
    $this->getJson('/api/mi-ficha/desarrollo')->assertOk();
});

it('no puede escribir ni leer lo ajeno por las rutas de administración', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    $ajena = fichaDe();
    asociarDesarrollo($usuario, $propia);
    $cuerpo = ['nivel' => 'X', 'titulo_programa' => 'X', 'institucion' => 'X', 'estado' => 'Completada'];

    $this->postJson("/api/colaboradores/{$propia->id}/formaciones", $cuerpo)->assertForbidden();
    $this->postJson("/api/colaboradores/{$ajena->id}/formaciones", $cuerpo)->assertForbidden();
    $this->getJson("/api/colaboradores/{$ajena->id}/desarrollo")->assertForbidden();
    $this->patchJson("/api/colaboradores/{$propia->id}/movilidad", ['version' => 1, 'estado' => 'Disponible'])->assertForbidden();
});

it('una asociación con ficha de otro tenant no concede acceso y exige sesión', function () {
    $usuario = actuarComoUsuarioDeTenant(1);
    $ajena = fichaDe([], 2);
    ColaboradorCuentaModel::query()->create(['tenant_id' => 2, 'user_id' => $usuario->id, 'colaborador_id' => $ajena->id, 'puede_consultar_ficha' => true]);

    $this->getJson('/api/mi-ficha/desarrollo')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');
});

it('exige sesión', function () {
    $this->getJson('/api/mi-ficha/desarrollo')->assertUnauthorized();
});
