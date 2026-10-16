<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => almacenDeDocumentos());

function asociarDocumentos(mixed $usuario, mixed $ficha, array $permisos = ['puede_consultar_documentos' => true]): void
{
    ColaboradorCuentaModel::query()->create(array_merge([
        'tenant_id' => $ficha->tenant_id, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id,
    ], $permisos));
}

function miDocumento(int $fichaId, array $atributos = []): DocumentoColaboradorModel
{
    return documentoConArchivo(array_merge(['colaborador_id' => $fichaId, 'categoria_id' => categoriaActiva()->id, 'visible_colaborador' => true], $atributos));
}

it('el colaborador ve solo los documentos de su ficha visibles y disponibles (EXP-DOC-CA05)', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    $ajena = fichaDe();
    asociarDocumentos($usuario, $propia);
    miDocumento($propia->id, ['titulo' => 'Visible']);
    miDocumento($propia->id, ['titulo' => 'Privado', 'visible_colaborador' => false]);
    miDocumento($propia->id, ['titulo' => 'Archivado'])->forceFill([])->save();
    DocumentoColaboradorModel::query()->where('titulo', 'Archivado')->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);
    miDocumento($ajena->id, ['titulo' => 'Ajeno']);

    $respuesta = $this->getJson('/api/mi-ficha/documentos')->assertOk();

    expect(array_column($respuesta->json('data'), 'titulo'))->toBe(['Visible'])
        ->and(array_keys($respuesta->json('data.0')))->toEqualCanonicalizing(['id', 'titulo', 'categoria', 'formato', 'tamano_bytes', 'created_at'])
        ->and($respuesta->getContent())->not->toContain('object_key')->not->toContain('visible_colaborador');
});

it('ignora el estado y el colaborador recibidos en la consulta', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    $ajena = fichaDe();
    asociarDocumentos($usuario, $propia);
    miDocumento($propia->id, ['titulo' => 'Visible']);
    miDocumento($ajena->id, ['titulo' => 'Ajeno']);

    $respuesta = $this->getJson("/api/mi-ficha/documentos?estado=Archivado&colaborador_id={$ajena->id}")->assertOk();

    expect(array_column($respuesta->json('data'), 'titulo'))->toBe(['Visible']);
});

it('abre y descarga un documento propio visible y disponible con las cabeceras seguras', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarDocumentos($usuario, $propia);
    $contenido = pdfSintetico(1, 'Mío');
    $documento = miDocumento($propia->id, []);
    Storage::disk(discoDeDocumentos())->put($documento->object_key, $contenido);
    $documento->update(['size_bytes' => strlen($contenido)]);

    $respuesta = $this->get("/api/mi-ficha/documentos/{$documento->id}/contenido")->assertOk();

    expect($respuesta->streamedContent())->toBe($contenido)
        ->and($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Cache-Control'))->toContain('no-store');
    expect($this->get("/api/mi-ficha/documentos/{$documento->id}/contenido?descarga=1")->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('un documento no visible, archivado, de otra ficha o de otro tenant responde 404 sin bytes aun conociendo el id', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    $ajena = fichaDe();
    $otroTenant = fichaDe([], 2);
    asociarDocumentos($usuario, $propia);
    $privado = miDocumento($propia->id, ['visible_colaborador' => false]);
    $archivado = miDocumento($propia->id);
    DocumentoColaboradorModel::query()->whereKey($archivado->id)->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);
    $deAjena = miDocumento($ajena->id);
    $deOtroTenant = documentoConArchivo(['tenant_id' => 2, 'colaborador_id' => $otroTenant->id, 'categoria_id' => categoriaActiva([], 2)->id, 'visible_colaborador' => true]);

    $inexistente = $this->get('/api/mi-ficha/documentos/999999/contenido')->assertNotFound();
    foreach ([$privado, $archivado, $deAjena, $deOtroTenant] as $documento) {
        $respuesta = $this->get("/api/mi-ficha/documentos/{$documento->id}/contenido")->assertNotFound();
        expect($respuesta->json())->toBe($inexistente->json());
    }
});

it('sin permiso documental se deniega aunque tenga permiso de ficha y de exportación', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarDocumentos($usuario, $propia, ['puede_consultar_ficha' => true, 'puede_exportar' => true, 'puede_consultar_documentos' => false]);
    $documento = miDocumento($propia->id);

    $this->getJson('/api/mi-ficha/documentos')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_DOCUMENTOS');
    $this->get("/api/mi-ficha/documentos/{$documento->id}/contenido")->assertForbidden();
});

it('con solo el permiso documental accede sin el permiso de ficha', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarDocumentos($usuario, $propia, ['puede_consultar_ficha' => false, 'puede_consultar_documentos' => true]);
    miDocumento($propia->id);

    $this->getJson('/api/mi-ficha/documentos')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/mi-ficha')->assertForbidden();
});

it('sin ficha asociada o con asociación de otro tenant no hay acceso', function () {
    actuarComoUsuarioDeTenant();
    $this->getJson('/api/mi-ficha/documentos')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');

    $usuario = actuarComoUsuarioDeTenant(1);
    $ajena = fichaDe([], 2);
    ColaboradorCuentaModel::query()->create(['tenant_id' => 2, 'user_id' => $usuario->id, 'colaborador_id' => $ajena->id, 'puede_consultar_documentos' => true]);
    $this->getJson('/api/mi-ficha/documentos')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');
});

it('retirar la visibilidad, archivar o revocar el permiso rechaza la siguiente petición', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarDocumentos($usuario, $propia);
    $documento = miDocumento($propia->id);
    $url = "/api/mi-ficha/documentos/{$documento->id}/contenido";

    $this->get($url)->assertOk();
    $documento->update(['visible_colaborador' => false]);
    $this->get($url)->assertNotFound();

    $documento->update(['visible_colaborador' => true]);
    $this->get($url)->assertOk();
    DocumentoColaboradorModel::query()->whereKey($documento->id)->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);
    $this->get($url)->assertNotFound();

    DocumentoColaboradorModel::query()->whereKey($documento->id)->update(['estado' => 'Disponible', 'archived_by' => null, 'archived_at' => null]);
    $this->get($url)->assertOk();
    ColaboradorCuentaModel::query()->where('user_id', $usuario->id)->update(['puede_consultar_documentos' => false]);
    $this->get($url)->assertForbidden();
});

it('el colaborador no puede escribir ni usar las rutas de administración', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarDocumentos($usuario, $propia);
    $documento = miDocumento($propia->id);

    $this->getJson(urlDocumentos($propia->id))->assertForbidden();
    $this->patchJson(urlDocumentos($propia->id, "/{$documento->id}/visibilidad"), ['visible_colaborador' => false, 'version' => 1])->assertForbidden();
    $this->patchJson(urlDocumentos($propia->id, "/{$documento->id}/archivo"), ['version' => 1])->assertForbidden();
    subirDocumento($this, $propia->id)->assertForbidden();
    expect($documento->fresh()->visible_colaborador)->toBeTrue()->and($documento->fresh()->estado)->toBe('Disponible');
});

it('el categoría inactiva se identifica y no impide el acceso propio', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarDocumentos($usuario, $propia);
    $categoria = categoriaActiva(['nombre' => 'Antigua', 'estado' => 'Inactiva']);
    $documento = miDocumento($propia->id, ['categoria_id' => $categoria->id]);

    $this->getJson('/api/mi-ficha/documentos')->assertOk()->assertJsonPath('data.0.categoria.estado', 'Inactiva');
    $this->get("/api/mi-ficha/documentos/{$documento->id}/contenido")->assertOk();
});

it('exige sesión', function () {
    $this->getJson('/api/mi-ficha/documentos')->assertUnauthorized();
    $this->getJson('/api/mi-ficha/documentos/1/contenido')->assertUnauthorized();
});
