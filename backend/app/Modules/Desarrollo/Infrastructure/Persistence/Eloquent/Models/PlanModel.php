<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Plan de formación. No se elimina nunca: el estado es un dato de negocio. */
class PlanModel extends Model
{
    protected $table = 'planes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha_aprobacion' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Un plan no se elimina.');
        });
    }

    /** @return HasMany<PlanAccionModel, $this> */
    public function acciones(): HasMany
    {
        return $this->hasMany(PlanAccionModel::class, 'plan_id')->orderBy('id');
    }
}
