<?php

use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Database\Seeders\PersonalSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => almacenDeDocumentos());

it('lista por defecto solo los disponibles, del más reciente al más antiguo, con categoría y sin claves internas', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva(['nombre' => 'Contratos']);
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Antiguo', 'created_at' => '2026-01-01 10:00:00']);
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Reciente', 'created_at' => '2026-06-01 10:00:00']);
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Archivado'])->forceFill([])->save();
    DocumentoColaboradorModel::query()->where('titulo', 'Archivado')->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);

    $respuesta = $this->getJson(urlDocumentos($ficha->id))->assertOk();

    expect(array_column($respuesta->json('data'), 'titulo'))->toBe(['Reciente', 'Antiguo'])
        ->and($respuesta->json('data.0.categoria'))->toBe(['id' => $categoria->id, 'nombre' => 'Contratos', 'estado' => 'Activa'])
        ->and($respuesta->getContent())->not->toContain('object_key')->not->toContain('personal/');
});

it('la vista de archivados muestra quién y cuándo archivó', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $documento = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Viejo']);
    DocumentoColaboradorModel::query()->whereKey($documento->id)->update(['estado' => 'Archivado', 'archived_by' => $admin->id, 'archived_at' => now()]);
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Vigente']);

    $respuesta = $this->getJson(urlDocumentos($ficha->id).'?estado=Archivado')->assertOk();

    expect(array_column($respuesta->json('data'), 'titulo'))->toBe(['Viejo'])
        ->and($respuesta->json('data.0.estado'))->toBe('Archivado')
        ->and($respuesta->json('data.0.archivado_por'))->toBe($admin->id)
        ->and($respuesta->json('data.0.archivado_en'))->not->toBeNull();
});

it('busca por título sin distinguir mayúsculas ni tildes, filtra por categoría y pagina', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $a = categoriaActiva();
    $b = categoriaActiva();
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $a->id, 'titulo' => 'Certificación académica']);
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $b->id, 'titulo' => 'Contrato 100% firmado']);
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $b->id, 'titulo' => 'Contrato x']);

    $titulos = fn (string $q) => array_column($this->getJson(urlDocumentos($ficha->id).$q)->assertOk()->json('data'), 'titulo');

    expect($titulos('?q=CERTIFICACION'))->toBe(['Certificación académica'])
        ->and($titulos('?q='.urlencode('%')))->toBe(['Contrato 100% firmado'])
        ->and($titulos('?categoria_id='.$b->id))->toHaveCount(2)
        ->and($titulos('?q=zzz'))->toBe([]);

    $pagina = $this->getJson(urlDocumentos($ficha->id).'?per_page=2&page=2')->assertOk();
    expect($pagina->json('data'))->toHaveCount(1)->and($pagina->json('meta.total'))->toBe(3);
    $this->getJson(urlDocumentos($ficha->id).'?per_page=51')->assertUnprocessable();
    $this->getJson(urlDocumentos($ficha->id).'?estado=Otro')->assertUnprocessable();
});

it('identifica la categoría inactiva sin impedir el acceso', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva(['nombre' => 'Antigua', 'estado' => 'Inactiva']);
    $documento = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id]);

    $this->getJson(urlDocumentos($ficha->id))->assertOk()->assertJsonPath('data.0.categoria.estado', 'Inactiva')->assertJsonPath('data.0.categoria.nombre', 'Antigua');
    $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertOk();
});

it('descarga el PDF con las cabeceras seguras y el nombre fijo, también un archivado', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $contenido = pdfSintetico(2, 'Contenido real');
    $documento = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Título con ñ y "comillas" / barras'], $contenido);

    $respuesta = $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertOk();

    expect($respuesta->streamedContent())->toBe($contenido)
        ->and($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toBe("inline; filename=\"Documento_{$documento->id}.pdf\"")
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and((int) $respuesta->headers->get('Content-Length'))->toBe(strlen($contenido));

    $descarga = $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido").'?descarga=1')->assertOk();
    expect($descarga->headers->get('Content-Disposition'))->toStartWith('attachment;')->toContain("Documento_{$documento->id}.pdf");

    DocumentoColaboradorModel::query()->whereKey($documento->id)->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);
    expect($this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertOk()->streamedContent())->toBe($contenido);
});

it('responde 404 idéntico sin bytes para inexistente, de otra ficha y de otro tenant', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $otra = fichaDe();
    $ajena = fichaDe([], 2);
    $categoria = categoriaActiva();
    $deOtra = documentoConArchivo(['colaborador_id' => $otra->id, 'categoria_id' => $categoria->id]);
    $deAjena = documentoConArchivo(['tenant_id' => 2, 'colaborador_id' => $ajena->id, 'categoria_id' => categoriaActiva([], 2)->id]);

    $inexistente = $this->get(urlDocumentos($ficha->id, '/999999/contenido'))->assertNotFound();
    $cruzado = $this->get(urlDocumentos($ficha->id, "/{$deOtra->id}/contenido"))->assertNotFound();
    $this->get(urlDocumentos($ajena->id, "/{$deAjena->id}/contenido"))->assertNotFound();

    expect($cruzado->json())->toBe($inexistente->json())->and($inexistente->json('code'))->toBe('DOCUMENTO_NO_ENCONTRADO');
    $this->getJson(urlDocumentos($ajena->id))->assertNotFound();
});

it('un fallo del almacén al descargar responde 503 sin cuerpo parcial', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => categoriaActiva()->id]);
    app()->bind(AlmacenDeDocumentosPort::class, fn () => new class implements AlmacenDeDocumentosPort
    {
        public function guardar(string $rutaTemporal, string $clave): void {}

        public function leerComoFlujo(string $clave)
        {
            throw new AlmacenamientoNoDisponible;
        }

        public function eliminar(string $clave): bool
        {
            return true;
        }

        public function listar(string $prefijo): iterable
        {
            return [];
        }
    });

    $respuesta = $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertStatus(503);

    expect($respuesta->headers->get('Content-Type'))->not->toContain('application/pdf')
        ->and($respuesta->json('code'))->toBe('ALMACENAMIENTO_NO_DISPONIBLE');
});

it('exige sesión y deniega al colaborador en las rutas de administración', function () {
    $ficha = fichaDe();
    $documento = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => categoriaActiva()->id]);
    $this->getJson(urlDocumentos($ficha->id))->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson(urlDocumentos($ficha->id))->assertForbidden();
    $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertForbidden();
});

it('el seeder de personal carga documentos sintéticos de ejemplo de forma idempotente', function () {
    config(['hrms.seed_password' => 'secreto-de-prueba-123']);

    $this->seed(PersonalSeeder::class);
    $this->seed(PersonalSeeder::class);

    $filas = DocumentoColaboradorModel::query()->orderBy('titulo')->get();
    expect($filas)->toHaveCount(2)
        ->and($filas->pluck('estado')->sort()->values()->all())->toBe(['Archivado', 'Disponible'])
        ->and($filas->every(fn ($d) => Storage::disk(discoDeDocumentos())->exists($d->object_key)))->toBeTrue();
});
