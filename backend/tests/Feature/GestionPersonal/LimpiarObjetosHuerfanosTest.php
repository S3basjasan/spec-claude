<?php

use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Infrastructure\Adapters\AlmacenDeDocumentosDisco;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => almacenDeDocumentos());

function objetoAntiguo(string $clave, int $horas, string $contenido = 'x'): void
{
    $disco = Storage::disk(discoDeDocumentos());
    $disco->put($clave, $contenido);
    touch($disco->path($clave), time() - $horas * 3600);
}

it('elimina un huérfano de más de 24 horas y conserva uno reciente', function () {
    objetoAntiguo('personal/1/colaboradores/1/viejo.pdf', 48);
    objetoAntiguo('personal/1/colaboradores/1/reciente.pdf', 2);

    $this->artisan('documentos:limpiar-huerfanos')->assertSuccessful();

    $disco = Storage::disk(discoDeDocumentos());
    expect($disco->exists('personal/1/colaboradores/1/viejo.pdf'))->toBeFalse()
        ->and($disco->exists('personal/1/colaboradores/1/reciente.pdf'))->toBeTrue();
});

it('nunca elimina documentos publicados ni archivados aunque sean antiguos', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $disponible = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id]);
    $archivado = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id]);
    DB::table('documentos_colaborador')->where('id', $archivado->id)->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);
    $disco = Storage::disk(discoDeDocumentos());
    touch($disco->path($disponible->object_key), time() - 100 * 3600);
    touch($disco->path($archivado->object_key), time() - 100 * 3600);
    objetoAntiguo('personal/1/colaboradores/1/huerfano.pdf', 100);

    $this->artisan('documentos:limpiar-huerfanos')->assertSuccessful();

    expect($disco->exists($disponible->object_key))->toBeTrue()
        ->and($disco->exists($archivado->object_key))->toBeTrue()
        ->and($disco->exists('personal/1/colaboradores/1/huerfano.pdf'))->toBeFalse();
});

it('no elimina el objeto ganador de una carga cuya fila ya existe', function () {
    $ficha = fichaDe();
    $ganador = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => categoriaActiva()->id]);
    $disco = Storage::disk(discoDeDocumentos());
    touch($disco->path($ganador->object_key), time() - 200 * 3600);

    $this->artisan('documentos:limpiar-huerfanos --horas=1')->assertSuccessful();

    expect($disco->exists($ganador->object_key))->toBeTrue();
});

it('un fallo de eliminación de un objeto no detiene el resto y se informa', function () {
    objetoAntiguo('personal/1/colaboradores/1/a.pdf', 48);
    objetoAntiguo('personal/1/colaboradores/1/b.pdf', 48);
    app()->bind(AlmacenDeDocumentosPort::class, fn () => new class implements AlmacenDeDocumentosPort
    {
        private AlmacenDeDocumentosDisco $real;

        public function __construct()
        {
            $this->real = new AlmacenDeDocumentosDisco;
        }

        public function guardar(string $rutaTemporal, string $clave): void {}

        public function leerComoFlujo(string $clave)
        {
            return $this->real->leerComoFlujo($clave);
        }

        public function eliminar(string $clave): bool
        {
            return str_ends_with($clave, 'b.pdf') ? $this->real->eliminar($clave) : false;
        }

        public function listar(string $prefijo): iterable
        {
            return $this->real->listar($prefijo);
        }
    });

    $this->artisan('documentos:limpiar-huerfanos')->assertFailed();

    $disco = Storage::disk(discoDeDocumentos());
    expect($disco->exists('personal/1/colaboradores/1/a.pdf'))->toBeTrue()
        ->and($disco->exists('personal/1/colaboradores/1/b.pdf'))->toBeFalse();
});

it('está programado a diario', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('documentos:limpiar-huerfanos')->assertSuccessful();
});
