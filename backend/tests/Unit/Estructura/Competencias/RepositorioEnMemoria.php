<?php

namespace Tests\Unit\Estructura\Competencias;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaSinCambio;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ReglasDeCategorias;
use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use App\Modules\Estructura\Domain\ValueObjects\PaginaCompetencias;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

/** Doble en memoria del repositorio de competencias para probar los casos de uso sin base de datos. */
final class RepositorioEnMemoria implements CompetenciaRepositoryInterface
{
    /** @var array<int, Competencia> */
    public array $competencias = [];

    public int $siguienteId = 1;

    public int $siguienteGradoId = 1;

    public ?string $ultimoQ = null;

    public ?TipoCompetencia $ultimoTipo = null;

    public ?EstadoCompetencia $ultimoEstado = null;

    public ?int $ultimoActor = null;

    /** @var array<int, array{0: int, 1: string, 2: string, 3: ?int}> */
    public array $historial = [];

    /** @var array<int, array{0: int, 1: string, 2: string, 3: ?int}> */
    public array $historialDeEscala = [];

    /** @var array<int, EscalaDeGrados> */
    public array $escalas;

    public function __construct()
    {
        $this->escalas = [
            1 => new EscalaDeGrados(1, 'Letras', true, [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D']),
            2 => new EscalaDeGrados(2, 'Números', false, [1 => 'Nivel 1', 2 => 'Nivel 2', 3 => 'Nivel 3', 4 => 'Nivel 4']),
            3 => new EscalaDeGrados(3, 'Categorías', false, [1 => 'Excelente', 2 => 'Muy buena', 3 => 'Buena', 4 => 'Normal']),
        ];
    }

    public function listar(?string $q = null, ?TipoCompetencia $tipo = null, ?EstadoCompetencia $estado = null, int $pagina = 1, int $porPagina = 12): PaginaCompetencias
    {
        $this->ultimoQ = $q;
        $this->ultimoTipo = $tipo;
        $this->ultimoEstado = $estado;
        $items = array_values($this->competencias);

        return new PaginaCompetencias(array_slice($items, ($pagina - 1) * $porPagina, $porPagina), count($items));
    }

    public function obtener(int $id): ?Competencia
    {
        return $this->competencias[$id] ?? null;
    }

    public function obtenerPorIds(array $ids): array
    {
        return array_values(array_intersect_key($this->competencias, array_flip($ids)));
    }

    public function obtenerActivas(): array
    {
        return array_values(array_filter($this->competencias, fn (Competencia $c) => $c->estaActiva()));
    }

    public function obtenerTodas(): array
    {
        return array_values($this->competencias);
    }

    public function crear(
        string $nombre,
        string $descripcion,
        TipoCompetencia $tipo,
        int $escalaId,
        ?array $categorias,
        array $grados,
        array $comportamientos,
        array $preguntas,
        ?int $actorId,
    ): Competencia {
        foreach ($this->competencias as $existente) {
            if (ReglasDeCategorias::clave($existente->nombre) === ReglasDeCategorias::clave($nombre)) {
                throw new NombreDuplicado;
            }
        }

        $escala = $this->escalas[$escalaId] ?? throw new EscalaNoEncontrada;
        $this->ultimoActor = $actorId;
        $id = $this->siguienteId++;
        $porOrden = $categorias === null ? null : array_combine([1, 2, 3, 4], array_values($categorias));

        $detalle = [];
        $mapa = [];
        foreach ([1, 2, 3, 4] as $orden) {
            $clave = GradoLetra::desdeOrden($orden);
            $mapa[$clave] = $grados[$orden - 1];
            $detalle[] = new GradoDeCompetencia(
                $this->siguienteGradoId++,
                $orden,
                $clave,
                $escala->etiqueta($orden, $porOrden),
                $grados[$orden - 1],
                array_values($comportamientos[$orden - 1]),
            );
        }

        return $this->competencias[$id] = new Competencia(
            id: $id,
            codigo: sprintf('COMP-%04d', $id),
            nombre: $nombre,
            descripcion: $descripcion,
            tipo: $tipo,
            estado: EstadoCompetencia::Activo,
            version: 1,
            creadoPor: $actorId,
            grados: $mapa,
            escala: $escala,
            gradosDetalle: $detalle,
            preguntas: array_values($preguntas),
            categorias: $porOrden,
        );
    }

    public function actualizar(int $id, int $versionEsperada, string $nombre, string $descripcion, TipoCompetencia $tipo, int $escalaId, ?array $categorias, array $grados, ?int $actorId): Competencia
    {
        $actual = $this->vigente($id, $versionEsperada);
        $escala = $this->escalas[$escalaId] ?? throw new EscalaNoEncontrada;
        $porOrden = $categorias === null ? $actual->categorias : array_combine([1, 2, 3, 4], array_values($categorias));

        $detalle = [];
        $mapa = [];
        foreach ($actual->gradosDetalle as $indice => $grado) {
            $mapa[$grado->clave] = $grados[$indice];
            $detalle[] = new GradoDeCompetencia($grado->id, $grado->orden, $grado->clave, $escala->etiqueta($grado->orden, $porOrden), $grados[$indice], $grado->comportamientos);
        }

        return $this->reemplazar($actual, [
            'nombre' => $nombre, 'descripcion' => $descripcion, 'tipo' => $tipo, 'escala' => $escala,
            'categorias' => $porOrden, 'grados' => $mapa, 'gradosDetalle' => $detalle,
        ], $actorId);
    }

    public function actualizarComportamientos(int $id, int $versionEsperada, array $comportamientos, ?int $actorId): Competencia
    {
        $actual = $this->vigente($id, $versionEsperada);

        $detalle = [];
        foreach ($actual->gradosDetalle as $indice => $grado) {
            $detalle[] = new GradoDeCompetencia($grado->id, $grado->orden, $grado->clave, $grado->etiqueta, $grado->descripcion, array_values($comportamientos[$indice]));
        }

        return $this->reemplazar($actual, ['gradosDetalle' => $detalle], $actorId);
    }

    public function actualizarPreguntas(int $id, int $versionEsperada, array $preguntas, ?int $actorId): Competencia
    {
        return $this->reemplazar($this->vigente($id, $versionEsperada), ['preguntas' => array_values($preguntas)], $actorId);
    }

    public function aplicarEscala(int $id, int $escalaId, ?int $actorId): Competencia
    {
        $actual = $this->competencias[$id] ?? throw new CompetenciaNoEncontrada;
        $escala = $this->escalas[$escalaId] ?? throw new EscalaNoEncontrada;

        if ($actual->escala?->id === $escalaId) {
            throw new EscalaSinCambio;
        }

        $this->historialDeEscala[] = [$id, (string) $actual->escala?->nombre, $escala->nombre, $actorId];

        $detalle = array_map(
            fn (GradoDeCompetencia $g) => new GradoDeCompetencia($g->id, $g->orden, $g->clave, $escala->etiqueta($g->orden, $actual->categorias), $g->descripcion, $g->comportamientos),
            $actual->gradosDetalle,
        );

        return $this->reemplazar($actual, ['escala' => $escala, 'gradosDetalle' => $detalle], $actorId);
    }

    public function cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia
    {
        $actual = $this->competencias[$id] ?? throw new CompetenciaNoEncontrada;

        if ($actual->estado === $nuevo) {
            throw new EstadoSinCambio;
        }

        $this->historial[] = [$id, $actual->estado->value, $nuevo->value, $actorId];

        return $this->reemplazar($actual, ['estado' => $nuevo], $actorId);
    }

    // ------------------------------------------------------------ Apoyo

    private function vigente(int $id, int $versionEsperada): Competencia
    {
        $actual = $this->competencias[$id] ?? throw new CompetenciaNoEncontrada;

        if ($actual->version !== $versionEsperada) {
            throw new ConflictoDeVersion;
        }

        return $actual;
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function reemplazar(Competencia $actual, array $cambios, ?int $actorId): Competencia
    {
        $this->ultimoActor = $actorId;

        $datos = array_merge([
            'id' => $actual->id, 'codigo' => $actual->codigo, 'nombre' => $actual->nombre, 'descripcion' => $actual->descripcion,
            'tipo' => $actual->tipo, 'estado' => $actual->estado, 'version' => $actual->version + 1,
            'creadoPor' => $actual->creadoPor, 'modificadoPor' => $actorId, 'grados' => $actual->grados,
            'escala' => $actual->escala, 'gradosDetalle' => $actual->gradosDetalle, 'preguntas' => $actual->preguntas,
            'categorias' => $actual->categorias, 'creadaEn' => $actual->creadaEn, 'modificadaEn' => $actual->modificadaEn,
        ], $cambios);

        return $this->competencias[$actual->id] = new Competencia(...$datos);
    }
}
