<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentDocumentoRepository implements DocumentoRepositoryInterface
{
    public function porId(int $tenantId, int $colaboradorId, int $id): ?DocumentoColaborador
    {
        $modelo = DocumentoColaboradorModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)->whereKey($id)->first();

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function listar(int $tenantId, int $colaboradorId, ?string $q, ?int $categoriaId, string $estado, bool $soloVisibles, int $pagina, int $porPagina): array
    {
        $consulta = DocumentoColaboradorModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)->where('estado', $estado);

        if ($soloVisibles) {
            $consulta->where('visible_colaborador', true);
        }
        if ($categoriaId !== null) {
            $consulta->where('categoria_id', $categoriaId);
        }
        if ($q !== null && $q !== '') {
            $consulta->whereRaw("titulo_busqueda like ? escape '\\'", ['%'.$this->escaparLike($q).'%']);
        }

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderByDesc('created_at')->orderByDesc('id')
            ->forPage($pagina, $porPagina)
            ->get()
            ->map(fn (DocumentoColaboradorModel $m) => $this->aEntidad($m))
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    public function porOperacion(int $tenantId, string $operacionId): ?array
    {
        $modelo = DocumentoColaboradorModel::query()
            ->where('tenant_id', $tenantId)->where('operacion_id', $operacionId)->first();

        if ($modelo === null) {
            return null;
        }

        return ['documento' => $this->aEntidad($modelo), 'hash' => (string) $modelo->operacion_hash, 'actor' => (int) $modelo->created_by];
    }

    public function publicar(
        int $tenantId,
        int $colaboradorId,
        int $categoriaId,
        int $actorId,
        string $titulo,
        string $tituloBusqueda,
        string $objectKey,
        string $mimeType,
        int $sizeBytes,
        string $sha256,
        bool $visible,
        string $operacionId,
        string $operacionHash,
    ): DocumentoColaborador {
        try {
            $modelo = DocumentoColaboradorModel::query()->create([
                'tenant_id' => $tenantId,
                'colaborador_id' => $colaboradorId,
                'categoria_id' => $categoriaId,
                'titulo' => $titulo,
                'titulo_busqueda' => $tituloBusqueda,
                'object_key' => $objectKey,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'sha256' => $sha256,
                'visible_colaborador' => $visible,
                'estado' => 'Disponible',
                'version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'operacion_id' => $operacionId,
                'operacion_hash' => $operacionHash,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->aEntidad($modelo->refresh());
    }

    public function cambiarVisibilidad(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId, bool $visible): DocumentoColaborador
    {
        $filas = $this->actualizar($tenantId, $colaboradorId, $id, $version, [
            'visible_colaborador' => $visible,
            'version' => DB::raw('version + 1'),
            'updated_by' => $actorId,
            'updated_at' => now(),
        ]);

        return $this->resultado($filas, $tenantId, $colaboradorId, $id);
    }

    public function archivar(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId): DocumentoColaborador
    {
        $filas = $this->actualizar($tenantId, $colaboradorId, $id, $version, [
            'estado' => 'Archivado',
            'archived_by' => $actorId,
            'archived_at' => now(),
            'version' => DB::raw('version + 1'),
            'updated_by' => $actorId,
            'updated_at' => now(),
        ]);

        return $this->resultado($filas, $tenantId, $colaboradorId, $id);
    }

    public function claveReferenciada(string $objectKey): bool
    {
        return DocumentoColaboradorModel::query()->where('object_key', $objectKey)->exists();
    }

    /** @param  array<string, mixed>  $cambios */
    private function actualizar(int $tenantId, int $colaboradorId, int $id, int $version, array $cambios): int
    {
        return DocumentoColaboradorModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
            ->whereKey($id)->where('version', $version)->where('estado', 'Disponible')
            ->update($cambios);
    }

    private function resultado(int $filas, int $tenantId, int $colaboradorId, int $id): DocumentoColaborador
    {
        $documento = $this->porId($tenantId, $colaboradorId, $id);

        if ($filas === 0) {
            throw $documento === null ? new DocumentoNoEncontrado : new DocumentoModificado;
        }

        return $documento ?? throw new DocumentoNoEncontrado;
    }

    private function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }

    private function aEntidad(DocumentoColaboradorModel $m): DocumentoColaborador
    {
        return new DocumentoColaborador(
            (int) $m->id,
            (int) $m->tenant_id,
            (int) $m->colaborador_id,
            (int) $m->categoria_id,
            (string) $m->titulo,
            (string) $m->object_key,
            (string) $m->mime_type,
            (int) $m->size_bytes,
            (string) $m->sha256,
            (bool) $m->visible_colaborador,
            (string) $m->estado,
            (int) $m->version,
            (int) $m->created_by,
            (int) $m->updated_by,
            $m->archived_by === null ? null : (int) $m->archived_by,
            $m->archived_at?->toIso8601ZuluString(),
            (string) $m->operacion_hash,
            $m->created_at->toIso8601ZuluString(),
            $m->updated_at->toIso8601ZuluString(),
        );
    }
}
