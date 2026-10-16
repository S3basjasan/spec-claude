<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(fn () => almacenDeDocumentos());

function objetosAlmacenados(): array
{
    return Storage::disk(discoDeDocumentos())->allFiles('personal');
}

it('publica un PDF válido como Disponible, privado y con los metadatos fijados por el servidor (EXP-DOC-CA01)', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $respuesta = subirDocumento($this, $ficha->id, ['titulo' => '  Contrato firmado  '])->assertCreated();

    expect($respuesta->json('data.titulo'))->toBe('Contrato firmado')
        ->and($respuesta->json('data.estado'))->toBe('Disponible')
        ->and($respuesta->json('data.visible_colaborador'))->toBeFalse()
        ->and($respuesta->json('data.formato'))->toBe('application/pdf')
        ->and($respuesta->json('data.tamano_bytes'))->toBe(strlen(pdfSintetico()))
        ->and($respuesta->json('data.created_by'))->toBe($admin->id)
        ->and($respuesta->json('data.version'))->toBe(1)
        ->and($respuesta->json('data.categoria.estado'))->toBe('Activa')
        ->and(array_keys($respuesta->json('data')))->not->toContain('object_key')
        ->and($respuesta->getContent())->not->toContain('personal/');

    $fila = DocumentoColaboradorModel::query()->first();
    expect($fila->colaborador_id)->toBe($ficha->id)
        ->and($fila->tenant_id)->toBe(1)
        ->and($fila->sha256)->toBe(hash('sha256', pdfSintetico()))
        ->and($fila->object_key)->toStartWith("personal/1/colaboradores/{$ficha->id}/")
        ->and($fila->object_key)->toEndWith('.pdf')
        ->and($fila->object_key)->not->toContain('contrato')
        ->and(Storage::disk(discoDeDocumentos())->exists($fila->object_key))->toBeTrue();
});

it('respeta la visibilidad enviada', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    subirDocumento($this, $ficha->id, ['visible_colaborador' => '1'])->assertCreated()->assertJsonPath('data.visible_colaborador', true);
});

it('valida el título', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    subirDocumento($this, $ficha->id, ['titulo' => '   '])->assertUnprocessable()->assertJsonValidationErrors(['titulo']);
    subirDocumento($this, $ficha->id, ['titulo' => str_repeat('a', 201)])->assertUnprocessable()->assertJsonValidationErrors(['titulo']);
    subirDocumento($this, $ficha->id, ['titulo' => str_repeat('a', 200)])->assertCreated();
    expect(DocumentoColaboradorModel::query()->count())->toBe(1);
});

it('exige una categoría activa existente (EXP-DOC-CA03)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $inactiva = categoriaActiva(['estado' => 'Inactiva']);
    $ajena = categoriaActiva([], 2);

    subirDocumento($this, $ficha->id, ['categoria_id' => null])->assertUnprocessable()->assertJsonValidationErrors(['categoria_id']);
    subirDocumento($this, $ficha->id, categoriaId: $inactiva->id)->assertStatus(409)->assertJsonPath('code', 'CATEGORIA_INACTIVA');
    subirDocumento($this, $ficha->id, categoriaId: 999999)->assertUnprocessable()->assertJsonValidationErrors(['categoria_id']);
    subirDocumento($this, $ficha->id, categoriaId: $ajena->id)->assertUnprocessable()->assertJsonValidationErrors(['categoria_id']);
    expect(DocumentoColaboradorModel::query()->count())->toBe(0)->and(objetosAlmacenados())->toBe([]);
});

it('rechaza archivo ausente, vacío y mayor de 10000000 bytes; acepta exactamente 10000000', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();

    $this->post("/api/colaboradores/{$ficha->id}/documentos", ['titulo' => 'X', 'categoria_id' => $categoria->id], ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonValidationErrors(['archivo']);
    subirDocumento($this, $ficha->id, contenido: '', categoriaId: $categoria->id)->assertUnprocessable()
        ->assertJsonPath('motivo', 'vacio')->assertJsonValidationErrors(['archivo']);
    subirDocumento($this, $ficha->id, contenido: str_repeat('a', 10000001), categoriaId: $categoria->id)->assertUnprocessable()
        ->assertJsonPath('motivo', 'demasiado_grande');
    expect(DocumentoColaboradorModel::query()->count())->toBe(0);

    subirDocumento($this, $ficha->id, contenido: pdfSintetico(bytesObjetivo: 10000000), categoriaId: $categoria->id)->assertCreated()
        ->assertJsonPath('data.tamano_bytes', 10000000);
});

it('rechaza por contenido un archivo renombrado a PDF y un PDF dañado (EXP-DOC-CA02)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();

    subirDocumento($this, $ficha->id, contenido: archivoNoPdf(), categoriaId: $categoria->id)->assertUnprocessable()->assertJsonPath('motivo', 'no_es_pdf');
    subirDocumento($this, $ficha->id, contenido: substr(pdfSintetico(), 0, 200), categoriaId: $categoria->id)->assertUnprocessable()->assertJsonPath('motivo', 'estructura_invalida');
    expect(DocumentoColaboradorModel::query()->count())->toBe(0)->and(objetosAlmacenados())->toBe([]);
});

it('ignora tenant_id, created_by, object_key y metadatos enviados por el cliente', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();

    subirDocumento($this, $ficha->id, ['tenant_id' => 2, 'created_by' => 999, 'object_key' => 'personal/2/x.pdf', 'size_bytes' => 5, 'estado' => 'Archivado', 'mime_type' => 'text/plain'])->assertCreated();

    $fila = DocumentoColaboradorModel::query()->first();
    expect($fila->tenant_id)->toBe(1)->and($fila->created_by)->toBe($admin->id)
        ->and($fila->object_key)->not->toBe('personal/2/x.pdf')
        ->and($fila->size_bytes)->toBe(strlen(pdfSintetico()))
        ->and($fila->estado)->toBe('Disponible')->and($fila->mime_type)->toBe('application/pdf');
});

it('exige la Idempotency-Key', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $archivo = UploadedFile::fake()->createWithContent('a.pdf', pdfSintetico());

    $this->post("/api/colaboradores/{$ficha->id}/documentos", ['titulo' => 'X', 'categoria_id' => categoriaActiva()->id, 'archivo' => $archivo], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
    expect(DocumentoColaboradorModel::query()->count())->toBe(0);
});

it('responde 404 idéntico para una ficha inexistente o de otro tenant', function () {
    actuarComoAdminDeTenant();
    $ajena = fichaDe([], 2);

    $a = subirDocumento($this, 999999)->assertNotFound();
    $b = subirDocumento($this, $ajena->id)->assertNotFound();

    expect($b->json())->toBe($a->json())->and($a->json('code'))->toBe('FICHA_NO_ENCONTRADA')
        ->and(DocumentoColaboradorModel::query()->count())->toBe(0)->and(objetosAlmacenados())->toBe([]);
});

it('exige sesión y deniega al colaborador', function () {
    $ficha = fichaDe();
    subirDocumento($this, $ficha->id)->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    subirDocumento($this, $ficha->id)->assertForbidden();
    expect(DocumentoColaboradorModel::query()->count())->toBe(0);
});

it('respeta el interruptor de documentos y no tiene DELETE', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    config(['hrms.gestion_personal.documentos.habilitado' => false]);
    subirDocumento($this, $ficha->id)->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');
    config(['hrms.gestion_personal.documentos.habilitado' => true]);

    $creado = subirDocumento($this, $ficha->id)->assertCreated()->json('data');
    expect($this->deleteJson("/api/colaboradores/{$ficha->id}/documentos/{$creado['id']}")->status())->toBeIn([404, 405])
        ->and(DocumentoColaboradorModel::query()->count())->toBe(1);
});

it('la misma clave y el mismo contenido devuelven el mismo documento sin subir otra copia', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $clave = (string) Str::uuid();

    $primera = subirDocumento($this, $ficha->id, clave: $clave, categoriaId: $categoria->id)->assertCreated();
    $segunda = subirDocumento($this, $ficha->id, clave: $clave, categoriaId: $categoria->id)->assertOk();

    expect($segunda->json('data.id'))->toBe($primera->json('data.id'))
        ->and(DocumentoColaboradorModel::query()->count())->toBe(1)
        ->and(objetosAlmacenados())->toHaveCount(1);
});

it('la misma clave con otro archivo o título devuelve 409 sin crear nada', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $clave = (string) Str::uuid();

    subirDocumento($this, $ficha->id, clave: $clave, categoriaId: $categoria->id)->assertCreated();
    subirDocumento($this, $ficha->id, clave: $clave, contenido: pdfSintetico(2), categoriaId: $categoria->id)->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');
    subirDocumento($this, $ficha->id, ['titulo' => 'Otro título'], clave: $clave, categoriaId: $categoria->id)->assertStatus(409);

    expect(DocumentoColaboradorModel::query()->count())->toBe(1)->and(objetosAlmacenados())->toHaveCount(1);
});

it('una nueva carga explícita con el mismo título crea otro documento', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();

    subirDocumento($this, $ficha->id, ['titulo' => 'Contrato'], categoriaId: $categoria->id)->assertCreated();
    subirDocumento($this, $ficha->id, ['titulo' => 'Contrato'], categoriaId: $categoria->id)->assertCreated();

    expect(DocumentoColaboradorModel::query()->count())->toBe(2)->and(objetosAlmacenados())->toHaveCount(2);
});

it('repetir una operación cuyo documento ya se archivó no lo reactiva', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $clave = (string) Str::uuid();

    $creado = subirDocumento($this, $ficha->id, clave: $clave, categoriaId: $categoria->id)->assertCreated()->json('data');
    $this->patchJson("/api/colaboradores/{$ficha->id}/documentos/{$creado['id']}/archivo", ['version' => 1])->assertOk();

    subirDocumento($this, $ficha->id, clave: $clave, categoriaId: $categoria->id)->assertOk()->assertJsonPath('data.estado', 'Archivado');

    expect(DocumentoColaboradorModel::query()->count())->toBe(1);
});
