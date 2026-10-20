<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Actividad formativa: ejecución de una acción de un plan. Nunca se elimina. */
class ActividadModel extends Model
{
    protected $table = 'actividades';

    protected $guarded = [];

    /** @return HasMany<ActividadAsistenciaModel, $this> */
    public function asistencias(): HasMany
    {
        return $this->hasMany(ActividadAsistenciaModel::class, 'actividad_id')->orderBy('id');
    }

    /** @return HasMany<ActividadHistorialModel, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(ActividadHistorialModel::class, 'actividad_id')->orderByDesc('id');
    }
}
