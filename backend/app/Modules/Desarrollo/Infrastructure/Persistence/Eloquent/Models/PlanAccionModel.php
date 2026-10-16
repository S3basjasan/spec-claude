<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanAccionModel extends Model
{
    protected $table = 'plan_acciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['cancelada' => 'boolean'];
    }

    /** @return BelongsTo<PlanModel, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }

    /** @return HasMany<PlanAccionCompetenciaModel, $this> */
    public function competencias(): HasMany
    {
        return $this->hasMany(PlanAccionCompetenciaModel::class, 'plan_accion_id')->orderBy('id');
    }

    /** @return HasMany<PlanAccionParticipanteModel, $this> */
    public function participantes(): HasMany
    {
        return $this->hasMany(PlanAccionParticipanteModel::class, 'plan_accion_id')->orderBy('id');
    }
}
