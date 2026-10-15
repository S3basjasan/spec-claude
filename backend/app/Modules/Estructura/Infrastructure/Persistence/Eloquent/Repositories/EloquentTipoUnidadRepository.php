<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\TipoUnidad;
use App\Modules\Estructura\Domain\Exceptions\CadenaInvalida;
use App\Modules\Estructura\Domain\Exceptions\CadenaModificada;
use App\Modules\Estructura\Domain\Repositories\TipoUnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\CadenaDeTipos;
use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDeTipo;
use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Support\Facades\DB;

final class EloquentTipoUnidadRepository implements TipoUnidadRepositoryInterface
{
    private const ENTIDAD = 'tipo_unidad';

    private const TABLA = 'tipos_unidad';

    public function __construct(
        private readonly CadenaDeTipos $cadena,
        private readonly ComparadorDeCambiosDeTipo $comparador,
    ) {}

    public function todos(): array
    {
        return TipoUnidadModel::query()->withCount('unidades')->orderBy('nivel')->get()
            ->map(fn (TipoUnidadModel $tipo) => $this->entidad($tipo))
            ->all();
    }

    public function obtener(int $id): ?TipoUnidad
    {
        $tipo = TipoUnidadModel::query()->withCount('unidades')->find($id);

        return $tipo === null ? null : $this->entidad($tipo);
    }

    public function crear(string $nombre, ?int $actorId): TipoUnidad
    {
        $nombre = trim($nombre);

        // Evita chocar con la restricción única de la base con un error técnico; se repite dentro de la transacción.
        $error = $this->cadena->errorDeNombre($nombre, DB::table(self::TABLA)->pluck('nombre')->all());
        if ($error !== null) {
            throw new CadenaInvalida(['nombre' => $error]);
        }

        $id = DB::transaction(function () use ($nombre, $actorId) {
            // El primer enunciado es una escritura (toma el candado de SQLite): el nivel sale del último vigente.
            $id = (int) DB::table(self::TABLA)->insertGetId([
                'nombre' => $nombre,
                'nivel' => DB::raw('(select coalesce(max(nivel), 0) + 1 from tipos_unidad)'),
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $otros = DB::table(self::TABLA)->where('id', '!=', $id)->pluck('nombre')->all();
            $error = $this->cadena->errorDeNombre($nombre, $otros);
            if ($error !== null) {
                throw new CadenaInvalida(['nombre' => $error]);
            }

            $nivel = (int) DB::table(self::TABLA)->where('id', $id)->value('nivel');
            $this->registrar($id, $this->comparador->comparar(null, ['nombre' => $nombre, 'nivel' => $nivel]), $actorId);

            return $id;
        });

        return $this->obtener($id) ?? throw new CadenaInvalida(['nombre' => 'El tipo no existe.']);
    }

    public function guardarCadena(string $version, array $borrador, ?int $actorId): array
    {
        DB::transaction(function () use ($version, $borrador, $actorId) {
            // Primer enunciado: una escritura que no cambia nada y toma el candado de escritura; lo demás se valida
            // contra el estado vigente.
            DB::table(self::TABLA)->update(['nombre' => DB::raw('nombre')]);

            $actuales = $this->todos();
            if ($this->cadena->token($actuales) !== $version) {
                throw new CadenaModificada;
            }

            $errores = $this->cadena->erroresDelBorrador($actuales, $borrador);
            if ($errores !== []) {
                throw new CadenaInvalida($errores);
            }

            $conflictos = $this->cadena->unidadesEnConflicto($this->nodos(), $borrador);
            if ($conflictos !== []) {
                throw new CadenaInvalida([], $conflictos);
            }

            $porId = [];
            foreach ($actuales as $tipo) {
                $porId[$tipo->id] = $tipo;
            }
            $niveles = $this->cadena->nivelesResultantes($borrador);

            $cambios = [];
            foreach ($borrador as $item) {
                $actual = $porId[$item->id];
                $nombre = trim($item->nombre);
                if ($actual->nombre !== $nombre || $actual->nivel !== $niveles[$item->id]) {
                    $cambios[$item->id] = ['nombre' => $nombre, 'nivel' => $niveles[$item->id], 'actual' => $actual];
                }
            }
            if ($cambios === []) {
                return;
            }

            // Paso 1: libera los niveles y los nombres de los tipos que cambian (la unicidad se valida fila por fila).
            foreach ($cambios as $id => $cambio) {
                DB::table(self::TABLA)->where('id', $id)->update([
                    'nivel' => -$cambio['actual']->nivel,
                    'nombre' => "~$id",
                ]);
            }
            // Paso 2: valores finales.
            foreach ($cambios as $id => $cambio) {
                DB::table(self::TABLA)->where('id', $id)->update([
                    'nivel' => $cambio['nivel'],
                    'nombre' => $cambio['nombre'],
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);
                $this->registrar(
                    $id,
                    $this->comparador->comparar(
                        ['nombre' => $cambio['actual']->nombre, 'nivel' => $cambio['actual']->nivel],
                        ['nombre' => $cambio['nombre'], 'nivel' => $cambio['nivel']],
                    ),
                    $actorId,
                );
            }
        });

        return $this->todos();
    }

    /**
     * Todas las unidades como nodos, con el tipo y el nivel vigentes.
     *
     * @return array<int, NodoDeUnidad>
     */
    private function nodos(): array
    {
        $nodos = [];
        foreach (UnidadModel::query()->with('tipo')->get() as $unidad) {
            $nodos[$unidad->id] = new NodoDeUnidad(
                $unidad->id,
                (string) $unidad->codigo,
                $unidad->nombre,
                new TipoDeUnidad($unidad->tipo->id, $unidad->tipo->nombre, (int) $unidad->tipo->nivel),
                $unidad->unidad_padre_id === null ? null : (int) $unidad->unidad_padre_id,
                EstadoUnidad::from($unidad->estado),
            );
        }

        return $nodos;
    }

    /**
     * @param  array<int, CambioAuditado>  $cambios
     */
    private function registrar(int $tipoId, array $cambios, ?int $actorId): void
    {
        foreach ($cambios as $cambio) {
            AuditoriaCambioModel::query()->create([
                'entidad' => self::ENTIDAD,
                'entidad_id' => $tipoId,
                'campo' => $cambio->campo,
                'valor_anterior' => $cambio->valorAnterior,
                'valor_nuevo' => $cambio->valorNuevo,
                'user_id' => $actorId,
            ]);
        }
    }

    private function entidad(TipoUnidadModel $tipo): TipoUnidad
    {
        return new TipoUnidad($tipo->id, $tipo->nombre, (int) $tipo->nivel, (int) ($tipo->unidades_count ?? 0));
    }
}
