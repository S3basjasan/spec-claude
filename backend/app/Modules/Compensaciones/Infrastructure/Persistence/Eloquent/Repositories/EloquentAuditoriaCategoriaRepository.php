<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Compensaciones\Domain\Repositories\AuditoriaCategoriaRepository;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;

class EloquentAuditoriaCategoriaRepository implements AuditoriaCategoriaRepository
{
    public function registrar(int $categoriaId, array $cambios, ?int $actorId): void
    {
        foreach ($cambios as $campo => [$anterior, $nuevo]) {
            AuditoriaCategoriaModel::query()->create([
                'categoria_reconocimiento_id' => $categoriaId,
                'campo' => $campo,
                'valor_anterior' => $anterior,
                'valor_nuevo' => $nuevo,
                'user_id' => $actorId,
            ]);
        }
    }
}
