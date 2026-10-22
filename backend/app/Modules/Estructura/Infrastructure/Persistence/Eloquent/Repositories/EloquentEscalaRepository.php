<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Repositories\EscalaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoValorModel;

final class EloquentEscalaRepository implements EscalaRepositoryInterface
{
    public function listar(): array
    {
        return TipoGradoModel::query()->with('valores')->orderBy('id')->get()
            ->map(fn (TipoGradoModel $tipo) => $this->aEscala($tipo))->all();
    }

    public function obtener(int $id): ?EscalaDeGrados
    {
        $tipo = TipoGradoModel::query()->with('valores')->find($id);

        return $tipo === null ? null : $this->aEscala($tipo);
    }

    private function aEscala(TipoGradoModel $tipo): EscalaDeGrados
    {
        return new EscalaDeGrados(
            $tipo->id,
            $tipo->nombre,
            (bool) $tipo->activo,
            $tipo->valores->mapWithKeys(fn (TipoGradoValorModel $v) => [(int) $v->orden => $v->valor])->all(),
        );
    }
}
