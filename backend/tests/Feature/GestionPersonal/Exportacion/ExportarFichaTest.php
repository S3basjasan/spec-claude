<?php

use App\Modules\GestionPersonal\Application\DTO\SnapshotDeFichaDTO;
use App\Modules\GestionPersonal\Application\Ports\RenderizadorDePdfPort;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;
use Illuminate\Support\Facades\DB;

/** Texto del PDF con pdftotext (poppler); la prueba se omite si no está instalado. */
function textoDePdf(string $pdf): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'gp_pdf_');
    file_put_contents($ruta, $pdf);
    $salida = shell_exec('pdftotext -enc UTF-8 -layout '.escapeshellarg($ruta).' - 2>NUL');
    @unlink($ruta);

    return (string) $salida;
}

function hayPdftotext(): bool
{
    return trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where pdftotext 2>NUL' : 'command -v pdftotext')) !== '';
}

function asociarExportacion(mixed $usuario, mixed $ficha, array $permisos = ['puede_exportar' => true]): ColaboradorCuentaModel
{
    return ColaboradorCuentaModel::query()->create(array_merge([
        'tenant_id' => $ficha->tenant_id, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id,
        'puede_consultar_ficha' => false, 'puede_consultar_documentos' => false,
    ], $permisos));
}

function contarEscrituras(): array
{
    return [
        DB::table('colaboradores')->count(), DB::table('vinculos_laborales')->count(),
        DB::table('formaciones_academicas')->count(), DB::table('experiencias_anteriores')->count(),
        DB::table('documentos_colaborador')->count(),
        DB::table('colaboradores')->max('updated_at'),
    ];
}

it('el administrador exporta una ficha con las cabeceras seguras y sin escribir nada (EXP-CA01)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['nombres' => 'María José', 'apellidos' => 'Ñúñez Peña']);
    FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id, 'titulo_programa' => 'Ingeniería Civil']);
    $antes = contarEscrituras();

    $respuesta = $this->get("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toBe('attachment; filename="Ficha_'.$ficha->codigo.'.pdf"')
        ->and($respuesta->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and(substr($respuesta->getContent(), 0, 5))->toBe('%PDF-')
        ->and(contarEscrituras())->toBe($antes);

    if (hayPdftotext()) {
        $texto = textoDePdf($respuesta->getContent());
        expect($texto)->toContain($ficha->codigo)->toContain('María José')->toContain('Ñúñez Peña')
            ->toContain('Ingeniería Civil')->toMatch('/Página 1 de \d+/u');
    }
});

it('sin autenticación es 401 y un colaborador no usa la ruta administrativa (EXP-CA02)', function () {
    $ficha = fichaDe();
    $this->getJson("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertForbidden();
});

it('una ficha inexistente o de otro tenant responde igual 404', function () {
    actuarComoAdminDeTenant();
    $otroTenant = fichaDe([], tenantDos()->id);

    $inexistente = $this->getJson('/api/colaboradores/999999/exportacion.pdf')->assertNotFound();
    $ajena = $this->getJson("/api/colaboradores/{$otroTenant->id}/exportacion.pdf")->assertNotFound();

    expect($ajena->json())->toBe($inexistente->json());
});

it('los ausentes se etiquetan y la movilidad queda como No informada (EXP-CA04)', function () {
    if (! hayPdftotext()) {
        $this->markTestSkipped('pdftotext no está instalado.');
    }
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['telefono' => null, 'direccion' => null, 'numero_hijos' => 0]);

    $texto = textoDePdf($this->get("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertOk()->getContent());

    expect($texto)->toContain('Teléfono: No registrado')->toContain('Número de hijos: 0')
        ->toContain('No informada')->toContain('Sin registros')->toContain('Sin vínculo vigente');
});

it('formación y experiencia extensas continúan en varias páginas sin recortar registros (EXP-CA06)', function () {
    if (! hayPdftotext()) {
        $this->markTestSkipped('pdftotext no está instalado.');
    }
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $largo = str_repeat('Observación extensa con acentos áéíóú ñ y texto largo. ', 40).str_repeat('X', 120);
    foreach (range(1, 25) as $i) {
        FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id, 'titulo_programa' => "Programa número $i", 'observaciones' => $largo]);
        ExperienciaAnteriorModel::factory()->create(['colaborador_id' => $ficha->id, 'cargo' => "Cargo número $i", 'funciones' => $largo]);
    }

    $pdf = $this->get("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertOk()->getContent();
    $texto = textoDePdf($pdf);

    preg_match('/Página \d+ de (\d+)/u', $texto, $m);
    expect((int) ($m[1] ?? 0))->toBeGreaterThan(3);
    foreach (range(1, 25) as $i) {
        expect($texto)->toContain("Programa número $i")->toContain("Cargo número $i");
    }
    expect(substr_count($texto, 'XXXXXXXXXXXXXXXXXXXX'))->toBeGreaterThanOrEqual(50);
});

it('el texto de usuario se escapa y no se interpreta como marcado', function () {
    if (! hayPdftotext()) {
        $this->markTestSkipped('pdftotext no está instalado.');
    }
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['direccion' => '<b>negrita</b> <img src="http://127.0.0.1/x.png"> {{ 1+1 }}']);

    $texto = textoDePdf($this->get("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertOk()->getContent());

    expect($texto)->toContain('<b>negrita</b>')->toContain('{{ 1+1 }}');
});

it('el colaborador exporta la propia solo con el permiso de exportación, sin los demás (EXP-CA03)', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarExportacion($usuario, $propia, ['puede_exportar' => true, 'puede_consultar_ficha' => false, 'puede_consultar_documentos' => false]);

    $respuesta = $this->get('/api/mi-ficha/exportacion.pdf')->assertOk();
    expect(substr($respuesta->getContent(), 0, 5))->toBe('%PDF-')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain("Ficha_{$propia->codigo}.pdf");

    $this->getJson('/api/mi-ficha')->assertForbidden();
    $this->getJson('/api/mi-ficha/documentos')->assertForbidden();
});

it('con los demás permisos pero sin exportación se deniega, y sin ficha asociada también', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarExportacion($usuario, $propia, ['puede_exportar' => false, 'puede_consultar_ficha' => true, 'puede_consultar_documentos' => true]);

    $this->getJson('/api/mi-ficha/exportacion.pdf')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_EXPORTACION');

    ColaboradorCuentaModel::query()->delete();
    $this->getJson('/api/mi-ficha/exportacion.pdf')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');
});

it('la ruta propia ignora identificadores enviados y entrega solo la ficha asociada', function () {
    if (! hayPdftotext()) {
        $this->markTestSkipped('pdftotext no está instalado.');
    }
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe(['nombres' => 'Propia']);
    $ajena = fichaDe(['nombres' => 'Ajenaficha']);
    asociarExportacion($usuario, $propia);

    $texto = textoDePdf($this->get("/api/mi-ficha/exportacion.pdf?colaborador={$ajena->id}&colaborador_id={$ajena->id}")->assertOk()->getContent());

    expect($texto)->toContain('Propia')->not->toContain('Ajenaficha');
});

it('si Estructura falla no hay archivo y responde 503 EXPORTACION_NO_DISPONIBLE (EXP-CA05)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $posicion = posicionAsignable();
    vinculoDe($ficha->id, hoyDelTenant(-30), null, $posicion->id);
    lectorEstructuraFalso($this, ['posiciones' => Lectura::noDisponible()]);

    $respuesta = $this->getJson("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertStatus(503);

    expect($respuesta->json('code'))->toBe('EXPORTACION_NO_DISPONIBLE')
        ->and($respuesta->headers->get('Content-Type'))->toContain('application/json');
});

it('si el renderizador falla responde 503 sin entregar un archivo parcial', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    app()->bind(RenderizadorDePdfPort::class, fn () => new class implements RenderizadorDePdfPort
    {
        public function renderizar(SnapshotDeFichaDTO $snapshot): string
        {
            throw new ExportacionNoDisponible;
        }
    });

    $respuesta = $this->getJson("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertStatus(503);

    expect($respuesta->headers->get('Content-Type'))->toContain('application/json')
        ->and($respuesta->getContent())->not->toContain('%PDF');
});

it('si se revoca el permiso antes de la entrega no se entrega el documento (EXP-CA07)', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe();
    asociarExportacion($usuario, $propia);
    app()->bind(RenderizadorDePdfPort::class, fn () => new class implements RenderizadorDePdfPort
    {
        public function renderizar(SnapshotDeFichaDTO $snapshot): string
        {
            ColaboradorCuentaModel::query()->update(['puede_exportar' => false]);

            return "%PDF-1.4\n%%EOF";
        }
    });

    $respuesta = $this->getJson('/api/mi-ficha/exportacion.pdf')->assertForbidden();

    expect($respuesta->getContent())->not->toContain('%PDF');
});

it('el administrador no recibe el archivo si la ficha desaparece antes de entregar', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    app()->bind(RenderizadorDePdfPort::class, fn () => new class($ficha->id) implements RenderizadorDePdfPort
    {
        public function __construct(private int $id) {}

        public function renderizar(SnapshotDeFichaDTO $snapshot): string
        {
            DB::table('colaboradores')->where('id', $this->id)->delete();

            return "%PDF-1.4\n%%EOF";
        }
    });

    $this->getJson("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertNotFound();
});

it('un ingreso programado no se presenta como vigente (EXP-CA08)', function () {
    if (! hayPdftotext()) {
        $this->markTestSkipped('pdftotext no está instalado.');
    }
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $posicion = posicionAsignable();
    vinculoDe($ficha->id, hoyDelTenant(15), null, $posicion->id);

    $texto = textoDePdf($this->get("/api/colaboradores/{$ficha->id}/exportacion.pdf")->assertOk()->getContent());

    expect($texto)->toContain('Ingreso programado')->toContain('Sin vínculo vigente')->not->toContain('Vínculo vigente');
});
