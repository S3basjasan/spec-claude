<?php

use App\Modules\GestionPersonal\Application\DTO\CargaDeDocumentoDTO;
use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Application\Ports\CatalogoDeCategoriasPort;
use App\Modules\GestionPersonal\Application\UseCases\CargarDocumento;
use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeDocumento;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\AlmacenDeDocumentosDisco;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentDocumentoRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(fn () => almacenDeDocumentos());

function ejecutarCarga(int $fichaId, int $categoriaId, ?string $clave = null, ?string $contenido = null, string $titulo = 'Contrato'): mixed
{
    $ruta = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($ruta, $contenido ?? pdfSintetico());

    try {
        return app(CargarDocumento::class)->ejecutar(
            new ContextoDeTenant(1, 'Altavista S.A.', 'America/Guayaquil'),
            1,
            $fichaId,
            $clave ?? (string) Str::uuid(),
            new CargaDeDocumentoDTO($titulo, $categoriaId, false, $ruta),
        );
    } finally {
        @unlink($ruta);
    }
}

function totalDeObjetos(): int
{
    return count(Storage::disk(discoDeDocumentos())->allFiles('personal'));
}

/** Almacén que puede fallar al guardar o al eliminar, delegando en el disco real. */
function almacenInestable(bool $falleGuardar = false, bool $falleEliminar = false): void
{
    app()->bind(AlmacenDeDocumentosPort::class, fn () => new class($falleGuardar, $falleEliminar) implements AlmacenDeDocumentosPort
    {
        private AlmacenDeDocumentosDisco $real;

        public function __construct(private bool $falleGuardar, private bool $falleEliminar)
        {
            $this->real = new AlmacenDeDocumentosDisco;
        }

        public function guardar(string $rutaTemporal, string $clave): void
        {
            if ($this->falleGuardar) {
                throw new AlmacenamientoNoDisponible;
            }
            $this->real->guardar($rutaTemporal, $clave);
        }

        public function leerComoFlujo(string $clave)
        {
            return $this->real->leerComoFlujo($clave);
        }

        public function eliminar(string $clave): bool
        {
            return $this->falleEliminar ? false : $this->real->eliminar($clave);
        }

        public function listar(string $prefijo): iterable
        {
            return $this->real->listar($prefijo);
        }
    });
}

it('un fallo del almacén no deja documento ni éxito', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    almacenInestable(falleGuardar: true);

    expect(fn () => ejecutarCarga($ficha->id, $categoria->id))->toThrow(AlmacenamientoNoDisponible::class);
    expect(DocumentoColaboradorModel::query()->count())->toBe(0)->and(totalDeObjetos())->toBe(0);
});

it('si falla la inserción se elimina solo el objeto de este intento', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $otro = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id]);
    app()->bind(DocumentoRepositoryInterface::class, fn () => new class extends EloquentDocumentoRepository
    {
        public function publicar(int $tenantId, int $colaboradorId, int $categoriaId, int $actorId, string $titulo, string $tituloBusqueda, string $objectKey, string $mimeType, int $sizeBytes, string $sha256, bool $visible, string $operacionId, string $operacionHash): DocumentoColaborador
        {
            throw new RuntimeException('falla de base de datos');
        }
    });

    expect(fn () => ejecutarCarga($ficha->id, $categoria->id))->toThrow(RuntimeException::class, 'falla de base de datos');
    expect(DocumentoColaboradorModel::query()->count())->toBe(1)
        ->and(totalDeObjetos())->toBe(1)
        ->and(Storage::disk(discoDeDocumentos())->exists($otro->object_key))->toBeTrue();
});

it('si falla también la limpieza no hay falso éxito y el fallo queda registrado sin exponer la clave', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    almacenInestable(falleEliminar: true);
    app()->bind(DocumentoRepositoryInterface::class, fn () => new class extends EloquentDocumentoRepository
    {
        public function publicar(int $tenantId, int $colaboradorId, int $categoriaId, int $actorId, string $titulo, string $tituloBusqueda, string $objectKey, string $mimeType, int $sizeBytes, string $sha256, bool $visible, string $operacionId, string $operacionHash): DocumentoColaborador
        {
            throw new RuntimeException('falla');
        }
    });
    Log::spy();

    expect(fn () => ejecutarCarga($ficha->id, $categoria->id))->toThrow(RuntimeException::class);

    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje, array $contexto) => ! str_contains(json_encode($contexto), 'personal/'));
    expect(DocumentoColaboradorModel::query()->count())->toBe(0);
});

it('una categoría desactivada justo antes de publicar rechaza la carga sin documento ni objeto', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    app()->bind(CatalogoDeCategoriasPort::class, fn () => new class($categoria->id) implements CatalogoDeCategoriasPort
    {
        public function __construct(private int $id) {}

        public function activas(int $tenantId): Lectura
        {
            return Lectura::disponible([]);
        }

        public function porIds(int $tenantId, array $ids): Lectura
        {
            return Lectura::disponible([$this->id => ['id' => $this->id, 'nombre' => 'X', 'estado' => 'Activa']]);
        }

        public function confirmarActiva(int $tenantId, int $categoriaId): void
        {
            throw new CategoriaInactiva;
        }
    });

    expect(fn () => ejecutarCarga($ficha->id, $categoria->id))->toThrow(CategoriaInactiva::class);
    expect(DocumentoColaboradorModel::query()->count())->toBe(0)->and(totalDeObjetos())->toBe(0);
});

it('dos intentos con la misma operación: el ganador conserva su objeto y el perdedor elimina solo el suyo', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $clave = (string) Str::uuid();
    $contenido = pdfSintetico();
    $hash = (new ReglasDeDocumento)->hashDeOperacion($ficha->id, 1, 'Contrato', $categoria->id, false, hash('sha256', $contenido));

    // El ganador ya publicó (otro proceso): la operación existe, pero el intento actual aún no la ve.
    $ganador = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'operacion_id' => $clave, 'operacion_hash' => $hash, 'created_by' => 1], 'ganador');
    app()->singleton(DocumentoRepositoryInterface::class, fn () => new class extends EloquentDocumentoRepository
    {
        private bool $primera = true;

        public function porOperacion(int $tenantId, string $operacionId): ?array
        {
            if ($this->primera) {
                $this->primera = false;

                return null;
            }

            return parent::porOperacion($tenantId, $operacionId);
        }
    });

    $resultado = ejecutarCarga($ficha->id, $categoria->id, $clave, $contenido);

    expect($resultado->creado)->toBeFalse()
        ->and($resultado->documento->id)->toBe($ganador->id)
        ->and(Storage::disk(discoDeDocumentos())->get($ganador->object_key))->toBe('ganador')
        ->and(totalDeObjetos())->toBe(1)
        ->and(DocumentoColaboradorModel::query()->count())->toBe(1);
});

it('con la misma operación pero otro contenido el perdedor recibe el conflicto de clave reutilizada', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $clave = (string) Str::uuid();
    documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'operacion_id' => $clave, 'operacion_hash' => str_repeat('0', 64), 'created_by' => 1], 'ganador');
    app()->singleton(DocumentoRepositoryInterface::class, fn () => new class extends EloquentDocumentoRepository
    {
        private bool $primera = true;

        public function porOperacion(int $tenantId, string $operacionId): ?array
        {
            if ($this->primera) {
                $this->primera = false;

                return null;
            }

            return parent::porOperacion($tenantId, $operacionId);
        }
    });

    expect(fn () => ejecutarCarga($ficha->id, $categoria->id, $clave))->toThrow(ClaveDeOperacionReutilizada::class);
    expect(totalDeObjetos())->toBe(1);
});

it('un fallo de lectura del catálogo impide publicar sin documento ni objeto', function () {
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    app()->bind(CatalogoDeCategoriasPort::class, fn () => new class implements CatalogoDeCategoriasPort
    {
        public function activas(int $tenantId): Lectura
        {
            return Lectura::noDisponible();
        }

        public function porIds(int $tenantId, array $ids): Lectura
        {
            return Lectura::noDisponible();
        }

        public function confirmarActiva(int $tenantId, int $categoriaId): void {}
    });

    expect(fn () => ejecutarCarga($ficha->id, $categoria->id))->toThrow(InformacionNoDisponible::class);
    expect(DocumentoColaboradorModel::query()->count())->toBe(0)->and(totalDeObjetos())->toBe(0);
});
