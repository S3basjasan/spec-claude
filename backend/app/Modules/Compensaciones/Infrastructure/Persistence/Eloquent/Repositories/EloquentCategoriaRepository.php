<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Exceptions\CodigoDeCategoriaEnUso;
use App\Modules\Compensaciones\Domain\Exceptions\NombreDeCategoriaRepetido;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\FiltroCategorias;
use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\Indicador;
use App\Modules\Compensaciones\Domain\ValueObjects\NombreCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\PaginaCategorias;
use App\Modules\Compensaciones\Domain\ValueObjects\PuntuacionBase;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\IndicadorReconocimientoModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentCategoriaRepository implements CategoriaRepository
{
    private const FORMATO = 'Y-m-d H:i:s.u';

    public function enTransaccion(callable $accion): mixed
    {
        return DB::transaction($accion);
    }

    public function buscar(FiltroCategorias $filtro): PaginaCategorias
    {
        $consulta = CategoriaReconocimientoModel::query();

        if ($filtro->estado !== null) {
            $consulta->where('estado', $filtro->estado->value);
        }

        if ($filtro->competenciaId !== null) {
            $consulta->where('competencia_id', $filtro->competenciaId);
        }

        if ($filtro->texto !== null) {
            $this->filtrarPorTexto($consulta, $filtro);
        }

        $total = (clone $consulta)->count();
        $ultima = max(1, (int) ceil($total / FiltroCategorias::POR_PAGINA));
        $pagina = min(max(1, $filtro->pagina), $ultima);

        $modelos = $consulta
            ->orderByRaw('LENGTH(codigo), codigo')
            ->forPage($pagina, FiltroCategorias::POR_PAGINA)
            ->get();

        return new PaginaCategorias(
            $modelos->map(fn (CategoriaReconocimientoModel $m) => $this->aEntidad($m, false))->all(),
            $total,
            $pagina,
            FiltroCategorias::POR_PAGINA,
            $ultima,
        );
    }

    public function porId(int $id): ?Categoria
    {
        $modelo = CategoriaReconocimientoModel::query()->with('indicadores')->find($id);

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return CategoriaReconocimientoModel::query()
            ->with('indicadores')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (CategoriaReconocimientoModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function activas(): array
    {
        return CategoriaReconocimientoModel::query()
            ->with('indicadores')
            ->where('estado', EstadoCategoria::Activo->value)
            ->orderByRaw('LENGTH(codigo), codigo')
            ->get()
            ->map(fn (CategoriaReconocimientoModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function existeNombre(string $nombreNormalizado, ?int $excluirId = null): bool
    {
        return CategoriaReconocimientoModel::query()
            ->where('nombre_normalizado', $nombreNormalizado)
            ->when($excluirId !== null, fn (Builder $q) => $q->where('id', '!=', $excluirId))
            ->exists();
    }

    public function siguienteCodigo(): string
    {
        // El código más largo y, entre iguales, el mayor, es el de número más alto (CAT-999 < CAT-1000).
        $ultimo = CategoriaReconocimientoModel::query()
            ->orderByRaw('LENGTH(codigo) desc, codigo desc')
            ->value('codigo');

        $siguiente = $ultimo === null ? 1 : ((int) substr((string) $ultimo, 4)) + 1;

        return 'CAT-'.str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }

    public function crear(Categoria $nueva, string $codigo, int $actorId): Categoria
    {
        try {
            $modelo = CategoriaReconocimientoModel::query()->create([
                'codigo' => $codigo,
                'nombre' => $nueva->nombre->valor,
                'nombre_normalizado' => $nueva->nombre->normalizado(),
                'descripcion' => $nueva->descripcion,
                'competencia_id' => $nueva->competenciaId,
                'puntuacion_base' => $nueva->puntuacion->valor,
                'icono' => $nueva->icono->value,
                'estado' => EstadoCategoria::Activo->value,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // El mensaje del driver nombra la restricción violada; el de Laravel incluye todo el SQL.
            $motivo = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            if (str_contains($motivo, 'nombre_normalizado')) {
                throw new NombreDeCategoriaRepetido;
            }

            if (str_contains($motivo, 'codigo')) {
                throw new CodigoDeCategoriaEnUso;
            }

            throw $e;
        }

        $this->reemplazarIndicadores((int) $modelo->id, $nueva->indicadores);

        return $this->porId((int) $modelo->id) ?? throw new \LogicException('La categoría recién creada no se pudo leer.');
    }

    public function actualizarSiVigente(Categoria $actual, Categoria $propuesta, int $actorId): ?Categoria
    {
        $afectadas = $this->actualizarCondicional($actual, [
            'nombre' => $propuesta->nombre->valor,
            'nombre_normalizado' => $propuesta->nombre->normalizado(),
            'descripcion' => $propuesta->descripcion,
            'competencia_id' => $propuesta->competenciaId,
            'puntuacion_base' => $propuesta->puntuacion->valor,
            'icono' => $propuesta->icono->value,
            'updated_by' => $actorId,
        ]);

        if ($afectadas === 0) {
            return null;
        }

        $this->reemplazarIndicadores((int) $actual->id, $propuesta->indicadores);

        return $this->porId((int) $actual->id);
    }

    public function cambiarEstadoSiVigente(Categoria $actual, EstadoCategoria $estado, int $actorId): ?Categoria
    {
        $afectadas = $this->actualizarCondicional($actual, [
            'estado' => $estado->value,
            'updated_by' => $actorId,
        ]);

        return $afectadas === 0 ? null : $this->porId((int) $actual->id);
    }

    /**
     * UPDATE ... WHERE id = ? AND updated_at = <versión leída>: si otro proceso la modificó, no afecta ninguna fila.
     *
     * @param  array<string, mixed>  $campos
     */
    private function actualizarCondicional(Categoria $actual, array $campos): int
    {
        $nuevaVersion = $this->nuevaVersion((string) $actual->version);

        return CategoriaReconocimientoModel::query()
            ->where('id', $actual->id)
            ->where('updated_at', $actual->version)
            ->update($campos + ['updated_at' => $nuevaVersion]);
    }

    /** Hora actual con microsegundos; si coincidiera con la versión anterior, avanza un microsegundo. */
    private function nuevaVersion(string $anterior): string
    {
        $ahora = new DateTimeImmutable('now', new DateTimeZone(config('app.timezone')));
        $texto = $ahora->format(self::FORMATO);

        return $texto === $anterior ? $ahora->modify('+1 microsecond')->format(self::FORMATO) : $texto;
    }

    /**
     * @param  array<int, Indicador>  $indicadores
     */
    private function reemplazarIndicadores(int $categoriaId, array $indicadores): void
    {
        IndicadorReconocimientoModel::query()->where('categoria_reconocimiento_id', $categoriaId)->delete();

        foreach ($indicadores as $indicador) {
            IndicadorReconocimientoModel::query()->create([
                'categoria_reconocimiento_id' => $categoriaId,
                'orden' => $indicador->orden,
                'texto' => $indicador->texto,
                'texto_normalizado' => $indicador->normalizado(),
            ]);
        }
    }

    private function filtrarPorTexto(Builder $consulta, FiltroCategorias $filtro): void
    {
        $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filtro->texto).'%';

        $consulta->where(function (Builder $q) use ($patron, $filtro) {
            $q->whereRaw("nombre_normalizado LIKE ? ESCAPE '\\'", [$patron]);

            if ($filtro->competenciaIdsPorNombre !== []) {
                $q->orWhereIn('competencia_id', $filtro->competenciaIdsPorNombre);
            }
        });
    }

    private function aEntidad(CategoriaReconocimientoModel $m, bool $conIndicadores = true): Categoria
    {
        $indicadores = [];

        if ($conIndicadores) {
            $indicadores = $m->indicadores
                ->map(fn (IndicadorReconocimientoModel $i) => new Indicador($i->texto, (int) $i->orden))
                ->all();
        }

        $version = $m->getRawOriginal('updated_at');

        return new Categoria(
            (int) $m->id,
            $m->codigo,
            new NombreCategoria($m->nombre),
            $m->descripcion,
            (int) $m->competencia_id,
            new PuntuacionBase((int) $m->puntuacion_base),
            Icono::from($m->icono),
            EstadoCategoria::from($m->estado),
            $indicadores,
            $this->fecha($m->getRawOriginal('created_at')),
            $this->fecha($version),
            $m->created_by === null ? null : (int) $m->created_by,
            $m->updated_by === null ? null : (int) $m->updated_by,
            $version === null ? null : (string) $version,
        );
    }

    private function fecha(?string $crudo): ?DateTimeImmutable
    {
        if ($crudo === null) {
            return null;
        }

        $zona = new DateTimeZone(config('app.timezone'));

        return DateTimeImmutable::createFromFormat(self::FORMATO, $crudo, $zona) ?: new DateTimeImmutable($crudo, $zona);
    }
}
